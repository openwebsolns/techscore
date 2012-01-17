<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

require_once('regatta/DB.php');

/**
 * Encapsulates a (flat) regatta object. Note that comments are
 * suppressed due to speed considerations.
 *
 * @author Dayan Paez
 * @version 2009-11-30
 */
class RegattaSummary extends DBObject {

  // Keys for data
  const NAME       = "name";
  const NICK_NAME  = "nick";
  const START_TIME = "start_time";
  const END_DATE   = "end_date";
  const DURATION   = "duration";
  const FINALIZED  = "finalized";
  const TYPE       = "type";
  const VENUE      = "venue";
  const SCORING    = "scoring";
  const SEASON     = "season";
  const PARTICIPANT = "participant";

  /**
   * Standard scoring
   */
  const SCORING_STANDARD = "standard";

  /**
   * Combined scoring
   */   
  const SCORING_COMBINED = "combined";

  /**
   * Women's regatta
   */
  const PARTICIPANT_WOMEN = "women";
  
  /**
   * Coed regatta (default)
   */
  const PARTICIPANT_COED = "coed";

  /**
   * Gets an assoc. array of the possible regatta types
   *
   * @return Array a dict of regatta types
   */
  public static function getTypes() {
    return array(RegattaSummary::TYPE_CHAMPIONSHIP=>"National Championship",
		 RegattaSummary::TYPE_CONF_CHAMPIONSHIP=>"Conference Championship",
		 RegattaSummary::TYPE_INTERSECTIONAL=>"Intersectional",
		 RegattaSummary::TYPE_TWO_CONFERENCE=>"Two-Conference",
		 RegattaSummary::TYPE_CONFERENCE=>"In-Conference",
		 RegattaSummary::TYPE_PROMOTIONAL=>"Promotional",
		 RegattaSummary::TYPE_PERSONAL=>"Personal");
  }
  const TYPE_PERSONAL = 'personal';
  const TYPE_CONFERENCE = 'conference';
  const TYPE_CHAMPIONSHIP = 'championship';
  const TYPE_INTERSECTIONAL = 'intersectional';
  const TYPE_CONF_CHAMPIONSHIP = 'conference-championship';
  const TYPE_TWO_CONFERENCE = 'two-conference';
  const TYPE_PROMOTIONAL = 'promotional';

  /**
   * Gets an assoc. array of the possible scoring rules
   *
   * @return Array a dict of scoring rules
   */
  public static function getScoringOptions() {
    return array(RegattaSummary::SCORING_STANDARD => "Standard",
		 RegattaSummary::SCORING_COMBINED => "Combined divisions");
  }

  /**
   * Gets an assoc. array of the possible participant values
   *
   * @return Array a dict of scoring rules
   */
  public static function getParticipantOptions() {
    return array(RegattaSummary::PARTICIPANT_COED => "Coed",
		 RegattaSummary::PARTICIPANT_WOMEN => "Women");
  }
  
  // Variables
  public $name;
  public $nick;
  protected $start_time;
  protected $end_date;
  public $type;
  protected $finalized;
  public $participant;

  // Managers
  private $rotation;
  private $rp;
  private $season;

  // ------------------------------------------------------------
  // DBObject stuff
  // ------------------------------------------------------------
  public function db_name() { return 'regatta'; }
  protected function db_order() { return array('start_time'=>false); }
  public function db_type($field) {
    switch ($field) {
    case 'start_time':
    case 'end_date':
    case 'finalized':
      return DB::$NOW;
    default:
      return parent::db_type($field);
    }
  }

  /**
   * Returns the specified property. Suitable for migration.
   *
   * @param Regatta::Const $property one of the class constants
   * @return the specified property
   * @throws InvalidArgumentException if the property is invalid.
   */
  public function get($property) {
    return $this->__get($property);
  }

  public function &__get($name) {
    switch ($name) {
    case 'scorer':
      if ($this->scorer === null) {
	require_once('regatta/ICSAScorer.php');
	$this->scorer = new ICSAScorer();
      }
      return $this->scorer;
    default:
      return parent::__get($name);
    }
  }

  public function getSeason() {
    if ($this->season === null)
      $this->season = Season::forDate($this->__get('start_date'));
    return $this->season;
  }

  /**
   * Commits the specified property. A thin and unnecessary wrapper
   * around DBObject::__set, which will be deprecated.
   *
   * @param Regatta::Const $property one of the class constants
   * @param object $value value whose string representation should be
   * used for the given property
   *
   * @throws InvalidArgumentException if the property is invalid.
   *
   * @version 2011-01-03: if the regatta is (re)activated, then check
   * if the nick name is valid.
   *
   * @deprecated 2012-01-16: Assign properties directly and use
   * DB::set to commit to database
   */
  public function set($property, $value) {
    $this->__set($property, $value);
    DB::set($this);
  }

  // ------------------------------------------------------------
  // Daily summaries
  // ------------------------------------------------------------

  /**
   * Gets the daily summary for the given day
   *
   * @param DateTime $day the day summary to return
   * @return String the summary
   */
  public function getSummary(DateTime $day) {
    $res = DB::getAll(DB::$DAILY_SUMMARY, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('summary_date', $day))));
    $r = (count($res) == 0) ? '' : $res[0]->summary;
    unset($res);
    return $r;
  }

  /**
   * Sets the daily summary for the given day
   *
   * @param DateTime $day
   * @param String $comment
   */
  public function setSummary(DateTime $day, $comment) {
    // Enforce uniqueness
    $res = DB::getAll(DB::$DAILY_SUMMARY, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('summary_date', $day))));
    if (count($res) > 0)
      $cur = $res[0];
    else {
      $cur = new Daily_Summary();
      $cur->regatta = $this->id;
      $cur->summary_date = $day;
    }
    $cur->summary = $comment;
    DB::set($cur);
    unset($res);
  }

  /**
   * @var Array:Division an attempt at caching
   */
  private $divisions = null;
  /**
   * Returns an array of the divisions in this regatta
   *
   * @return list of divisions in this regatta
   */
  public function getDivisions() {
    if ($this->divisions === null) {
      $q = DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->id), array('division'));
      $q->distinct(true);
      $q = DB::query($q);
      $this->divisions = array();
      while ($row = $q->fetch_object()) {
	$this->divisions[$row->division] = Division::get($row->division);
      }
    }
    return array_values($this->divisions);
  }

  /**
   * @var Array $teams an attempt to cache teams
   */
  private $teams = null;
  /**
   * Fetches the team with the given ID, or null
   *
   * @param int $id the id of the team
   * @return Team|null if the team exists
   */
  public function getTeam($id) {
    if ($this->teams !== null)
      return (isset($this->teams[$id])) ? $this->teams[$id] : null;

    $res = DB::get($this->isSingleHanded() ? DB::$SINGLEHANDED_TEAM : DB::$TEAM, $id);
    if ($res === null || $res->regatta != $this)
      return null;

    $this->teams[$team->id] = $team;
    return $team;
  }

  /**
   * Just get the number of teams, which is slightly quicker than
   * serializing all those teams.
   *
   * @return int the fleet size
   */
  public function getFleetSize() {
    return count($this->getTeams());
  }

  /**
   * Gets a list of team objects for this regatta.
   *
   * @param School $school the optional school whose teams to return
   * @return array of team objects
   */
  public function getTeams(School $school = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id)));
    if ($school !== null)
      $cond->add(new DBCond('school', $school));
    return DB::getAll($this->isSingleHanded() ? DB::$SINGLEHANDED_TEAM : DB::$TEAM, $cond);
  }

  /**
   * Adds the given team to this regatta. Updates the given team
   * object to have the correct, databased ID
   *
   * @param Team $team the team to add (only team name and school are
   * needed)
   */
  public function addTeam(Team $team) {
    DB::set($team);
    if ($this->teams !== null)
      $this->teams[$team->id] = $team;
  }

  /**
   * Replaces the given team's school information with the team
   * given. Note that this changes the old team's object's
   * information. The new team does not become part of this
   * regatta.
   *
   * @param Team $old the team to replace
   * @param Team $new the team to replace with
   * @throws InvalidArgumentException if old team is not part of this
   * regatta to begin with!
   */
  public function replaceTeam(Team $old, Team $new) {
    if ($old->regatta->id != $this->id)
      throw new InvalidArgumentException("Team \"$old\" is not part of this regatta.");

    $old->school = $new->school;
    $old->name = $new->name;
    DB::set($old);
  }

  /**
   * Remove the given team from this regatta
   *
   * @param Team $team the team to remove
   */
  public function removeTeam(Team $team) {
    DB::remove($team);
    unset($this->teams[$team->id]);
  }
  
  /**
   * Returns the simple rank of the teams in the database, by
   * totalling their score across the division given (or all
   * divisions). A tiebreaker procedure should be used after that if
   * multiple teams share the same score.
   *
   * @param Array:Division $divs the divisions to use for the ranking
   */
  public function getRanks(Array $divs) {
    $q = DB::createQuery();
    $q->fields(array(new DBField('team'),
		     new DBField('score', 'sum', 'total')), DB::$FINISH->db_name());
    $q->where(new DBCondIn('race',
			   DB::prepGetAll(DB::$RACE,
					  new DBBool(array(new DBCondIn('division', $divs),
							   new DBCond('regatta', $this->id))),
					  array('id'))));
    $q->order_by(array('total'=>true));
    $q = DB::query($q);
    $ranks = array();
    while ($obj = $q->fetch_object())
      $ranks[] = new Rank($this->getTeam($obj->team), $obj->total);
    $q->free();
    return $ranks;
  }

  // ------------------------------------------------------------
  // Comparators
  // ------------------------------------------------------------
  
  /**
   * Compares two regattas based on start_time
   *
   * @param RegattaSummary $r1 a regatta
   * @param RegattaSummary $r2 a regatta
   */
  public static function cmpStart(RegattaSummary $r1, RegattaSummary $r2) {
    if ($r1->start_time < $r2->start_time)
      return -1;
    if ($r1->start_time > $r2->start_time)
      return 1;
    return 0;
  }

  /**
   * Compares two regattas based on start_time, descending
   *
   * @param RegattaSummary $r1 a regatta
   * @param RegattaSummary $r2 a regatta
   */
  public static function cmpStartDesc(RegattaSummary $r1, RegattaSummary $r2) {
    return -1 * self::cmpStart($r1, $r2);
  }
}
DB::$REGATTA_SUMMARY = new RegattaSummary();
?>