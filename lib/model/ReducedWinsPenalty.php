<?php
namespace model;

use \DB;

/**
 * Tracks "discretionary" penalty assigned to a team and optionally a race.
 */
class ReducedWinsPenalty extends AbstractObject {
  protected $team;
  protected $race;
  public $amount;
  public $comments;

  public function db_name() {
    return 'reduced_wins_penalty';
  }

  public function db_type($field) {
    switch ($field) {
    case 'team':
      return DB::T(DB::TEAM);
    case 'race':
      return DB::T(DB::RACE);
    default:
      return parent::db_type($field);
    }
  }
}