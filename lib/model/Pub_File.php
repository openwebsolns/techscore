<?php
/*
 * This file is part of Techscore
 */



/**
 * Public site file
 *
 * @author Dayan Paez
 * @version 2013-10-04
 */
class Pub_File extends Pub_File_Summary {
  public $filedata;
  protected function db_cache() { return true; }
  public function getFile() {
    return $this;
  }
}
