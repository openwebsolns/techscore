<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/writers
 */

require_once('S3Writer.php');

/**
 * Writes directly to local S3 server (src/daemon)
 *
 * @author Dayan Paez
 * @created 2012-10-10
 */
class S3LocalWriter extends S3Writer {
  public function __construct() {
    parent::__construct();

    // This allows the daemon, which will use this class to verify
    // signatures, to avoid having to include conf.php and all its
    // accoutrements.
    if (class_exists('Conf')) {
      $tokens = explode('.', Conf::$PUB_HOME);
      $this->bucket = array_shift($tokens);
      $this->host_base = implode('.', $tokens);
    }
    $this->port = 9210;
  }
}
?>