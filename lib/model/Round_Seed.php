<?php
/*
 * This file is part of Techscore
 */



/**
 * The seeded teams for a round
 *
 * @author Dayan Paez
 * @version 2014-01-13
 */
class Round_Seed extends DBObject {
  public $seed;
  protected $round;
  protected $original_round;
  protected $team;

  public function db_type($field) {
    if ($field == 'round' || $field == 'original_round')
      return DB::T(DB::ROUND);
    if ($field == 'team')
      return DB::T(DB::TEAM);
    return parent::db_type($field);
  }

  protected function db_order() {
    return array('seed'=>true);
  }
}
