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
  protected $creator;
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
    case 'creator':
      require_once('regatta/Account.php');
      return DB::$ACCOUNT;
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
   * Fetches the team with the given ID, or null
   *
   * @param int $id the id of the team
   * @return Team|null if the team exists
   */
  public function getTeam($id) {
    $res = DB::get($this->isSingleHanded() ? DB::$SINGLEHANDED_TEAM : DB::$TEAM, $id);
    if ($res === null || $res->regatta != $this)
      return null;
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

  /**
   * Gets the race object for the race with the given division and
   * number. If the race does not exist, throws an
   * InvalidArgumentException. The race object has properties "id",
   * "division", "number", "boat"
   *
   * @param $div the division of the race
   * @param $num the number of the race within that division
   * @return the race object which matches the description
   * @throws InvalidArgumentException if such a race does not exist
   */
  public function getRace(Division $div, $num) {
    $res = DB::getAll(DB::$RACE, new DBBool(array(new DBCond('regatta', $this->id),
						  new DBCond('division', (string)$div),
						  new DBCond('number', $num))));
    if (count($res) == 0)
      throw new InvalidArgumentException(sprintf("No race %s%s in regatta %s", $num, $div, $this->id));
    $r = $res[0];
    unset($res);
    return $r;
  }

  /**
   * Returns the race that is part of this regatta and has the ID
   *
   * @param String $id the ID
   * @return Race|null the race if it exists
   */
  public function getRaceById($id) {
    $r = DB::get(DB::$RACE, $id);
    if ($r === null || $r->regatta != $this->id)
      return null;
    return $r;
  }

  /**
   * Return the total number of races participating, for efficiency
   * purposes
   *
   * @return int the number of races
   */
  public function getRacesCount() {
    if ($this->total_races !== null)
      return $this->total_races;
    $this->total_races = count(DB::getAll(DB::$RACE, new DBCond('regatta', $this->id)));
    return $this->total_races;
  }

  // ------------------------------------------------------------
  // Races and boats
  // ------------------------------------------------------------

  /**
   * Returns an array of race objects within the specified division
   * ordered by the race number. If no division specified, returns all
   * the races in the regatta ordered by division, then number.
   *
   * @param $div the division whose races to extract
   * @return list of races in that division (could be empty)
   */
  public function getRaces(Division $div = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id)));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::$RACE, $cond);
  }

  /**
   * Returns the unique boats being used in this regatta. Note that
   * this is much faster than going through all the races manually and
   * keeping track of the boats.
   *
   * @param Division $div the division whose boats to retrieve.
   * If null, return all of them instead.
   * @return Array<Boat> the boats
   */
  public function getBoats(Division $div = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id)));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::$BOAT, new DBCondIn('id', DB::prepGetAll(DB::$RACE, $cond, array('boat'))));
  }

  /**
   * Returns a sorted list of the race numbers common to all the
   * divisions
   *
   * @param Array:Division the list of divisions
   * @return Array:int the common race numbers
   */
  public function getCombinedRaces(Array $divs = null) {
    $set = array();
    if ($divs == null)
      $divs = $this->getDivisions();
    foreach ($this->getDivisions() as $div) {
      foreach ($this->getRaces($div) as $race)
	$set[$race->number] = $race->number;
    }
    usort($set);
    return array_values($set);
  }

  /**
   * Adds the specified race to this regatta. Unlike in previous
   * versions, the user needs to specify the race number. As a result,
   * if the race already exists, the code will attempt to update the
   * race instead of adding a new one.
   *
   * @param Race $race the race to register with this regatta
   */
  public function setRace(Race $race) {
    $cur = $this->getRace($race->division, $race->number);
    if ($cur !== null)
      $race->id = $cur->id;
    else
      $this->total_races++;
    DB::set($cur);
  }

  /**
   * Removes the specific race from this regatta. Note that in this
   * version, the race is removed by regatta, division, number
   * identifier instead of by ID. This means that it is not necessary
   * to first serialize the race object in order to remove it from the
   * database.
   *
   * It is the client code's responsibility to make sure that there
   * aren't any empty race numbers in the middle of a division, as
   * this could have less than humorous results in the rest of the
   * application.
   *
   * @param Race $race the race to remove
   */
  public function removeRace(Race $race) {
    DB::removeAll(DB::$RACE, new DBBool(array(new DBCond('regatta', $this->id),
					      new DBCond('division', (string)$race->division),
					      new DBCond('number', $race->number))));
  }

  /**
   * Removes all the races from the given division
   *
   * @param Division $div the division whose races to remove
   */
  public function removeDivision(Division $div) {
    DB::removeAll(DB::$RACE, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('division', (string)$div))));
  }

  /**
   * Returns a list of races in the given division which are unscored
   *
   * @param Division $div the division. If null, return all unscored races
   * @return Array<Race> a list of races
   */
  public function getUnscoredRaces(Division $div = null) {
    DB::$RACE->db_set_order(array('number'=>true, 'division'=>true));
    
    $cond = new DBBool(array(new DBCond('regatta', $this->id),
			     new DBCondIn('id', DB::prepGetAll(DB::$FINISH, null, array('race')), DBCondIn::NOT_IN)));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    $res = DB::getAll(DB::$RACE, $cond);
    
    DB::$RACE->db_set_order();
    return $res;
  }

  /**
   * Returns a list of the unscored race numbers common to all the
   * divisions passed in the parameter
   *
   * @param Array<div> $divs a list of divisions
   * @return a list of race numbers
   */
  public function getUnscoredRaceNumbers(Array $divisions) {
    $nums = array();
    foreach ($divisions as $div) {
      foreach ($this->getUnscoredRaces($div) as $race)
	$nums[$race->number] = $race->number;
    }
    usort($nums);
    return $nums;
  }

  /**
   * Get list of scored races in the specified division
   *
   * @param Division $div the division. If null, return all scored races
   * @return Array<Race> a list of races
   */
  public function getScoredRaces(Division $div = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id),
			     new DBCondIn('id', DB::prepGetAll(DB::$FINISH, null, array('race')))));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::$RACE, $cond);
  }

  /**
   * Returns a list of the race numbers scored across all divisions
   *
   * @param Array<Division> the divisions
   * @return Array<int> the race numbers
   */
  public function getCombinedScoredRaces(Array $divs = null) {
    if ($divs == null)
      $divs = $this->getDivisions();
    $nums = array();
    foreach ($divs as $div) {
      foreach ($this->getScoredRaces($div) as $race)
	$nums[$race->number] = $race->number;
    }
    usort($nums);
    return $nums;
  }

  /**
   * Returns a list of unscored race numbers common to all divisions
   *
   * @param  Array<Division> the divisions, or all if null
   * @return Array<int> the race numbers
   */
  public function getCombinedUnscoredRaces(Array $divs = null) {
    if ($divs == null)
      $divs = $this->getDivisions();
    $nums = array();
    foreach ($divs as $div) {
      foreach ($this->getUnscoredRaces($div) as $race)
	$nums[$race->number] = $race->number;
    }
    usort($nums);
    return $nums;
  }

  /**
   * Fetches the race that was last scored in the regatta, or the
   * specific division if one is provided. This method will look at
   * the timestamp of the first finish in each race to determine which
   * is the latest to be scored.
   *
   * @param Division $div (optional) only look in this division
   * @return Race|null the race or null if none yet scored
   */
  public function getLastScoredRace(Division $div = null) {
    // Get the race (id) from the latest finish
    DB::$FINISH->db_set_order(array('entered'=>false));
    $q = DB::prepGetAll(DB::$FINISH,
			new DBCondIn('race', DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->id), array('id'))),
			array('race'));
    $q->limit(1);
    $res = DB::query($q);
    if ($res->num_rows == 0)
      $r = null;
    else {
      $res = $res->fetch_object();
      $r = DB::get(DB::$RACE, $res->race);
    }
    unset($res);
    DB::$FINISH->db_set_order();
    return $r;
  }

  // ------------------------------------------------------------
  // Finishes
  // ------------------------------------------------------------

  /**
   * @var Array attempt to cache finishes, index is 'race-team_id'
   */
  private $finishes = array();
  /**
   * @var boolean quick! Do we have finishes?
   */
  private $has_finishes = null;

  /**
   * Creates a new finish for the given race and team, and returns the
   * object. Note that this clobbers the existing finish, if any,
   * although the information is not saved in the database until it is
   * saved with 'setFinishes'
   *
   * @param Race $race the race
   * @param Team $team the team
   * @return Finish
   */
  public function createFinish(Race $race, Team $team) {
    $id = sprintf('%s-%d', (string)$race, $team->id);
    $fin = new Finish(null, $race, $team);
    $this->finishes[$id] = $fin;
    $this->has_finishes = true;
    return $fin;
  }

  /**
   * Returns the finish for the given race and team, or null
   *
   * @param $race the race object
   * @param $team the team object
   * @return the finish object
   */
  public function getFinish(Race $race, Team $team) {
    $id = (string)$race . '-' . $team->id;
    if (isset($this->finishes[$id])) {
      return $this->finishes[$id];
    }
    $res = DB::getAll(DB::$FINISH, new DBBool(array(new DBCond('race', $race), new DBCond('team', $team))));
    if (count($res) == 0)
      $r = null;
    else {
      $r = $res[0];
      $this->finishes[$id] = $r;
    }
    unset($res);
    return $r;
  }

  /**
   * Returns an array of finish objects for the given race ordered by
   * timestamp.
   *
   * @param $race whose finishes to get.
   * @return a list of ordered finishes in the race. If null, return
   * all the finishes ordered by race, and timestamp.
   *
   */
  public function getFinishes(Race $race) {
    return DB::getAll(DB::$FINISH, new DBCond('race', $race));
  }

  /**
   * Returns all the finishes which have been "penalized" in one way
   * or another. That is, they have either a penalty or a breakdown
   *
   * @return Array:Finish the list of finishes, regardless of race
   */
  public function getPenalizedFinishes() {
    return DB::getAll(DB::$FINISH,
		      new DBBool(array(new DBCondIn('race', DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->id), array('id'))),
				       new DBCond('penalty', null, DBCond::NE))));
  }

  /**
   * Returns a list of those finishes in the given division which are
   * set to be scored as average of the other finishes in the same
   * division. Confused? Read the procedural rules for breakdowns, etc.
   *
   * @param Division $div the division whose average-scored finishes
   * to fetch
   *
   * @return Array:Finish the finishes
   */
  public function getAverageFinishes(Division $div) {
    return DB::getAll(DB::$FINISH,
		      new DBBool(array(new DBCondIn('race',
						    DB::prepGetAll(DB::$RACE,
								   new DBBool(array(new DBCond('regatta', $this->id),
										    new DBCond('division', (string)$div))),
								   array('id'))),
				       new DBCondIn('penalty', array(Breakdown::BKD, Breakdown::RDG, Breakdown::BYE)),
				       new DBCond('amount', 0, DBCond::LE))));
  }

  /**
   * Like hasFinishes, but checks specifically for penalties
   *
   * @param Race $race optional, if given, returns status for only
   * that race
   * @return boolean
   * @see hasFinishes
   */
  public function hasPenalties(Race $race = null) {
    $cond = new DBBool(array(new DBCond('penalty', null, DBCond::NE)));
    if ($race === null)
      $cond->add(new DBCondIn('race', DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->id), array('id'))));
    else
      $cond->add(new DBCond('race', $race));
    return DB::getAll(DB::$FINISH, $cond);
  }

  /**
   * Are there finishes for this regatta?
   *
   * @param Race $race optional, if given, returns status for just
   * that race. Otherwise, the whole regatta
   * @return boolean
   */
  public function hasFinishes(Race $race = null) {
    if ($race === null && $this->has_finishes !== null)
      return $this->has_finishes;

    if ($race === null)
      $cond = new DBCondIn('race', DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->id), array('id')));
    else
      $cond = new DBCond('race', $race);
    $cnt = count(DB::getAll(DB::$FINISH, $cond));
    if ($race === null)
      $this->has_finishes = $cnt;
    return $cnt;
  }

  /**
   * Commits the finishes given to the database. Note that the
   * finishes must have been registered ahead of time with the
   * regatta, either through getFinish or createFinish.
   *
   * @param Race $race the race for which to enter finishes
   * @param Array:Finish $finishes the list of finishes
   */
  public function setFinishes(Race $race) {
    $this->commitFinishes($this->getFinishes($race));
  }

  /**
   * Commits the given finishes to the database.
   *
   * @param Array:Finish $finishes the finishes to commit
   * @see setFinishes
   */
  public function commitFinishes(Array $finishes) {
    foreach ($this->finishes as $finish)
      DB::set($finish);
  }

  /**
   * Deletes the finishes in the given race, without scoring the
   * regatta.
   *
   * @param Race $race the race whose finishes to drop
   */
  protected function deleteFinishes(Race $race) {
    DB::removeAll(DB::$RACE, new DBCond('race', $race));
    $this->has_finishes = null;
  }

  /**
   * Drops all the finishes registered with the given race and
   * rescores the regatta. Respects the regatta scoring option.
   *
   * @param Race $race the race whose finishes to drop
   */
  public function dropFinishes(Race $race) {
    if ($this->get(Regatta::SCORING) == Regatta::SCORING_STANDARD)
      $this->deleteFinishes($race);
    else {
      foreach ($this->getDivisions() as $div)
	$this->deleteFinishes($this->getRace($div, $race->number));
    }
    $this->runScore($race);
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