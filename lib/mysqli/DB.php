<?php
/**
 * A different way of serializing and deserializing objects using the
 * DBM class created by Dayan Paez.
 *
 * @author Dayan Paez
 * @version 2011-01-22
 * @package mysql
 */

require_once('DBC.php');
require_once('MySQLi_delegate.php');
require_once('MySQLi_Query.php');

/**
 * Provides some more functionality
 *
 */
class DBME extends DBM {
  /**
   * Empty objects to serve as prototypes
   */
  public static $TEAM_DIVISION = null;
  public static $CONFERENCE = null;
  public static $REGATTA = null;
  public static $SEASON = null;
  public static $SCHOOL = null;
  public static $SAILOR = null;
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
    self::$CONFERENCE = new Dt_Conference();
    self::$REGATTA = new Dt_Regatta();
    self::$SEASON = new Dt_Season();
    self::$SCHOOL = new Dt_School();
    self::$SAILOR = new Dt_Sailor();
    self::$SCORE = new Dt_Score();
    self::$VENUE = new Dt_Venue();
    self::$TEAM = new Dt_Team();
    self::$RACE = new Dt_Race();
    self::$SAIL = new Dt_Sail();
    self::$NOW = new DateTime();
    self::$RP = new Dt_Rp();

    DBM::setConnection($con);
  }
}

class Dt_Season extends DBObject {
  public $season;
  protected $start_date;
  protected $end_date;

  public function db_name() { return 'season'; }
  public function db_type($field) {
    switch ($field) {
    case 'start_date':
    case 'end_date':
      return DBME::$NOW;
    default:
      return parent::db_type($field);
    }
  }
  public function db_order() { return 'start_date'; }
  public function db_order_by() { return false; }
  public function db_cache() { return true; }

  public function __toString() {
    switch ($this->season) {
    case 'fall':   $t = 'f'; break;
    case 'winter': $t = 'w'; break;
    case 'spring': $t = 's'; break;
    case 'summer': $t = 'm'; break;
    default: $t = '_';
    }
    return sprintf('%s%s', $t, substr($this->end_date->format('Y'), 2));
  }
  public function fullString() {
    return sprintf('%s %s', ucfirst($this->season), $this->end_date->format('Y'));
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
      return DBME::$VENUE;

    default:
      return parent::db_type($field);
    }
  }
  public function db_cache() { return true; }
  public function db_order() { return 'start_time'; }
  public function db_order_by() { return false; }

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
    $q = DBME::createQuery(MySQLi_Query::DELETE);
    $q->fields(array(), DBME::$TEAM->db_name());
    $q->where(new MyCond('regatta', $this->id));
    DBME::query($q);
  }

  public function getTeams() {
    return DBME::getAll(DBME::$TEAM, new MyCond('regatta', $this->id));
  }

  /**
   * Return the teams ranked in the given division
   *
   * @param String $div the division
   * @return Array:Dt_Team_Division
   */
  public function getRanks($div) {
    $q = DBME::prepGetAll(DBME::$TEAM, new MyCond('regatta', $this->id));
    $q->fields(array('id'), DBME::$TEAM->db_name());

    return DBME::getAll(DBME::$TEAM_DIVISION, new MyBoolean(array(new MyCond('division', $div),
								  new MyCondIn('team', $q))));
  }

  public function getHosts() {
    $list = array();
    foreach (explode(',', $this->hosts) as $id) {
      $sch = DBME::get(DBME::$SCHOOL, $id);
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
    $q = DBME::prepGetAll($p);
    $q->fields(array('race'), $p->db_name());
    $q->distinct(true);

    return DBME::getAll(DBME::$RACE, new MyBoolean(array(new MyCond('regatta', $this->id),
							 new MyCond('division', $division),
							 new MyCondIn('id', $q))));
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

    $r = DBME::getAll(DBME::$SCORE, new MyBoolean(array(new MyCond('race', $race->id),
							new MyCond('team', $team->id))));
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
    return DBME::getAll(DBME::$RACE, new MyBoolean(array(new MyCond('regatta', $this->id),
							 new MyCond('division', $division))));
  }
}

class Dt_Venue extends DBObject {
  public $name;
  public $address;
  public $city;
  public $state;
  public $zipcode;

  public function db_name() { return 'venue'; }
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
      return DBME::$SCHOOL;
    default:
      return parent::db_type($field);
    }
  }
  public function db_order() { return 'rank'; }
  public function db_cache() { return true; }

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
    $r = DBME::getAll(DBME::$TEAM_DIVISION, new MyBoolean(array(new MyCond('team', $this->id),
								new MyCond('division', $division))));
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
   * @param String $div the division
   * @param String $role 'skipper', or 'crew'
   * @return Array:Dt_RP the rp for that team
   */
  public function getRP($div, $role) {
    $rank = $this->getRank($div);
    if ($rank === null)
      return array();
    return DBME::getAll(DBME::$RP, new MyBoolean(array(new MyCond('boat_role', $role),
						       new MyCond('team_division', $rank->id))));
  }

  /**
   * Removes all RP entries for this team from the database
   *
   * @param String $div the division whose RP info to reset
   */
  public function resetRP($div) {
    $q = DBME::prepGetAll(DBME::$TEAM_DIVISION, new MyBoolean(array(new MyCond('team', $this->id),
								    new MyCond('division', $div))));
    $q->fields(array('id'), DBME::$TEAM_DIVISION->db_name());
    foreach (DBME::getAll(DBME::$RP, new MyCondIn('team_division', $q)) as $rp)
      DBME::remove($rp);
  }
}

class Dt_Race extends DBObject {
  protected $regatta;
  public $division;
  public $number;

  public function db_name() { return 'race'; }
  public function db_cache() { return true; }
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
    return DBME::getAll(DBME::$SAIL, new MyCond('race', $this->id));
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
  public function db_order() { return 'sail'; }
  public function __toString() {
    return $this->sail;
  }
}

class Dt_School extends DBObject {
  public $name;
  public $nick_name;
  public $city;
  public $state;
  protected $conference;

  public function db_name() { return 'school'; }
  public function db_cache() { return true; }
  public function db_type($field) {
    if ($field == 'conference')
      return DBME::$CONFERENCE;
    return parent::db_type($field);
  }

  public function __toString() {
    return $this->name;
  }
}

class Dt_Conference extends DBObject {
  public $name;
  public function db_name() { return 'conference'; }
  public function db_cache() { return true; }
  public function __toString() { return $this->id; }
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
  public function __get($name) {
    if ($name == 'place')
      return ($this->penalty === null) ? $this->score : $this->penalty;
    return parent::__get($name);
  }
}

class Dt_Rp extends DBObject {
  protected $team_division;
  protected $race_nums;
  protected $sailor;
  public $boat_role;

  public function db_type($field) {
    if ($field == 'sailor') return DBME::$SAILOR;
    if ($field == 'race_nums') return DBME::$ARRAY;
    if ($field == 'team_division') return DBME::$TEAM_DIVISION;
    return parent::db_type($field);
  }
  public function db_order() { return 'race_nums'; }
}

class Dt_Sailor extends DBObject {
  public $icsa_id;
  protected $school;
  public $last_name;
  public $first_name;
  public $year;
  public $role;

  public function db_name() { return 'sailor'; }
  public function db_cache() { return true; }
  public function db_type($field) {
    if ($field == 'school')
      return DBME::$SCHOOL;
    return parent::db_type($field);
  }
  public function __toString() {
    $suffix = ($this->icsa_id === null) ? ' *' : '';
    return sprintf('%s %s \'%s%s',
		   $this->first_name,
		   $this->last_name,
		   substr($this->year, 2),
		   $suffix);
  }
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
  public function db_order() { return 'division, rank'; }
}
?>
