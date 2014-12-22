<?php
/*
 * This file is part of Techscore
 */



/**
 * Relationship between regatta and hosting school.
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class Host_School extends DBObject {
  protected $regatta;
  protected $school;
  public function db_type($field) {
    switch ($field) {
    case 'school': return DB::T(DB::SCHOOL);
    case 'regatta': return DB::T(DB::REGATTA);
    default:
      return parent::db_type($field);
    }
  }
}
