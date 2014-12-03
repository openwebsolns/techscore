<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

/**
 * Javascript file to load other javascript files
 *
 * This file allows us to create a more dynamic experience in a
 * static-site environment, without having to regenerate the entire
 * site.
 *
 * @author Dayan Paez
 * @created 2014-12-02
 */
class InitJs implements Writeable {

  private $filedata;

  private function getFiledata() {
    if ($this->filedata == null) {
      $this->filedata = '';
    }
    return $this->filedata;
  }

  /**
   * Implementation of Writeable
   *
   */
  public function write($resource) {
    fwrite($resource, $this->getFiledata());
  }
}
?>