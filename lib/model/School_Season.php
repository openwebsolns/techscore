<?php
/*
 * This file is part of Techscore
 */



/**
 * Indicator of which seasons a school was active in
 *
 * @author Dayan Paez
 * @version 2014-09-28
 */
class School_Season extends Element_Season {
  protected $school;

  public function db_type($field) {
    if ($field == 'school')
      return DB::T(DB::SCHOOL);
    return parent::db_type($field);
  }
}
