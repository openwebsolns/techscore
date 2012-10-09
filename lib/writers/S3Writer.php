<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/writers
 */

require_once('AbstractWriter.php');

/**
 * Writes directly to an S3 bucket
 *
 * @author Dayan Paez
 * @created 2012-10-09
 */
class S3Writer extends AbstractWriter {
  public static $BUCKET = 'live.collegesailing.org';
  public static $ACCESS_KEY = 'AKIAJUTLT5IE6R5UZXNA';
  public static $SECRET_KEY = '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';
  public static $HOST_BASE = 's3.amazonaws.com';

  public function __construct() {
    if (self::$BUCKET === null) {
      require_once(dirname(__FILE__) . '/S3Writer.conf.local.php');
      if (empty(self::$BUCKET) ||
          empty(self::$ACCESS_KEY) ||
          empty(self::$SECRET_KEY) ||
          empty(self::$HOST_BASE))
        throw new TSWriterException("Missing parameters for S3Writer.");
    }
  }

  /**
   * Helper method: prepare the S3 request
   *
   */
  protected function prepRequest(&$fname) {
    $ch = curl_init(sprintf('https://%s.%s%s', self::$BUCKET, self::$HOST_BASE, $fname));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TS3 Bot');
    return $ch;                                               
  }

  /**
   * Rudimentary method to determine the type of file based on filename
   *
   * @param String $fname the name of the file
   * @throws TSWriterException if unknown
   */
  protected function getMIME($fname) {
    $tokens = explode('.', $fname);
    if (count($tokens) < 2)
      throw new TSWriterException("No extension for file $fname.");
    $suff = array_pop($tokens);
    switch ($suff) {
    case 'html':
      return 'text/html';
    case 'png':
      return 'image/png';
    case 'css':
      return 'text/css';
    case 'js':
      return 'text/javascript';
    default:
      throw new TSScriptException("Unknown extension: $suff");
    }
  }

  public function write($fname, &$contents) {
    $type = $this->getMIME($fname);
    $size = strlen($contents);
    $date = date('D, d M Y H:i:s T');
    $md5 = base64_encode(md5($contents, true));

    $headers = array();
    $headers[] = sprintf('Host: %s.%s', self::$BUCKET, self::$HOST_BASE);
    $headers[] = sprintf('Content-Length: %s', $size);
    $headers[] = sprintf('Content-Type: %s', $type);
    $headers[] = sprintf('Content-MD5: %s', $md5);
    $headers[] = sprintf('Date: %s', $date);

    $string_to_sign = sprintf("PUT\n%s\n%s\n%s\n/%s%s",
                              $md5,
                              $type,
                              $date,
                              self::$BUCKET, $fname);
    $signature = base64_encode(hash_hmac('sha1', $string_to_sign, self::$SECRET_KEY, true));
    $headers[] = sprintf('Authorization: AWS %s:%s', self::$ACCESS_KEY, $signature);
                              
    $ch = $this->prepRequest($fname);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (($fp = fopen('php://temp', 'w+')) === false)
      throw new TSWriterException("Unable to create temporary file for $fname");
    fwrite($fp, $contents);
    fseek($fp, 0);

    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_INFILESIZE, $size);
    if (($output = curl_exec($ch)) === false) {
      $mes = curl_error($ch);
      curl_close($ch);
      throw new TSWriterException($mes);
    }

    $data = curl_getinfo($ch);
    if ($data['http_code'] != 200)
      throw new TSWriterException(sprintf("HTTP error %s: %s", $data['http_code'], $output));
    curl_close($ch);
  }

  /**
   * Removes the given file, which must not be a directory
   *
   */
  public function remove($fname) {
    $date = date('D, d M Y H:i:s T');

    $headers = array();
    $headers[] = sprintf('Host: %s.%s', self::$BUCKET, self::$HOST_BASE);
    $headers[] = sprintf('Date: %s', $date);

    $string_to_sign = sprintf("DELETE\n\n\n%s\n/%s%s",
                              $date,
                              self::$BUCKET, $fname);
    $signature = base64_encode(hash_hmac('sha1', $string_to_sign, self::$SECRET_KEY, true));
    $headers[] = sprintf('Authorization: AWS %s:%s', self::$ACCESS_KEY, $signature);
                              
    $ch = $this->prepRequest($fname);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    if (($output = curl_exec($ch)) === false) {
      $mes = curl_error($ch);
      curl_close($ch);
      throw new TSWriterException($mes);
    }

    $data = curl_getinfo($ch);
    if ($data['http_code'] > 400)
      throw new TSWriterException(sprintf("HTTP error %s: %s", $data['http_code'], $output));
    curl_close($ch);
  }
}
?>