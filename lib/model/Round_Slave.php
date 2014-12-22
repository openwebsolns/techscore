<?php
/*
 * This file is part of Techscore
 */



/**
 * Rounds (slaves) that carry over from other rounds (masters)
 *
 * When carrying over races from other rounds, those teams that have
 * already met do not race again. The slave round will only include
 * the races necessary to complete the impartial round-robins from the
 * master rounds.
 *
 * Because of this, the net number of races that are created for the
 * slave round depends on the number of teams that carry over from all
 * of the master rounds. It is imperative that this number of races
 * remain the same, even after teams are substituted. As a result,
 * each master-slave record must also indicate the number of teams
 * that are to "advance" from one round to another.
 *
 * @author Dayan Paez
 * @version 2013-05-20
 */
class Round_Slave extends DBObject {
  protected $master;
  protected $slave;
  public $num_teams;

  public function db_type($field) {
    switch ($field) {
    case 'master':
    case 'slave':
      return DB::T(DB::ROUND);
    default:
      return parent::db_type($field);
    }
  }
}
