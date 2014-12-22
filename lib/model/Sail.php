<?php
/*
 * This file is part of Techscore
 */



/**
 * Encapsulates a sail: a boat in a given race for a given team
 *
 * @author Dayan Paez
 * @version 2012-01-11
 */
class Sail extends DBObject {
  public $sail;
  protected $race;
  protected $team;
  public $color;

  protected function db_order() { return array('sail'=>true); }
  public function db_name() { return 'rotation'; }
  public function db_type($field) {
    switch ($field) {
    case 'team': return DB::T(DB::TEAM);
    case 'race': return DB::T(DB::RACE);
    default:
      return parent::db_type($field);
    }
  }
  public function __toString() { return (string)$this->sail; }
  /**
   * Returns a string representation of sail that is unique for a
   * given race, team pairing
   *
   * @return String race_id-team_id
   */
  public function hash() {
    $r = ($this->race instanceof Race) ? $this->race->id : $this->race;
    $t = ($this->team instanceof Team) ? $this->team->id : $this->team;
    return sprintf('%s-%s', $r, $t);
  }
}
