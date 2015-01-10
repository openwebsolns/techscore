<?php
/*
 * This file is part of Techscore
 */



/**
 * Request to update a public file.
 *
 * @author Dayan Paez
 * @version 2013-10-04
 */
class UpdateFileRequest extends AbstractUpdate {
  public $file;
  public static function getTypes() { return array(); }
  public function db_name() { return 'pub_update_file'; }
  public function hash() { return $this->file; }
}
