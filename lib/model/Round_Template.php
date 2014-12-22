<?php
/*
 * This file is part of Techscore
 */



/**
 * Race order for round
 *
 * @author Dayan Paez
 * @version 2014-04-02
 */
class Round_Template extends DBObject {
  public $team1;
  public $team2;
  protected $round;
  protected $boat;

  public function db_type($field) {
    if ($field == 'round')
      return DB::T(DB::ROUND);
    if ($field == 'boat')
      return DB::T(DB::BOAT);
    return parent::db_type($field);
  }
}
