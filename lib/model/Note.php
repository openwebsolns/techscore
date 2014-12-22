<?php
/*
 * This file is part of Techscore
 */



/**
 * An observation during a race
 *
 * @author Dayan Paez
 * @version 2012-01-11
 */
class Note extends DBObject {
  public $observation;
  public $observer;
  protected $race;
  protected $noted_at;

  protected function db_order() { return array('noted_at' => true); }
  public function db_name() { return 'observation'; }
  public function db_type($field) {
    switch ($field) {
    case 'noted_at': return DB::T(DB::NOW);
    case 'race': return DB::T(DB::RACE);
    default:
      return parent::db_type($field);
    }
  }
  public function __toString() { return $this->observation; }
}
