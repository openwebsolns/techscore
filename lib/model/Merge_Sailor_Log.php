<?php
/*
 * This file is part of Techscore
 */



/**
 * Which unregistered sailor was merged, and with which sailor
 *
 * This class contains the necessary parameters to 'recreate' the
 * unregistered sailor entry that was deleted during the merge,
 * assuming that role = 'student'.
 *
 * @author Dayan Paez
 * @version 2014-09-14
 */
class Merge_Sailor_Log extends DBObject {
  protected $merge_log;
  protected $school;
  protected $registered_sailor;
  public $last_name;
  public $first_name;
  public $year;
  public $gender;
  public $regatta_added;

  public function db_type($field) {
    switch ($field) {
    case 'merge_log': return DB::T(DB::MERGE_LOG);
    case 'school':    return DB::T(DB::SCHOOL);
    case 'registered_sailor':    return DB::T(DB::SAILOR);
    default: return parent::db_type($field);
    }
  }

  protected function db_order() { return array('last_name'=>true, 'first_name'=>true); }

  /**
   * Creates an unpersisted Sailor based on parameters
   *
   * Useful when a deleted sailor needs to be revived
   *
   * @return Sailor a copy of original unregistered sailor
   */
  public function createUnregisteredSailor() {
    $sailor = new Sailor();
    $sailor->first_name = $this->first_name;
    $sailor->last_name = $this->last_name;
    $sailor->school = $this->__get('school');
    $sailor->year = $this->year;
    $sailor->gender = $this->gender;
    $sailor->role = Sailor::STUDENT;
    $sailor->regatta_added = $this->regatta_added;
    return $sailor;
  }
}
