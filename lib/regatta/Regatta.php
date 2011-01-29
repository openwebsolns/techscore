<?php
/**
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */
require_once('conf.php');

/**
 * Class for regatta objects. Each object is responsible for
 * communicating with the database and retrieving all sorts of
 * pertinent informations. Only one global connection to the database
 * is necessary and shared by all regatta objects.
 *
 * 2010-02-16: Created TempRegatta which extends this class.
 *
 * 2010-03-07: Provided for combined divisions
 *
 * 2011-01-03: Regatta nick names offer a special challenge in that
 * they (a) need to be unique per season (as this is how they are
 * identified in the public site), and (b) only for those that are
 * published. As such, special care must be taken for regattas which
 * change their status either by being deactivated or possibly
 * re-activated. Of course, although this note appears here, the
 * orchestration needs to be done elsewhere. For this moment, this
 * class will now throw an error when attempting to set a name for
 * which a nick-name would no longer be unique among active regattas
 * in that season!
 *
 * @author Dayan Paez
 * @created 2009-10-01
 */
class Regatta implements RaceListener, FinishListener {

  private $id;
  private $con; // MySQL connection object
  private $scorer;

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

  /**
   * Standard scoring
   */
  const SCORING_STANDARD = "standard";

  /**
   * Combined scoring
   */   
  const SCORING_COMBINED = "combined";

  // Properties
  private $properties = null;

  // Managers
  private $rotation;
  private $rp;

  /**
   * Sends the query to the database and handles errors. Returns the
   * resultant mysqli_result object
   */
  public function query($string) {
    return Preferences::query($string);
  }

  /**
   * Creates a new regatta object using the specified connection
   *
   * @param int $id the id of the regatta
   *
   * @throws InvalidArgumentException if not a valid regatta ID
   */
  public function __construct($id) {
    if (!is_numeric($id))
      throw new InvalidArgumentException(sprintf("Illegal regatta id value (%s).", $id));

    $this->id  = (int)$id;
    $this->scorer = new ICSAScorer();

    // Update the properties
    $q = sprintf('select regatta.id, regatta.name, regatta.nick, ' .
		 'regatta.start_time, regatta.end_date, regatta.venue, ' .
		 'regatta.type, regatta.finalized, regatta.scoring ' .
		 'from regatta ' .
		 'where regatta.id = "%s"',
		 $this->id);
    $result = $this->query($q);
    if ($result->num_rows > 0) {
      $this->properties = $result->fetch_assoc();

      $start = new DateTime($this->properties[Regatta::START_TIME]);
      $end   = new DateTime($this->properties[Regatta::END_DATE]);
      date_time_set($end, 0, 0, 0);

      $this->properties[Regatta::START_TIME] = $start;
      $this->properties[Regatta::END_DATE]   = $end;

      // Calculate duration
      $duration = 1 + (date_format($end, "U") -
		   date_format($this->getDay($start), "U")) /
	(3600 * 24);

      $this->properties[Regatta::DURATION] = $duration;

      // Venue and Season shall not be serialized until they are requested
      // Finalized
      if (($p = $this->properties[Regatta::FINALIZED]) !== null)
	$this->properties[Regatta::FINALIZED] = new DateTime($p);
    }
    else {
      throw new InvalidArgumentException("Invalid ID for regatta.");
    }

    // Managers
    $this->rotation = new Rotation($this);
    $this->rp       = new RpManager($this);
  }

  /**
   * Returns the specified property.
   *
   * @param Regatta::Const $property one of the class constants
   * @return the specified property
   * @throws InvalidArgumentException if the property is invalid.
   */
  public function get($property) {
    if (!array_key_exists($property, $this->properties)) {
      $m = "Property $property not supported in regattas.";
      throw new InvalidArgumentException($m);
    }
    if ($property == Regatta::VENUE) {
      if ($this->property[$property] !== null &&
	  !($this->property[$property] instanceof Venue))
	$this->properties[$property] = Preferences::getVenue($this->properties[$property]);
    }
    elseif ($property == Regatta::SEASON) {
      if ($this->property[$property] !== null &&
	  !($this->property[$property] instanceof Season))
	$this->properties[$property] = new Season($this->properties[$property]);
    }
    return $this->properties[$property];
  }

  public function __get($name) {
    if ($name == "scorer") {
      return $this->scorer;
    }
    throw new InvalidArgumentException("No such Regatta property $name.");
  }

  /**
   * Commits the specified property
   *
   * @param Regatta::Const $property one of the class constants
   * @param object $value value whose string representation should be
   * used for the given property
   *
   * @throws InvalidArgumentException if the property is invalid.
   *
   * @version 2011-01-03: if the regatta is (re)activated, then check
   * if the nick name is valid.
   */
  public function set($property, $value) {
    if (!array_key_exists($property, $this->properties)) {
      $m = "Property $property not supported in regattas.";
      throw new InvalidArgumentException($m);
    }
    if ($property == Regatta::SEASON)
      throw new InvalidArgumentException("Cannot set season directly. Set START_TIME instead.");
    if ($value == null)
      $strvalue = 'NULL';
    elseif (in_array($property, array(Regatta::START_TIME, Regatta::END_DATE, Regatta::FINALIZED))) {
      if (!($value instanceof DateTime)) {
	$m = sprintf("Property %s must be a valid DateTime object.", $property);
	throw new InvalidArgumentException($m);
      }
      $strvalue = sprintf('"%s"', $value->format("Y-m-d H:i:s"));
    }
    elseif ($property == Regatta::TYPE) {
      if (!in_array($value, array_keys(Preferences::getRegattaTypeAssoc())))
	throw new InvalidArgumentException("Invalid regatta type \"$value\".");
      // re-create the nick name, and let that method determine if it
      // is valid (this would throw an exception otherwise)
      if ($value != Preferences::TYPE_PERSONAL) {
	$this->set(Regatta::NICK_NAME, $this->createNick());
	// If it used to be personal, we need to queue for public site
	// as well, since it is now publicly viewable
	if ($this->get(Regatta::TYPE) == Preferences::TYPE_PERSONAL)
	  UpdateManager::queueRequest($this, UpdateRequest::ACTIVITY_SCORE);
      }
      else {
	// Queue public "score" update: this will result in deletion
	UpdateManager::queueRequest($this, UpdateRequest::ACTIVITY_SCORE);
      }

      $strvalue = sprintf('"%s"', $value);
    }
    else
      $strvalue = sprintf('"%s"', $value);

    $this->properties[$property] = $value;
    $q = sprintf('update regatta set %s = %s where id = "%s"',
		 $property, $strvalue, $this->id);
    $this->query($q);
  }

  //
  // Daily summaries
  //

  /**
   * Gets the daily summary for the given day
   *
   * @param DateTime $day the day summary to return
   * @return String the summary
   */
  public function getSummary(DateTime $day) {
    $q = sprintf('select summary from daily_summary ' .
		 'where regatta = "%s" and summary_date = "%s"',
		 $this->id, $day->format("Y-m-d"));
    $res = $this->query($q);
    if ($res->num_rows == 0)
      return '';
    return stripslashes($res->fetch_object()->summary);
  }

  /**
   * Sets the daily summary for the given day
   *
   * @param DateTime $day
   * @param String $comment
   */
  public function setSummary(DateTime $day, $comment) {
    $q = sprintf('replace into daily_summary (regatta, summary_date, summary) ' .
		 'values ("%s", "%s", "%s")',
		 $this->id, $day->format('Y-m-d'), (string)$comment);
    $this->query($q);
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
    if ($this->divisions !== null)
      return $this->divisions;
    
    $q = sprintf('select distinct division from race ' .
		 'where regatta = "%s" ' . 
		 'order by division',
		 $this->id);
    $q = $this->query($q);
    $this->divisions = array();
    while ($row = $q->fetch_object()) {
      $this->divisions[] = Division::get($row->division);
    }
    return $this->divisions;
  }

  /**
   * Gets a list of team objects for this regatta.
   *
   * @param School $school the optional school whose teams to return
   * @return array of team objects
   */
  public function getTeams(School $school = null) {
    $q = sprintf('select team.id, team.name, team.school ' .
		 'from team where regatta = "%s" %s order by school, id',
		 $this->id,
		 ($school === null) ? '' : sprintf('and school = "%s"', $school->id));
    $q = $this->query($q);

    $teams = array();
    if ($this->isSingleHanded()) {
      while ($team = $q->fetch_object("SinglehandedTeam")) {
	$teams[] = $team;
	$team->setRpManager($this->rp);
      }
    }
    else {
      while ($team = $q->fetch_object("Team")) {
	$teams[] = $team;
      }
    }
    return $teams;
  }

  /**
   * Adds the given team to this regatta. Updates the given team
   * object to have the correct, databased ID
   *
   * @param Team $team the team to add (only team name and school are
   * needed)
   */
  public function addTeam(Team $team) {
    $q = sprintf('insert into team (regatta, school, name) ' .
		 'values ("%s", "%s", "%s")',
		 $this->id, $team->school->id, $team->name);
    $this->query($q);
    $res = $this->query('select last_insert_id() as id');
    $team->id = $res->fetch_object()->id;
  }

  /**
   * Remove the given team from this regatta
   *
   * @param Team $team the team to remove
   */
  public function removeTeam(Team $team) {
    $q = sprintf('delete from team where id = "%s" and regatta = "%s"', $team->id, $this->id);
    $this->query($q);
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
    $q = sprintf('select %s from %s ' .
		 'where (regatta, division, number) = ' .
		 '      ("%s",    "%s",     "%d") limit 1',
		 Race::FIELDS, Race::TABLES,
		 $this->id, $div, $num);
    $q = $this->query($q);
    if ($q->num_rows == 0) {
      $m = sprintf("No race %s%s in regatta %s", $num, $div, $this->id);
      throw new InvalidArgumentException($m);
    }
    $race = $q->fetch_object("Race");
    $race->addListener($this);
    return $race;
  }

  /**
   * @var Array indexed array of races. An attempt at efficiency through caching
   */
  private $races = array();
  /**
   * Returns an array of race objects within the specified division
   * ordered by the race number. If no division specified, returns all
   * the races in the regatta ordered by division, then number.
   *
   * @param $div the division whose races to extract
   * @return list of races in that division (could be empty)
   */
  public function getRaces(Division $div = null) {
    if ($div == null) {
      $list = array();
      foreach ($this->getDivisions() as $div)
	$list = array_merge($list, $this->getRaces($div));
      return $list;
    }
    // cache?
    $divs = (string)$div;
    if (isset($this->races[$divs]))
      return $this->races[$divs];

    $q = sprintf('select %s from %s ' .
		 'where (regatta, division) = ' .
		 '      ("%s",    "%s") order by number',
		 Race::FIELDS, Race::TABLES,
		 $this->id, $divs);
    $q = $this->query($q);
    $this->races[$divs] = array();

    while ($race = $q->fetch_object("Race")) {
      $this->races[$divs][] = $race;
      $race->addListener($this);
    }
    return $this->races[$divs];
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
    if ($div === null) {
      $list = array();
      foreach ($this->getDivisions() as $div) {
	$list = array_merge($list, $this->getBoats($div));
      }
      return array_unique($list);
    }

    $q = sprintf('select distinct %s from %s ' .
		 'where id in (select boat from race where regatta = %d and division = "%s")',
		 Boat::FIELDS, Boat::TABLES, $this->id, $div);
    $r = $this->query($q);
    $list = array();
    while ($obj = $r->fetch_object("Boat"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Returns a sorted list of the race numbers common to all the
   * divisions
   *
   * @param Array<Division> the list of divisions
   * @return Array<int> the common race numbers
   */
  public function getCombinedRaces(Array $divs = null) {
    $nums = null;
    if ($divs == null)
      $divs = $this->getDivisions();
    foreach ($this->getDivisions() as $div) {
      $set = array();
      foreach ($this->getRaces($div) as $race)
	$set[] = $race->number;

      $nums = ($nums == null) ? $set : array_intersect($nums, $set);
    }
    return $nums;
  }

  /**
   * Adds the specified race to this regatta. This operation always
   * results in new races being created. The 'id' and 'number'
   * property of the Race object is ignored.
   *
   * @param Race $race the new race to register with this regatta
   */
  public function addRace(Race $race) {
    $q = sprintf('insert into race (regatta, division, boat) ' .
		 'values ("%s", "%s", "%s")',
		 $this->id, $race->division, $race->boat->id);
    $this->query($q);
  }

  /**
   * Removes the specific race from this regatta. Note that the race
   * is removed by ID, and due to the nature of race objects, that may
   * mean that other races' numbers will be affected as a result. In
   * general, it is a good idea to remove races from the end first.
   *
   * @param Race $race the race to remove
   */
  public function removeRace(Race $race) {
    $this->query(sprintf('delete from race where id = %d', $race->id));
    if (isset($this->races[(string)$race->division]))
      unset($this->races[(string)$race->division]);
  }

  /**
   * Removes all the races from the given division
   *
   * @param Division $div the division whose races to remove
   */
  public function removeDivision(Division $div) {
    $q = sprintf('delete from race where (regatta, division) = ("%s", "%s")',
		 $this->id, $div);
    $this->query($q);
    $this->divisions = null;
  }

  /**
   * Returns a list of races in the given division which are unscored
   *
   * @param Division $div the division. If null, return all unscored races
   * @return Array<Race> a list of races
   */
  public function getUnscoredRaces(Division $div = null) {
    if ($div == null) {
      $list = array();
      foreach ($this->getDivisions() as $div) {
	$next = $this->getUnscoredRaces($div);
	$list = array_merge($list, $next);
      }
      // sort by number, then division
      usort($list, "Race::compareNumber");
      return $list;
    }
    
    $q = sprintf('select %s from %s ' .
		 'where race.division = "%s" ' .
		 '  and race.regatta = "%s" ' .
		 '  and race.id not in ' .
		 '  (select race from finish) ' .
		 'order by number',
		 Race::FIELDS, Race::TABLES, $div, $this->id);
    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object("Race")) {
      $list[] = $obj;
      $obj->addListener($this);
    }
    return $list;
  }

  /**
   * Get list of scored races in the specified division
   *
   * @param Division $div the division. If null, return all scored races
   * @return Array<Race> a list of races
   */
  public function getScoredRaces(Division $div = null) {
    if ($div == null) {
      $list = array();
      foreach ($this->getDivisions() as $div)
	$list = array_merge($list, $this->getScoredRaces($div));
      return $list;
    }
    
    $q = sprintf('select %s from %s ' .
		 'where race.division = "%s" ' .
		 '  and race.regatta = "%s" ' .
		 '  and race.id in ' .
		 '  (select distinct race from finish) ' .
		 'order by number',
		 Race::FIELDS, Race::TABLES, $div, $this->id);
    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object("Race")) {
      $list[] = $obj;
      $obj->addListener($this);
    }
    return $list;
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
    $nums = null;
    foreach ($divs as $div) {
      $set = array();
      foreach ($this->getScoredRaces($div) as $race)
	$set[] = $race->number;

      $nums = ($nums == null) ? $set : array_intersect($nums, $set);
    }
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
    $nums = null;
    foreach ($divs as $div) {
      $set = array();
      foreach ($this->getUnscoredRaces($div) as $race)
	$set[] = $race->number;

      $nums = ($nums == null) ? $set : array_intersect($nums, $set);
    }
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
    $w = ($div === null) ? "" : sprintf('and division = "%s"', $div);
    $q = sprintf('select race.id from race inner join (select race, min(entered) as entered from finish group by race) as f1 on race.id = f1.race where race.regatta = %d %s order by entered desc limit 1',
		 $this->id, $w);
    $r = $this->query($q);
    if ($r->num_rows == 0)
      return null;
    $r = $r->fetch_object();
    $q = sprintf('select %s from %s where race.id = %d limit 1', Race::FIELDS, Race::TABLES, $r->id);
    $r = $this->query($q);
    $r = $r->fetch_object("Race");
    $r->addListener($this);
    return $r;
  }


  /**
   * Returns the finish for the given race and team, or null
   *
   * @param $race the race object
   * @param $team the team object
   * @return the finish object
   */
  public function getFinish(Race $race, Team $team) {
    $q = sprintf('select finish.id, finish.race, finish.team, finish.entered, ' .
		 'finish.score, finish.place, finish.explanation, ' .
		 'handicap.type as handicap, handicap.amount as h_amt, handicap.comments as h_com, ' .
		 'penalty.type  as penalty,  penalty.comments as p_com ' .
		 'from finish ' .
		 'left join handicap on (finish.id = handicap.finish) ' .
		 'left join penalty  on (finish.id = penalty.finish) ' .
		 'where (race, team) = ("%s", "%s")',
		 $race->id, $team->id);
    $q = $this->query($q);
    if ($q->num_rows == 0)
      return null;
    
    $fin = $q->fetch_object();
    $finish = new Finish($fin->id, $race, $team);
    $finish->entered = new DateTime($fin->entered);
      
    $penalty = null;
    if ($fin->handicap != null) {
      $penalty = new Breakdown($fin->handicap, $fin->h_amt, $fin->h_com);
    }
    if ($fin->penalty != null) {
      $penalty = new Penalty($fin->penalty, $fin->p_com);
    }
    $finish->penalty   = $penalty;
    $finish->score = new Score($fin->place, $fin->score, $fin->explanation);

    $finish->addListener($this);
    return $finish;
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
  public function getFinishes(Race $race = null) {
    if ($race == null) {
      $list = array();
      foreach ($this->getRaces() as $race)
	$list = array_merge($list, $this->getFinishes($race));
      return $list;
    }
    
    $finishes = array();
    foreach ($this->getTeams() as $team) {
      if (($f = $this->getFinish($race, $team)) !== null)
	$finishes[] = $f;
    }
    
    return $finishes;
  }

  /**
   * Adds the finishes to the regatta, then checks for completeness
   *
   * @param Array<Finish> $finishes the list of finishes
   */
  public function setFinishes(Array $finishes) {
    $fmt = 'replace into finish (race, team, entered) ' .
      'values ("%s", "%s", "%s")';
    foreach ($finishes as $finish) {
      $q = sprintf($fmt,
		   $finish->race->id,
		   $finish->team->id,
		   $finish->entered->format("Y-m-d H:i:s"));
      $this->query($q);
    }

    $this->doScore();
  }

  /**
   * Deletes the finishes in the given race, without scoring the
   * regatta.
   *
   * @param Race $race the race whose finishes to drop
   */
  protected function deleteFinishes(Race $race) {
    $q = sprintf('delete from finish where race = "%s"', $race->id);
    $this->query($q);
  }

  /**
   * Drops all the finishes registered with the given race and
   * rescores the regatta
   *
   * @param Race $race the race whose finishes to drop
   */
  public function dropFinishes(Race $race) {
    $this->deleteFinishes($race);
    $this->doScore();
  }

  /**
   * Set team penalty
   *
   * @param TeamPenalty $penalty the penalty to register
   */
  public function setTeamPenalty(TeamPenalty $penalty) {
    $q = sprintf('replace into %s values ("%s", "%s", "%s", "%s")',
		 TeamPenalty::TABLES,
		 $penalty->team->id,
		 $penalty->division,
		 $penalty->type,
		 $penalty->comments);
    $this->query($q);
  }

  /**
   * Drops the team penalty for the given team in the given division
   *
   * @param Team $team the team whose penalty to drop
   * @param Division $div the division to drop
   */
  public function dropTeamPenalty(Team $team, Division $div) {
    $q = sprintf('delete from %s where (team, division) = ("%s", "%s")',
		 TeamPenalty::TABLES, $team->id, $div);
    $this->query($q);
  }

  /**
   * Returns the team penalty, or null
   *
   * @param Team $team the team
   * @param Division $div the division
   * @return TeamPenalty if one exists, or null otherwise
   */
  public function getTeamPenalty(Team $team, Division $div) {
    $q = sprintf('select %s from %s where team = "%s" and division = "%s"',
		 TeamPenalty::FIELDS, TeamPenalty::TABLES,
		 $team->id, $div);
    $q = $this->query($q);
    if ($q->num_rows == 0)
      return null;
    return $q->fetch_object("TeamPenalty");
  }
  

  /**
   * Returns list of all the team penalties for the given team, or all
   * if null
   *
   * @param Team $team the team whose penalties to return, or all if null
   * @param Division $div the division to fetch, or all if null
   * @return Array<TeamPenalty> list of team penalties
   */
  public function getTeamPenalties(Team $team = null, Division $div = null) {
    if ($team == null) {
      $list = array();
      foreach ($this->getTeams() as $team)
	$list = array_merge($list, $this->getTeamPenalties($team, $div));
      return $list;
    }
    if ($div == null) {
      $list = array();
      foreach ($this->getDivisions() as $division) {
        $pen = $this->getTeamPenalty($team, $division);
        if ($pen != null) {
          $list[] = $pen;
        }
      }
      return $list;
    }
    return $this->getTeamPenalty($team, $div);
  }

  /**
   * Returns the timestamp of the last score update
   *
   * @return DateTime, or null if no update found
   */
  public function getLastScoreUpdate() {
    $q = sprintf('select request_time from %s where regatta = %d and activity = "%s"',
		 UpdateRequest::TABLES, $this->id(), UpdateRequest::ACTIVITY_SCORE);
    $q = $this->query($q);
    if ($q->num_rows == 0)
      return null;

    return new DateTime($q->fetch_object()->request_time);
  }

  /**
   * Gets the winning team for this regatta. That is, the team with
   * the lowest score thus far
   *
   * @return Team the winning team object
   */
  public function getWinningTeam() {
    $ranks = $this->__get("scorer")->rank($this);
    if (count($ranks) == 0) return null;
    return $ranks[0]->team;
  }

  /**
   * Like getWinningTeam, this more generic method returns a list of
   * where did every team belonging to the given school finish in this
   * regatta (or is currently finishing). Returns a list because a
   * school can have more than one team per regatta.
   *
   * An empty array means that the school had no teams in this
   * regatta (something which can be known ahead of time using the
   * Season::getParticipation function.
   *
   * @param School $school the school
   * @return Array:int the current or final place finish for all teams
   */
  public function getPlaces(School $school) {
    $ranks = $this->__get("scorer")->rank($this);
    $places = array();
    foreach ($ranks as $i => $rank) {
      if ($rank->team->school->id == $school->id)
	$places[] = ($i + 1);
    }
    return $places;
  }

  // ------------------------------------------------------------
  // Scorers
  // ------------------------------------------------------------

  /**
   * Return a list of scorers for this regatta
   *
   * @return Array<Account> a list of scorers
   */
  public function getScorers() {
    $q = sprintf('select %s from %s ' .
		 'inner join host on    (host.account = account.username) ' .
		 'inner join regatta on (host.regatta = regatta.id) ' .
		 'where regatta = "%s"',
		 Account::FIELDS, Account::TABLES, $this->id);
    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object("Account")) {
      $list[] = $obj;
    }
    return $list;
  }

  /**
   * Returns a list of hosts for this regatta
   *
   * @return Array<Account> a list of hosts
   */
  public function getHosts() {
    $q = sprintf('select %s from %s ' .
		 'inner join host on    (host.account = account.username) ' .
		 'inner join regatta on (host.regatta = regatta.id) ' .
		 'where regatta = "%s" and host.principal = 1',
		 Account::FIELDS, Account::TABLES, $this->id);
    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object("Account")) {
      $list[] = $obj;
    }
    return $list;
  }

  /**
   * Registers the given scorer with this regatta
   *
   * @param Account $acc the account to add
   * @param bool is_host whether or not this account is also a host
   * for the regatta (default: false)
   */
  public function addScorer(Account $acc, $is_host = false) {
    $q = sprintf('replace into host values ("%s", "%s", %d)',
		 $acc->username, $this->id, ($is_host) ? 1 : 0);
    $this->query($q);
  }

  /**
   * Removes the specified account from this regatta
   *
   * @param Account $acc the account of the scorer to be removed
   * from this regatta
   */
  public function removeScorer(Account $acc) {
    $q = sprintf('delete from host where account = "%s"', $acc->username);
    $q = $this->query($q);
  }

  //------------------------------------------------------------
  // Misc

  /**
   * Get this regatta's ID
   *
   * @return int the regatta's ID
   */
  public function id() { return $this->id; }

  /**
   * Gets the rotation object that manages this regatta's rotation
   *
   * @return the rotation object for this regatta
   */
  public function getRotation() {
    return $this->rotation;
  }

  /**
   * Gets the RpManager object that manages this regatta's RP
   *
   * @return RpManager the rp manager
   */
  public function getRpManager() {
    return $this->rp;
  }

  /**
   * Returns the day stripped of time-of-day information
   *
   * @param DateTime $time the datetime object
   * @return DateTime the modified datetime object
   */
  public function getDay(DateTime $time) {
    $time_copy = clone($time);
    date_time_set($time_copy, 0, 0, 0);
    return $time_copy;
  }


  /**
   * Determines whether the regatta is a singlehanded regatta or
   * not. Singlehanded regattas consist of one division, and each race
   * consists of single-occupant boats
   *
   * @return boolean is this regatta singlehanded?
   */
  public function isSingleHanded() {
    $divisions = $this->getDivisions();
    if (count($divisions) > 1) return false;

    foreach ($this->getRaces(array_shift($divisions)) as $race) {
      if ($race->boat->occupants > 1)
	return false;
    }
    return true;
  }

  /**
   * Scores itself
   *
   */
  public function doScore() {
    
    // Check that all races are complete:
    $count     = count($this->getTeams());
    $divisions = $this->getDivisions();

    // When scoring combined, the same race number must be scored
    // across all divisions.
    if ($this->get(Regatta::SCORING) == Regatta::SCORING_COMBINED) {

      // start with a list of all the race numbers available. Assumes
      // that each division has the same race numbers. Then remove
      // those race numbers for which any race in any division does
      // not equal the total number of teams
      $numbers = array();
      foreach ($this->getRaces($divisions[0]) as $race)
	$numbers[] = $race->number;

      $faulty = array();
      foreach ($numbers as $num) {
	foreach ($divisions as $div) {
	  $f = $this->getFinishes($this->getRace($div, $num));
	  if (count($f) != $count) {
	    $faulty[] = $num;
	    break;
	  }
	}
      }

      // delete finishes for faulty races
      foreach ($faulty as $num) {
	foreach ($divisions as $div)
	  $this->deleteFinishes($this->getRace($div, $num));
      }
    }
    // With standard scoring, each race is counted individually
    else {
      foreach ($divisions as $div) {
	foreach ($this->getScoredRaces($div) as $race) {
	  if (count($this->getFinishes($race)) != $count)
	    $this->deleteFinishes($race);
	}
      }
    }


    $this->scorer->score($this);

    // Queue public score update
    UpdateManager::queueRequest($this, UpdateRequest::ACTIVITY_SCORE);
  }

  // ------------------------------------------------------------
  // Listeners

  /**
   * Commits the properties of the race object. If a race's properties
   * change, this function registers those changes with the database.
   * Note that only the boat and the division are updated.
   *
   * @param Race $race the race to update
   */
  public function changedRace(Race $race) {
    $q = sprintf('update race set boat = "%s", division = "%s" ' .
		 'where id = "%s"',
		 $race->boat->id, $race->division, $race->id);
    $this->query($q);
  }

  /**
   * Commits the changes to the finish
   *
   * @param FinishListener::CONST $type the type of change
   * @param Finish $finish the finish
   */
  public function finishChanged($type, Finish $finish) {

    // Penalties
    if ($type == FinishListener::PENALTY) {
      $q1 = sprintf('delete from penalty  where finish = "%s"', $finish->id);
      $q2 = sprintf('delete from handicap where finish = "%s"', $finish->id);
      $this->query($q1);
      $this->query($q2);

      if ($finish->penalty instanceof Breakdown)
	$q = sprintf('replace into handicap values ("%s", "%s", "%s", "%s")',
		     $finish->id,
		     $finish->penalty->type,
		     $finish->penalty->amount,
		     $finish->penalty->comments);
      else
	$q = sprintf('replace into penalty values ("%s", "%s", "%s")',
		     $finish->id,
		     $finish->penalty->type,
		     $finish->penalty->comments);
      $this->query($q);
      $this->doScore();
    }

    // Scores
    elseif ($type == FinishListener::SCORE) {
      $q = sprintf('update finish set place = "%s", score = "%s", explanation = "%s" where id = %d',
		   $finish->place,
		   $finish->score,
		   $finish->explanation,
		   $finish->id);
      $this->query($q);
    }

    // Entered
    elseif ($type == FinishListener::ENTERED) {
      $q = sprintf('update finish set entered = "%s" where id = "%s"',
		   $finish->entered->format("Y-m-d H:i:s"),
		   $finish->id);
      $this->regatta->query($q);
    }
  }

  /**
   * Fetches a list of all the notes for the given race, or the entire
   * regatta if no race provided
   *
   * @return Array<Note> the list of notes
   */
  public function getNotes(Race $race = null) {
    if ($race == null) {
      $list = array();
      foreach ($this->getRaces() as $race) {
	$list = array_merge($list, $this->getNotes($race));
      }
      return $list;
    }

    // Fetch the notes for the given race
    $q = sprintf('select %s from %s where race = "%s"',
		 Note::FIELDS, Note::TABLES, $race->id);
    $q = $this->query($q);

    $list = array();
    while ($obj = $q->fetch_object("Note")) {
      $list[] = $obj;
      $obj->race = $race;
    }
    return $list;
  }

  /**
   * Adds the given note to the regatta. Updates the Note object
   *
   * @param Note $note the note to add and update
   */
  public function addNote(Note $note) {
    $now = new DateTime("now", new DateTimeZone("America/New_York"));
    $q = sprintf('insert into observation (race, observation, observer, noted_at) ' .
		 'values ("%s", "%s", "%s", "%s")',
		 $note->race->id, $note->observation, $now->format("Y-m-d H:i:s"), $note->observer);
    $this->query($q);

    $res = $this->query('select last_insert_id() as id');
    $note->id = $res->fetch_object()->id;
    $note->noted_at = $now;
  }

  /**
   * Deletes the given note from the regatta
   *
   * @param Note $note the note to delete
   */
  public function deleteNote(Note $note) {
    $q = sprintf('delete from observation where id = "%s"', $note->id);
    $this->query($q);
  }
  

  // ------------------------------------------------------------
  // Static methods and properties
  // ------------------------------------------------------------

  private static $static_con;

  /**
   * Sends the given request to the database
   *
   * @param String $query the query to send
   * @return MySQLi_Result the result
   * @throws BadFunctionCallException should the query be unsuccessful.
   */
  protected static function static_query($query) {
    if (!isset(self::$static_con))
      self::$static_con = new MySQLi(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB);
    
    $res = self::$static_con->query($query);
    $error = self::$static_con->error;
    if (!empty($error))
      throw new BadFunctionCallException("Invalid query: $error.");
    return $res;
  }
  
  /**
   * Creates a new regatta with the given specs
   *
   * @param String $db the database to add the regatta to, must be in
   * the database map ($self::DB_MAP)
   * @param String $name the name of the regatta
   * @param DateTime $start_time the start time of the regatta
   * @param DateTime $end_date the end_date
   * @param String $type one of those listed in Preferences::getRegattaTypeAssoc()
   * @param String $comments the comments (default empty)
   *
   * @return int the ID of the regatta
   *
   * @throws InvalidArgumentException if illegal regatta type
   */
  protected static function addRegatta($db,
				       $name,
				       DateTime $start_time,
				       DateTime $end_date,
				       $type,
				       $scoring) {
    if (!in_array($type, array_keys(Preferences::getRegattaTypeAssoc())))
      throw new InvalidArgumentException("No such regatta type $type.");
    if (!in_array($scoring, array_keys(Preferences::getRegattaScoringAssoc())))
      throw new InvalidArgumentException("No such regatta scoring $scoring.");

    $q = sprintf('insert into regatta ' .
		 '(name, start_time, end_date, type, scoring) values ' .
		 '("%s", "%s", "%s", "%s", "%s")',
		 addslashes((string)$name),
		 $start_time->format("Y-m-d H:i:s"),
		 $end_date->format("Y-m-d"),
		 $type,
		 $scoring);

    $res = self::static_query($q);
    
    // Fetch the regatta back
    return self::$static_con->insert_id;
  }

  /**
   * Creates a new regatta with the given specs
   *
   * @param String $name the name of the regatta
   * @param DateTime $start_time the start time of the regatta
   * @param DateTime $end_date the end_date
   * @param String $type one of those listed in Preferences::getRegattaTypeAssoc()
   * @param String $scoring one of those listed in Preferences::getRegattaScoringAssoc()
   * @param String $comments the comments (default empty)
   *
   * @throws InvalidArgumentException if illegal regatta type or name
   */
  public static function createRegatta($name,
				       DateTime $start_time,
				       DateTime $end_date,
				       $type,
				       $scoring) {
    $id = self::addRegatta(SQL_DB, $name, $start_time, $end_date, $type, $scoring);
    $r = new Regatta($id);
    // do not create nick names for personal regattas (nick name
    // creation is delayed until the regatta is made active)
    if ($type != Preferences::TYPE_PERSONAL)
      $r->set(Regatta::NICK_NAME, $r->createNick());
    return $r;
  }

  /**
   * Creates a regatta nick name for this regatta based on this
   * regatta's name. Nick names are guaranteed to be a unique per
   * season. As such, this function will throw an error if there is
   * already a regatta with the same nick name as this one. This is
   * meant to establish some order from users who fail to read
   * instructions and create mutliple regattas all with the same name,
   * leaving behind "phantom" regattas.
   *
   * Nicknames are all lower case, separated by dashes, and devoid of
   * filler words, including 'trophy', 'championship', and the like.
   *
   * @return String the nick name
   * @throw InvalidArgumentException if the nick name is not unique
   */
  public function createNick() {
    $name = strtolower($this->get(Regatta::NAME));
    // Remove 's from words
    $name = str_replace('\'s', '', $name);

    // Convert dashes, slashes and underscores into spaces
    $name = str_replace('-', ' ', $name);
    $name = str_replace('/', ' ', $name);
    $name = str_replace('_', ' ', $name);

    // White list permission
    $name = preg_replace('/[^0-9a-z\s_+]+/', '', $name);

    // Remove '80th'
    $name = preg_replace('/[0-9]+th/', '', $name);
    $name = preg_replace('/[0-9]*1st/', '', $name);
    $name = preg_replace('/[0-9]*2nd/', '', $name);
    $name = preg_replace('/[0-9]*3rd/', '', $name);

    // Trim and squeeze spaces
    $name = trim($name);
    $name = preg_replace('/\s+/', '-', $name);

    $tokens = explode("-", $name);
    $blacklist = array("the", "of", "for", "and", "an", "in", "is", "at",
		       "trophy", "championship", "intersectional",
		       "college", "university",
		       "professor");
    $tok_copy = $tokens;
    foreach ($tok_copy as $i => $t)
      if (in_array($t, $blacklist))
	unset($tokens[$i]);
    $name = implode("-", $tokens);

    // eastern -> east
    $name = str_replace("eastern", "east", $name);
    $name = str_replace("western", "west", $name);
    $name = str_replace("northern", "north", $name);
    $name = str_replace("southern", "south", $name);

    // semifinals -> semis
    $name = str_replace("semifinals", "semis", $name);
    $name = str_replace("semifinal",  "semis", $name);

    // list of regatta names in the same season as this one
    foreach ($this->get(Regatta::SEASON)->getRegattas() as $n) {
      if ($n->nick == $name && $n->id != $this->id)
	throw new InvalidArgumentException(sprintf("Nick name \"%s\" already in use by (%d).",
						   $name, $n->id));
    }
    return $name;
  }
}
?>
