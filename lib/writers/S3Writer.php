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
  protected function prepRequest($fname) {
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
      return 'text/html; charset=utf-8';
    case 'png':
      return 'image/png';
    case 'css':
      return 'text/css';
    case 'js':
      return 'text/javascript';
    case 'ico':
      return 'image/x-icon';
    case 'svg':
      return 'image/svg+xml';
    case 'pdf':
      return 'application/pdf';
    case 'gif':
      return 'image/gif';
    case 'jpg':
      return 'image/jpg';
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
    if (substr($fname, -5) != '.html' && substr($fname, -4) != '.svg')
      $headers[] = 'Cache-Control: no-cache, max-age=1209600';

    foreach ($extra_headers as $i => $header)
      $headers[] = $header;

    $signature = $this->sign($method, $md5, $type, $date, $fname, $extra_headers);
    $headers[] = sprintf('Authorization: AWS %s:%s', $this->access_key, $signature);
    return $headers;
  }

  public function write($fname, Writeable $elem) {
    $fp = tmpfile();
    $data = stream_get_meta_data($fp);
    $filename = $data['uri'];
    $elem->write($fp);
    fseek($fp, 0);

    $type = $this->getMIME($fname);
    $size = filesize($filename);
    $md5 = base64_encode(md5_file($filename, true));
    $headers = $this->getHeaders('PUT', $md5, $type, $fname);

    $ch = $this->prepRequest($fname);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_INFILESIZE, $size);
    if (($output = curl_exec($ch)) === false) {
      $mes = curl_error($ch);
      fclose($fp);
      curl_close($ch);
      throw new TSWriterException($mes);
    }

    $data = curl_getinfo($ch);
    fclose($fp);
    curl_close($ch);
    if ($data['http_code'] >= 400)
      throw new TSWriterException(sprintf("HTTP error %s: %s", $data['http_code'], $output));
  }

  /**
   * Removes the given file, which must not be a directory
   *
   */
  public function remove($fname) {
    $objs = $this->listobjects(substr($fname, 1), true);
    $cnt = count($objs);
    if ($cnt == 0)
      return;

    require_once('xml5/XmlLib.php');
    for ($round = 0; $round < (int)($cnt / 1000) + 1; $round++) {
      // create XML doc
      $P = new XDoc('Delete', array(), array(new XElem('Quiet', array(), array(new XText("true")))));
      for ($i = $round * 1000; $i < ($round + 1) * 1000 && $i < $cnt; $i++) {
        $P->add(new XElem('Object', array(),
                          array(new XElem('Key', array(), array(new XText(substr($objs[$i], 1)))))));
      }

      // Submit form
      $mes = $P->toXML();
      $md5 = base64_encode(md5($mes, true));
      $headers = $this->getHeaders('POST', $md5, 'application/xml', '/?delete');
      $headers[] = sprintf('Content-Length: %s', strlen($mes));

      $ch = $this->prepRequest('/?delete');
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $mes);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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

  public function listdir($dirname) {
    if ($dirname[strlen($dirname) - 1] != '/')
      $dirname .= '/';
    return $this->listobjects(substr($dirname, 1), false);
  }

  /**
   * Helper method will list the contents of a given bucket under a
   * given prefix, which may be a directory. The third argument will
   * only include in the return set the objects directly underneath
   * the prefix, that is any objects that share the prefix and whose
   * filename remainder contains no more slashes.
   *
   * @param Site $dept the site
   * @param String $prefix the prefix to fetch
   * @param boolean $recursive true (default) to get EVERY subobject
   * @param String $marker used internally to split into multiple requests
   */
  public function listobjects($prefix, $recursive = true, $marker = null) {
    $fname = '/?prefix=' . $prefix;
    if ($recursive === false)
      $fname .= '&delimiter=/';
    if ($marker !== null)
      $fname .= '&marker=' . $marker;

    $date = date('D, d M Y H:i:s T');

    $headers = array();
    $headers[] = sprintf('Host: %s.%s', $this->bucket, $this->host_base);
    $headers[] = sprintf('Date: %s', $date);

    $signature = $this->sign("GET", "", "", $date, '/');
    $headers[] = sprintf('Authorization: AWS %s:%s', $this->access_key, $signature);

    $ch = $this->prepRequest($fname);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (($output = curl_exec($ch)) === false) {
      $mes = curl_error($ch);
      curl_close($ch);
      throw new TSWriterException($mes);
    }

    $data = curl_getinfo($ch);
    if ($data['http_code'] >= 400)
      throw new TSWriterException(sprintf("HTTP error %s: %s", $data['http_code'], $output));
    curl_close($ch);

    // Parse output
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($output);
    if (!$doc) {
      $errors = "";
      foreach(libxml_get_errors() as $error)
        $errors .= '@' . $error->line . ',' . $error->column . ': ' . $error->message . "\n";
      throw new TSWriterException("Invalid response: $errors");
    }

    // Step through XML
    $list = array();
    foreach ($doc->Contents as $sub)
      $list[] = '/' . $sub->Key;

    // Fetch the remaining
    if ((string)$doc->IsTruncated == 'true') {
      foreach ($this->listobjects($prefix, $recursive, substr($list[count($list) - 1], 1)) as $sub)
        $list[] = $sub;
    }
    return $list;
  }
}
?>
