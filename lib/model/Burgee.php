<?php
/*
 * This file is part of Techscore
 */



/**
 * Burgees: primary key matches with (and is a foreign key) to school.
 *
 * @author Dayan Paez
 * @version 2012-01-07
 */
class Burgee extends DBObject implements Writeable {
  public $filedata;
  public $width;
  public $height;
  protected $last_updated;
  protected $school;
  public $updated_by;

  public function db_type($field) {
    switch ($field) {
    case 'last_updated': return DB::T(DB::NOW);
    case 'school': return DB::T(DB::SCHOOL);
    default:
      return parent::db_type($field);
    }
  }

  public function write($resource) {
    fwrite($resource, base64_decode($this->filedata));
  }
}
