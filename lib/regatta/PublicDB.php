<?php
/*
 * A different way of serializing and deserializing objects using the
 * DBM class created by Dayan Paez.
 *
 * @author Dayan Paez
 * @version 2011-01-22
 * @package mysql
 */

require_once('mysqli/DBM.php');

/**
 * Provides some more functionality
 *
 */
class DBME extends DBM {
  /**
   * Empty objects to serve as prototypes
   */
  public static $TEAM_DIVISION = null;
  public static $REGATTA = null;
  public static $SCORE = null;
  public static $VENUE = null;
  public static $TEAM = null;
  public static $RACE = null;
  public static $SAIL = null;
  public static $NOW = null;
  public static $RP = null;
  public static $ARRAY = array();
  
  // use this method to initialize the different objects as well
  public static function setConnection(MySQLi $con) {
    self::$TEAM_DIVISION = new Dt_Team_Division();
    self::$REGATTA = new Dt_Regatta();
    self::$SCORE = new Dt_Score();
    self::$TEAM = new Dt_Team();
    self::$RACE = new Dt_Race();
    self::$SAIL = new Dt_Sail();
    self::$NOW = new DateTime();
    self::$RP = new Dt_Rp();

    DBM::setConnection($con);
  }

  public static function parseSeason($str) {
    if (strlen($str) == 0) return null;
    $s = null;
    switch (strtolower($str[0])) {
    case 'f': $s = 'fall'; break;
    case 'm': $s = 'summer'; break;
    case 's': $s = 'spring'; break;
    case 'w': $s = 'winter'; break;
    default: return null;
    }
    $y = substr($str, 1);
    if (!is_numeric($y)) return null;
    $y = (int)$y;
    $y += ($y < 90) ? 2000 : 1900;

    $res = self::getAll(self::$SEASON,
			new DBBool(array(new DBCond('season', $s),
					 new DBCond('year(start_date)', $y))));

    if (count($res) == 0)
      return null;
    return $res[0];
  }

  public static function getSeason(DateTime $t) {
    $res = self::getAll(self::$SEASON, new DBBool(array(new DBCond('start_date', $t, DBCond::LE),
							new DBCond('end_date',   $t, DBCond::GE))));
    if (count($res) == 0)
      return null;
    return $res[0];
  }
}

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
      return DBME::$NOW;

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
    DBME::removeAll(DBME::$TEAM, new DBCond('regatta', $this->id));
  }

  public function getTeams() {
    return DBME::getAll(DBME::$TEAM, new DBCond('regatta', $this->id));
  }

  /**
   * Return the teams ranked in the given division
   *
   * @param String $div the division
   * @return Array:Dt_Team_Division
   */
  public function getRanks($div) {
    $q = DBME::prepGetAll(DBME::$TEAM, new DBCond('regatta', $this->id), array('id'));
    return DBME::getAll(DBME::$TEAM_DIVISION, new DBBool(array(new DBCond('division', $div),
							       new DBCondIn('team', $q))));
  }

  public function getHosts() {
    $list = array();
    foreach (explode(',', $this->hosts) as $id) {
      $sch = DBME::get(DB::$SCHOOL, $id);
      if ($sch !== null)
        $list[] = $sch;
    }
    return $list;
  }

  // ------------------------------------------------------------
  // SCORING
  // ------------------------------------------------------------

  /**
   * Returns a list of all the races that have been scored in the
   * given division
   *
   * @param String $division the division to fetch
   * @return Array:Race the scored races
   */
  public function getScoredRaces($division) {
    $p = DBME::$SCORE;
    $q = DBME::prepGetAll($p, null, array('race'));
    $q->distinct(true);

    return DBME::getAll(DBME::$RACE, new DBBool(array(new DBCond('regatta', $this->id),
						      new DBCond('division', $division),
						      new DBCondIn('id', $q))));
  }

  /**
   * Gets the finish from this regatta for the given team in the given race
   *
   * @param Dt_Race $race the particular race
   * @param Dt_Team $team the particular team
   * @return Dt_Score|null the particular score
   */
  public function getFinish(Dt_Race $race, Dt_Team $team) {
    $id = $race . '-' . $team->id;
    if (isset($this->finishes[$id]))
      return $this->finishes[$id];

    $r = DBME::getAll(DBME::$SCORE, new DBBool(array(new DBCond('race', $race->id),
						     new DBCond('team', $team->id))));
    if (count($r) == 0)
      $this->finishes[$id] = null;
    else
      $this->finishes[$id] = $r[0];
    unset($r);
    return $this->finishes[$id];
  }
  private $finishes = array();

  /**
   * Returns the races for this regatta
   *
   * @param String $division the division
   * @return Array:Dt_Race the races
   */
  public function getRaces($division) {
    return DBME::getAll(DBME::$RACE, new DBBool(array(new DBCond('regatta', $this->id),
						      new DBCond('division', $division))));
  }

  // ------------------------------------------------------------
  // RP information
  // ------------------------------------------------------------

  public function getParticipation(Sailor $sailor, $division = null, $role = null) {
    $team = DBME::prepGetAll(DBME::$TEAM, new DBCond('regatta', $this->id), array('id'));
    
    $cond = new DBBool(array(new DBCondIn('team', $team)));
    if ($division !== null)
      $cond->add(new DBCond('division', $division));
    $tdiv = DBME::prepGetAll(DBME::$TEAM_DIVISION, $cond, array('id'));

    $cond = new DBBool(array(new DBCondIn('team_division', $tdiv),
			     new DBCond('sailor', $sailor->id)));
    if ($role !== null)
      $cond->add(new DBCond('boat_role', $role));
    return DBME::getAll(DBME::$RP, $cond);
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
      return DBME::$REGATTA;
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
    $r = DBME::getAll(DBME::$TEAM_DIVISION, new DBBool(array(new DBCond('team', $this->id),
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
    $q = DBME::prepGetAll(DBME::$TEAM_DIVISION, new DBCond('team', $this->id), array('id'));
    return DBME::getAll(DBME::$RP, new DBBool(array(new DBCond('boat_role', $role),
						    new DBCondIn('team_division', $q))));
  }

  /**
   * Removes all RP entries for this team from the database
   *
   * @param String $div the division whose RP info to reset
   */
  public function resetRP($div) {
    $q = DBME::prepGetAll(DBME::$TEAM_DIVISION,
			  new DBBool(array(new DBCond('team', $this->id), new DBCond('division', $div))),
			  array('id'));
    foreach (DBME::getAll(DBME::$RP, new DBCondIn('team_division', $q)) as $rp)
      DBME::remove($rp);
  }
}

class Dt_Race extends DBObject {
  protected $regatta;
  public $division;
  public $number;

  public function db_name() { return 'race'; }
  protected function db_cache() { return true; }
  public function db_type($field) {
    if ($field == 'regatta') return DBME::$REGATTA;
    return parent::db_type($field);
  }

  public function __toString() {
    return $this->number . $this->division;
  }

  /**
   * Returns all the sails in this race
   *
   * @return Array:Dt_Sail the sails
   */
  public function getSails() {
    return DBME::getAll(DBME::$SAIL, new DBCond('race', $this->id));
  }
}

class Dt_Sail extends DBObject {
  protected $race;
  protected $team;
  public $sail;

  public function db_name() { return 'rotation'; }
  public function db_type($field) {
    switch ($field) {
    case 'race': return DBME::$RACE;
    case 'team': return DBME::$TEAM;
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('sail'=>true); }
  public function __toString() {
    return $this->sail;
  }
}

class Dt_Score extends DBObject {
  protected $team;
  protected $race;

  public $penalty;
  public $score;
  
  public $explanation;

  public function db_type($field) {
    if ($field == 'team')
      return DBME::$TEAM;
    if ($field == 'race')
      return DBME::$RACE;
    return parent::db_type($field);
  }
  public function db_name() { return 'finish'; }
  public function &__get($name) {
    if ($name == 'place')
      return ($this->penalty === null) ? $this->score : $this->penalty;
    return parent::__get($name);
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
    if ($field == 'race_nums') return DBME::$ARRAY;
    if ($field == 'team_division') return DBME::$TEAM_DIVISION;
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
    if ($field == 'team') return DBME::$TEAM;
    return parent::db_type($field);
  }
  protected function db_order() { return array('division'=>true, 'rank'=>true); }

  public function getRP($role = 'skipper') {
    return DBME::getAll(DBME::$RP, new DBBool(array(new DBCond('boat_role', $role),
						    new DBCond('team_division', $this->id))));
  }
}
?>
