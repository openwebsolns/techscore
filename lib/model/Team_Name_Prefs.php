<?php
/**
 * Preference for team name, as specified by a school's user
 *
 * @author Dayan Paez
 * @version 2012-01-11
 */
class Team_Name_Prefs extends DBObject {

  /**
   * Un-delimited regular expression for allowed names.
   */
  const REGEX_NAME = '[^0-9]+$';

  protected $school;
  public $name;
  public $rank;

  public function db_name() { return 'team_name_prefs'; }
  public function db_type($field) {
    if ($field == 'school')
      return DB::T(DB::SCHOOL);
    return parent::db_type($field);
  }
  protected function db_order() { return array('school'=>true, 'rank'=>false); }
  public function __toString() { return (string)$this->name; }
}
