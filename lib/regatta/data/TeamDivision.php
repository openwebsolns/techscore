<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2011-05-14
 * @package tscore-data
 */

/**
 * Encapsulates a finishing team's information in a given division,
 * and is technically only applicable for non-personal regattas. This
 * class is reserved for data analysis, and is not part of the
 * standard set of objects which are used during the course of scoring
 * a regatta.
 *
 * Note that since this field is not associated directly with a
 * regatta, nor should it be, the properties are all flat strings, and
 * not nested objects, except for division, which is a division object
 * upon serialization.
 *
 * @see Dt_Team_Division, under DBObject
 */
class TeamDivision extends DBObject {
  protected $team;
  protected $division;
  public $rank;
  public $explanation;
  public $penalty;
  public $comments;

  public function db_name() { return 'dt_team_division'; }
  public function db_type($field) {
    switch ($field) {
    case 'team': return DB::$TEAM;
    case 'division': return DBQuery::A_STR;
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('division'=>true, 'rank'=>true); }

  public function &__get($name) {
    switch ($name) {
    case 'division': return Division::get($this->division);
    default:
      return parent::__get($name);
    }
  }
  public function __set($name, $value) {
    if ($name == 'division') {
      if ($value === null)
	$this->division = null;
      elseif ($value instanceof Division)
	$this->division = (string)$value;
      else
	throw new InvalidArgumentException("Division must be a Division object.");
      return;
    }
    parent::__set($name, $value);
  }
}
DB::$TEAM_DIVISION = new TeamDivision();
?>