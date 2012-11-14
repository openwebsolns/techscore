<?php
/*
 * A different way of serializing and deserializing objects using the
 * DBM class created by Dayan Paez.
 *
 * @author Dayan Paez
 * @version 2011-01-22
 * @package mysql
 */

require_once('regatta/DB.php');

class Dt_Rp extends DBObject {
  const SKIPPER = 'skipper';
  const CREW = 'crew';

  protected $team_division;
  protected $race_nums;
  protected $sailor;
  public $boat_role;
  public $rank;
  public $explanation;

  public function db_type($field) {
    if ($field == 'sailor') return DB::$MEMBER;
    if ($field == 'race_nums') return array();
    if ($field == 'team_division') return DB::$DT_TEAM_DIVISION;
    return parent::db_type($field);
  }
  protected function db_order() { return array('race_nums'=>true); }
}

/**
 * Team rank within division, and possible penalty
 *
 * @author Dayan Paez
 * @version 2011-03-06
 */
class Dt_Team_Division extends DBObject {
  protected $team;
  public $division;
  public $rank;
  public $explanation;
  public $penalty;
  public $comments;
  public $score;

  public function db_name() { return 'dt_team_division'; }
  public function db_type($field) {
    if ($field == 'team') return DB::$TEAM;
    return parent::db_type($field);
  }
  protected function db_order() { return array('rank'=>true); }

  public function getRP($role = Dt_Rp::SKIPPER) {
    return DB::getAll(DB::$DT_RP, new DBBool(array(new DBCond('boat_role', $role),
                                                   new DBCond('team_division', $this->id))));
  }
}

DB::$DT_TEAM_DIVISION = new Dt_Team_Division();
DB::$DT_RP = new Dt_Rp();
?>
