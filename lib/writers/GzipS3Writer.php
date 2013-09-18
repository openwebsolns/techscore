<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/writers
 */

require_once('S3Writer.php');

/**
 * Gzipped version of the S3 writer
 *
 * @author Dayan Paez
 * @created 2013-09-17
 */
class GzipS3Writer extends S3Writer {
  public function __construct() {
    parent::__construct();
    $fname = dirname(__FILE__) . '/GzipS3Writer.conf.local.php';
    if (file_exists($fname))
      require($fname);
  }

  public function getHeaders($method, $md5, $type, $fname, Array $extra_headers = array()) {
    $headers = parent::getHeaders($method, $md5, $type, $fname, $extra_headers);
    if ($method == 'PUT')
      $headers[] = 'Content-Encoding: gzip';
    return $headers;
  }

  public function write($fname, &$contents) {
    $gzip = gzencode($contents, 9);
    if ($gzip === false)
      throw new TSWriterException("Unable to compress file $fname", 8);
    parent::write($fname, $gzip);
  }
}
?>