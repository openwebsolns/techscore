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

class Dt_Regatta extends DBObject {
  public $name;
  public $nick;
  protected $start_time;
  protected $end_date;
  protected $venue;
  public $type;
  protected $finalized;
  public $scoring;
  public $num_divisions;
  public $num_races;
  public $hosts;
  public $confs;
  public $boats;
  public $singlehanded;
  public $season;
  public $status;
  public $participant;

  public function db_type($field) {
    switch ($field) {
    case 'start_time':
    case 'end_date':
    case 'finalized':
      return DB::$NOW;

    case 'venue':
      return DB::$VENUE;

    default:
      return parent::db_type($field);
    }
  }
  protected function db_cache() { return true; }
  protected function db_order() { return array('start_time'=>false); }

  /**
   * How many days is the regatta worth
   *
   * @return int number of days
   */
  public function duration() {
    $end = new DateTime($this->end_time->format('Y-m-d'));
    $str = new DateTime($this->start_time->format('Y-m-d'));
    $str->setTime(0, 0);
    $end->setTime(0, 0);
    
    return (int)($end->format('U') - $str->format('U')) / 86400;
  }

  /**
   * Deletes all data about my teams
   */
  public function deleteTeams() {
    DB::removeAll(DB::$DT_TEAM, new DBCond('regatta', $this->id));
  }

  public function getTeams() {
    return DB::getAll(DB::$DT_TEAM, new DBCond('regatta', $this->id));
  }

  /**
   * Return the teams ranked in the given division
   *
   * @param String $div the division
   * @return Array:Dt_Team_Division
   */
  public function getRanks($div) {
    $q = DB::prepGetAll(DB::$DT_TEAM, new DBCond('regatta', $this->id), array('id'));
    return DB::getAll(DB::$DT_TEAM_DIVISION, new DBBool(array(new DBCond('division', $div),
							      new DBCondIn('team', $q))));
  }

  public function getHosts() {
    $list = array();
    foreach (explode(',', $this->hosts) as $id) {
      $sch = DB::get(DB::$SCHOOL, $id);
      if ($sch !== null)
        $list[] = $sch;
    }
    return $list;
  }

  // ------------------------------------------------------------
  // RP information
  // ------------------------------------------------------------

  public function getParticipation(Sailor $sailor, $division = null, $role = null) {
    $team = DB::prepGetAll(DB::$DT_TEAM, new DBCond('regatta', $this->id), array('id'));
    
    $cond = new DBBool(array(new DBCondIn('team', $team)));
    if ($division !== null)
      $cond->add(new DBCond('division', $division));
    $tdiv = DB::prepGetAll(DB::$DT_TEAM_DIVISION, $cond, array('id'));

    $cond = new DBBool(array(new DBCondIn('team_division', $tdiv),
			     new DBCond('sailor', $sailor->id)));
    if ($role !== null)
      $cond->add(new DBCond('boat_role', $role));
    return DB::getAll(DB::$DT_RP, $cond);
  }
}

class Dt_Team extends DBObject {
  protected $regatta;
  protected $school;
  public $name;
  public $rank;
  public $rank_explanation;

  public function db_type($field) {
    switch ($field) {
    case 'regatta':
      return DB::$DT_REGATTA;
    case 'school':
      return DB::$SCHOOL;
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('rank'=>true); }
  protected function db_cache() { return true; }

  public function __toString() {
    return sprintf("%s %s", $this->__get('school')->nick_name, $this->name);
  }

  /**
   * Returns this team's rank within the given division, if one exists
   *
   * @param String $division the possible division
   * @return Dt_Team_Division|null the rank
   */
  public function getRank($division) {
    $r = DB::getAll(DB::$DT_TEAM_DIVISION, new DBBool(array(new DBCond('team', $this->id),
							    new DBCond('division', $division))));
    $b;
    if (count($r) == 0) $b = null;
    else $b = $r[0];
    unset($r);
    return $b;
  }

  // ------------------------------------------------------------
  // RP
  // ------------------------------------------------------------

  /**
   * Gets the RP for this team in the given division and role
   *
   * @param String $div the division, or null for all divisions
   * @param String $role 'skipper', or 'crew'
   * @return Array:Dt_RP the rp for that team
   */
  public function getRP($div = null, $role = 'skipper') {
    if ($div !== null) {
      $rank = $this->getRank($div);
      if ($rank === null)
	return array();
      return $rank->getRP($role);
    }
    $q = DB::prepGetAll(DB::$DT_TEAM_DIVISION, new DBCond('team', $this->id), array('id'));
    return DB::getAll(DB::$DT_RP, new DBBool(array(new DBCond('boat_role', $role),
						   new DBCondIn('team_division', $q))));
  }

  /**
   * Removes all RP entries for this team from the database
   *
   * @param String $div the division whose RP info to reset
   */
  public function resetRP($div) {
    $q = DB::prepGetAll(DB::$DT_TEAM_DIVISION,
			new DBBool(array(new DBCond('team', $this->id), new DBCond('division', $div))),
			array('id'));
    foreach (DB::getAll(DB::$DT_RP, new DBCondIn('team_division', $q)) as $rp)
      DB::remove($rp);
  }
}

class Dt_Rp extends DBObject {
  const SKIPPER = 'skipper';
  const CREW = 'crew';

  protected $team_division;
  protected $race_nums;
  protected $sailor;
  public $boat_role;

  public function db_type($field) {
    if ($field == 'sailor') return DB::$SAILOR;
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

  public function db_name() { return 'dt_team_division'; }
  public function db_type($field) {
    if ($field == 'team') return DB::$DT_TEAM;
    return parent::db_type($field);
  }
  protected function db_order() { return array('division'=>true, 'rank'=>true); }

  public function getRP($role = 'skipper') {
    return DB::getAll(DB::$DT_RP, new DBBool(array(new DBCond('boat_role', $role),
						   new DBCond('team_division', $this->id))));
  }
}

DB::$DT_REGATTA = new Dt_Regatta();
DB::$DT_TEAM = new Dt_Team();
DB::$DT_TEAM_DIVISION = new Dt_Team_Division();
DB::$DT_RP = new Dt_Rp();
?>
