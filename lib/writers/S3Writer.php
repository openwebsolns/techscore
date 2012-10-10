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
  public $bucket;
  public $access_key;
  public $secret_key;
  public $host_base;
  public $port;

  public function __construct() {
    require(dirname(__FILE__) . '/S3Writer.conf.local.php');
  }

  /**
   * Helper method: prepare the S3 request
   *
   */
  protected function prepRequest(&$fname) {
    if (empty($this->bucket) ||
        empty($this->access_key) ||
        empty($this->secret_key) ||
        empty($this->host_base))
      throw new TSWriterException("Missing parameters for S3Writer.");

    $ch = curl_init(sprintf('http://%s.%s%s', $this->bucket, $this->host_base, $fname));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TS3 Bot');
    if ($this->port !== null)
      curl_setopt($ch, CURLOPT_PORT, $this->port);
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
      throw new TSWriterException("Unknown extension: $suff");
    }
  }

  public function sign($method, $md5, $type, $date, $fname) {
    $string_to_sign = sprintf("%s\n%s\n%s\n%s\n/%s%s",
                              $method,
                              $md5,
                              $type,
                              $date,
                              $this->bucket, $fname);
    return base64_encode(hash_hmac('sha1', $string_to_sign, $this->secret_key, true));
  }

  public function write($fname, &$contents) {
    $type = $this->getMIME($fname);
    $size = strlen($contents);
    $date = date('D, d M Y H:i:s T');
    $md5 = base64_encode(md5($contents, true));

    $headers = array();
    $headers[] = sprintf('Host: %s.%s', $this->bucket, $this->host_base);
    $headers[] = sprintf('Content-Length: %s', $size);
    $headers[] = sprintf('Content-Type: %s', $type);
    $headers[] = sprintf('Content-MD5: %s', $md5);
    $headers[] = sprintf('Date: %s', $date);

    $signature = $this->sign("PUT", $md5, $type, $date, $fname);
    $headers[] = sprintf('Authorization: AWS %s:%s', $this->access_key, $signature);
                              
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
    if ($data['http_code'] >= 400)
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
    $headers[] = sprintf('Host: %s.%s', $this->bucket, $this->host_base);
    $headers[] = sprintf('Date: %s', $date);

    $signature = $this->sign("DELETE", "", "", $date, $fname);
    $headers[] = sprintf('Authorization: AWS %s:%s', $this->access_key, $signature);
                              
    $ch = $this->prepRequest($fname);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    if (($output = curl_exec($ch)) === false) {
      $mes = curl_error($ch);
      curl_close($ch);
      throw new TSWriterException($mes);
    }

    $data = curl_getinfo($ch);
    if ($data['http_code'] >= 400)
      throw new TSWriterException(sprintf("HTTP error %s: %s", $data['http_code'], $output));
    curl_close($ch);
  }
}
?>