<?php
/*
 * This file is part of Techscore
 */



/**
 * Link between Sailor and Team: representative for the RP
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class Representative extends DBObject {
  protected $team;
  public $name;

  public function db_type($field) {
    switch ($field) {
    case 'team': return DB::T(DB::TEAM);
    default:
      return parent::db_type($field);
    }
  }

  public function __toString() { return $this->name; }
}
