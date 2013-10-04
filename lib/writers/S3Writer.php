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
    case 'ico':
      return 'image/x-icon';
    default:
      throw new TSWriterException("Unknown extension: $suff");
    }
  }

  protected function canonicalizeAmzHeaders(Array $headers = array()) {
    if (count($headers) == 0)
      return "";
    $uniq = array();
    foreach ($headers as $header) {
      $tokens = explode(":", $header);
      $h = strtolower(array_shift($tokens));
      if (count($tokens) == 0)
        continue;
      if (!isset($uniq[$h]))
        $uniq[$h] = array();
      $uniq[$h][] = trim(implode(":", $tokens));
    }
    $str = "";
    foreach ($uniq as $h => $v)
      $str .= sprintf("%s:%s\n", $h, implode(",", $v));
    return $str;
  }

  public function sign($method, $md5, $type, $date, $fname, Array $extra = array()) {
    $string_to_sign = sprintf("%s\n%s\n%s\n%s\n%s/%s%s",
                              $method,
                              $md5,
                              $type,
                              $date,
                              $this->canonicalizeAmzHeaders($extra),
                              $this->bucket, $fname);
    return base64_encode(hash_hmac('sha1', $string_to_sign, $this->secret_key, true));
  }

  protected function getHeaders($method, $md5, $type, $fname, Array $extra_headers = array()) {
    $date = date('D, d M Y H:i:s T');

    $headers = array();
    $headers[] = sprintf('Host: %s.%s', $this->bucket, $this->host_base);
    if ($type !== null)
      $headers[] = sprintf('Content-Type: %s', $type);
    if ($md5 !== null)
      $headers[] = sprintf('Content-MD5: %s', $md5);
    $headers[] = sprintf('Date: %s', $date);

    foreach ($extra_headers as $i => $header)
      $headers[] = $header;

    $signature = $this->sign($method, $md5, $type, $date, $fname, $extra_headers);
    $headers[] = sprintf('Authorization: AWS %s:%s', $this->access_key, $signature);
    return $headers;
  }

  public function write($fname, &$contents) {
    $type = $this->getMIME($fname);
    $size = strlen($contents);
    $md5 = base64_encode(md5($contents, true));
    $headers = $this->getHeaders('PUT', $md5, $type, $fname);

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
    $headers = $this->getHeaders('DELETE', null, null, $fname);
                              
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
