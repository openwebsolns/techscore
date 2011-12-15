<?php
/*
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */

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
 * @version 2009-10-01
 */
class Regatta implements RaceListener {

  private $id;
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

    // Update the properties
    $q = sprintf('select regatta.id, regatta.name, regatta.nick, ' .
		 'regatta.start_time, regatta.end_date, regatta.venue, ' .
		 'regatta.type, regatta.finalized, regatta.scoring, regatta.participant ' .
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

      // Venue and Season shall not be serialized until they are
      // requested
      $this->properties[Regatta::SEASON] = null;

      // Finalized
      if (($p = $this->properties[Regatta::FINALIZED]) !== null)
	$this->properties[Regatta::FINALIZED] = new DateTime($p);
    }
    else {
      throw new InvalidArgumentException("Invalid ID for regatta.");
    }
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
      if ($this->properties[$property] !== null &&
	  !($this->properties[$property] instanceof Venue))
	$this->properties[$property] = Preferences::getVenue($this->properties[$property]);
    }
    elseif ($property == Regatta::SEASON) {
      if ($this->properties[$property] === null)
	$this->properties[$property] = new Season($this->properties[Regatta::START_TIME]);
    }
    return $this->properties[$property];
  }

  public function __get($name) {
    if ($name == "scorer") {
      if ($this->scorer === null) {
	require_once('regatta/ICSAScorer.php');
	$this->scorer = new ICSAScorer();
      }
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
      if ($value != Preferences::TYPE_PERSONAL)
	$this->set(Regatta::NICK_NAME, $this->createNick());
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
      return array_values($this->divisions);
    
    $q = sprintf('select distinct division from race ' .
		 'where regatta = "%s" ' . 
		 'order by division',
		 $this->id);
    $q = $this->query($q);
    $this->divisions = array();
    while ($row = $q->fetch_object()) {
      $this->divisions[$row->division] = Division::get($row->division);
    }
    return array_values($this->divisions);
  }

  // attempt to cache teams
  private $teams = null;
  /**
   * Fetches the team with the given ID, or null
   *
   * @param int $id the id of the team
   * @return Team|null if the team exists
   */
  public function getTeam($id) {
    if ($this->teams !== null) {
      if (isset($this->teams[$id]))
	return $this->teams[$id];
      return null;
    }

    $q = sprintf('select team.id, team.name, team.school from team where regatta = %d and id = %d limit 1',
		 $this->id, $id);
    $q = $this->query($q);
    if ($q->num_rows == 0)
      return null;

    if ($this->isSingleHanded()) {
      $team = $q->fetch_object('SinglehandedTeam');
      $team->setRpManager($this->rp);
    }
    else
      $team = $q->fetch_object('Team');
    return $team;
  }

  /**
   * Just get the number of teams, which is slightly quicker than
   * serializing all those teams.
   *
   * @return int the fleet size
   */
  public function getFleetSize() {
    if ($this->teams !== null)
      return count($this->teams);
    $q = $this->query(sprintf('select id from team where regatta = %d', $this->id));
    $n = $q->num_rows;
    $q->free();
    return $n;
  }

  /**
   * Gets a list of team objects for this regatta.
   *
   * @param School $school the optional school whose teams to return
   * @return array of team objects
   */
  public function getTeams(School $school = null) {
    if ($school === null && $this->teams !== null)
      return array_values($this->teams);
    
    $q = sprintf('select team.id, team.name, team.school ' .
		 'from team where regatta = "%s" %s order by school, id',
		 $this->id,
		 ($school === null) ? '' : sprintf('and school = "%s"', $school->id));
    $q = $this->query($q);

    $teams = array();
    if ($this->isSingleHanded()) {
      while ($team = $q->fetch_object("SinglehandedTeam")) {
	$teams[$team->id] = $team;
	$team->setRpManager($this->rp);
      }
    }
    else {
      while ($team = $q->fetch_object("Team")) {
	$teams[$team->id] = $team;
      }
    }
    if ($school === null)
      $this->teams = $teams;
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
    $con = Preferences::getConnection();
    $q = sprintf('insert into team (regatta, school, name) ' .
		 'values ("%s", "%s", "%s")',
		 $this->id, $team->school->id, $team->name);
    $this->query($q);
    $team->id = $con->insert_id;
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
    $this->getTeams();
    if (!isset($this->teams[$old->id]))
      throw new InvalidArgumentException("Team \"$old\" is not part of this regatta.");
    
    $this->query(sprintf('update team set school = "%s", name = "%s" where id = "%s"',
			 $new->school->id, $new->name, $old->id));
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
    $q = sprintf('select team, sum(score) as total from finish ' .
		 'where race in (select id from race where regatta = %d and division in ("%s")) ' .
		 'group by team order by total',
		 $this->id,
		 implode('","', $divs));
    $q = $this->query($q);
    $ranks = array();
    while ($obj = $q->fetch_object())
      $ranks[] = new Rank($this->getTeam($obj->team), $obj->total);
    $q->free();
    return $ranks;
  }

  /**
   * Remove the given team from this regatta
   *
   * @param Team $team the team to remove
   */
  public function removeTeam(Team $team) {
    $q = sprintf('delete from team where id = "%s" and regatta = "%s"', $team->id, $this->id);
    $this->query($q);
    unset($this->teams[$team->id]);
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
    $sdiv = (string)$div;
    if (isset($this->races[$sdiv]) &&
	isset($this->races[$sdiv][$num - 1]))
      return $this->races[$sdiv][$num - 1];
    
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
    if (!isset($this->races[$sdiv]))
      $this->races[$sdiv] = array();
    $this->races[$sdiv][$num - 1] = $race;
    return $race;
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
    
    $q = $this->query(sprintf('select id from race where regatta = %d', $this->id));
    $this->total_races = $q->num_rows;
    $q->free();
    return $this->total_races;
  }

  /**
   * @var Array an associative array of divisions => list of races,
   * for those times when the races per division are requested
   */
  private $races = array();
  private $total_races = null;
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
    $sdiv = (string)$div;
    if (isset($this->races[$sdiv]))
      return $this->races[$sdiv];

    $q = sprintf('select %s from %s ' .
		 'where (regatta, division) = ' .
		 '      ("%s",    "%s") order by number',
		 Race::FIELDS, Race::TABLES,
		 $this->id, $sdiv);
    $q = $this->query($q);
    $this->races[$sdiv] = array();

    while ($race = $q->fetch_object("Race")) {
      $this->races[$sdiv][] = $race;
      $race->addListener($this);
    }
    return $this->races[$sdiv];
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
   * Adds the specified race to this regatta. Unlike in previous
   * versions, the user needs to specify the race number. As a result,
   * if the race already exists, the code will attempt to update the
   * race instead of adding a new one.
   *
   * @param Race $race the race to register with this regatta
   */
  public function setRace(Race $race) {
    $q = sprintf('insert into race (regatta, division, number, boat) ' .
		 'values ("%s", "%s", "%s", %d) ' .
		 'on duplicate key update boat = %4$d',
		 $this->id, $race->division, $race->number, $race->boat->id);
    $this->query($q);
    $con = Preferences::getConnection();
     // amounts to an insert
    if ($con->affected_rows > 1 && $this->total_races !== null)
      $this->total_races++;
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
   * @return boolean true if something was removed.
   */
  public function removeRace(Race $race) {
    $this->query(sprintf('delete from race where (regatta, division, number) = (%d, "%s", %d)',
			 $this->id, $race->division, $race->number));
    $con = Preferences::getConnection();
    if ($con->affected_rows > 0) {
      if (isset($this->races[(string)$race->division]))
	unset($this->races[(string)$race->division]);
      if ($this->total_races !== null)
	$this->total_races--;
      return true;
    }
    return false;
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
    unset($this->divisions[(string)$div]);
    if ($this->total_races !== null) {
      $con = Preferences::getConnection();
      $this->total_races -= $con->affected_rows;
    }
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

  // ------------------------------------------------------------
  // FINISHES
  // ------------------------------------------------------------
  
  /**
   * @var Array attempt to cache finishes, index is 'race-team_id'
   */
  private $finishes = array();

  /**
   * Helper method to serialize to the database a finish objec. This
   * merely creates the query that should be executed for the given
   * finish object
   *
   * @param Finish $fin the finish object to serialize
   * @return String the query
   */
  private function serializeFinish(Race $race, Finish $fin) {
    $fields = array('id', 'race', 'team', 'entered');
    $values = array('"'.$fin->id.'"',
		    '"'.$race->id.'"',
		    '"'.$fin->team->id.'"',
		    '"'.$fin->entered->format('Y-m-d H:i:s').'"');
    $update = array('entered=values(entered)');

    if ($fin->score !== null) {
      $fields[] = 'score';
      $fields[] = 'explanation';
      $values[] = '"'.$fin->score.'"';
      $values[] = '"'.$fin->explanation.'"';
      $update[] = 'score=values(score),explanation=values(explanation)';
    }
    $fields[] = 'amount';
    $fields[] = 'penalty';
    $fields[] = 'comments';
    $update[] = 'amount=values(amount),penalty=values(penalty),comments=values(comments)';
    if ($fin->penalty !== null) {
      $values[] = '"'.$fin->penalty->amount.'"';
      $values[] = '"'.$fin->penalty->type.'"';
      $values[] = '"'.$fin->penalty->comments.'"';
      if ($fin->penalty instanceof Breakdown) {
	$fields[] = 'earned';
	$values[] = '"'.$fin->penalty->earned.'"';
	$update[] = 'earned=values(earned)';
      }
    }
    else {
      $values[] = '0';
      $values[] = 'NULL';
      $values[] = 'NULL';
    }
    
    return sprintf('insert into finish (%s) values (%s) on duplicate key update %s',
		   implode(',', $fields),
		   implode(',', $values),
		   implode(',', $update));
  }

  /**
   * Helper method creates the finish object from the MySQLi_Result object
   *
   * @param MySQLi_Result $sql the result of a query that returns the
   * finish object's parameters
   *
   * @param Race $race the race
   * @param Team $team the team
   *
   * @return Finish|null the first finish object from the result set
   */
  private function deserializeFinish(MySQLi_Result $res, Race $race, Team $team) {
    $fin = $res->fetch_object();
    if ($fin === false)
      return null;
    
    $finish = new Finish($fin->id, $race, $team);
    $finish->entered = new DateTime($fin->entered);

    // penalty
    if ($fin->penalty !== null) {
      $penalties = Penalty::getList();
      if (isset($penalties[$fin->penalty]))
	$finish->penalty = new Penalty($fin->penalty, $fin->amount, $fin->comments, $fin->displace);
      else {
	$finish->penalty = new Breakdown($fin->penalty, $fin->amount, $fin->comments, $fin->displace);
	$finish->penalty->earned = $fin->earned;
      }
    }
    $finish->score = new Score($fin->score, $fin->explanation);
    return $finish;
  }

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
    $id = sprintf('%s-%d', $race, $team->id);
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
    // $id = sprintf('%s-%s', $race, $team->id);
    $id = (string)$race . '-' . $team->id;
    if (isset($this->finishes[$id])) {
      return $this->finishes[$id];
    }

    $q = sprintf('select %s from %s where (race, team) = ("%s", "%s")',
		 Finish::FIELDS, Finish::TABLES, $race->id, $team->id);

    $q = $this->query($q);
    if ($q->num_rows == 0)
      return null;

    $this->finishes[$id] = $this->deserializeFinish($q, $race, $team);
    $q->free();
    $this->has_finish = true;
    return $this->finishes[$id];
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
    $finishes = array();
    foreach ($this->getTeams() as $team) {
      if (($f = $this->getFinish($race, $team)) !== null)
	$finishes[] = $f;
    }
    return $finishes;
  }

  /**
   * Returns all the finishes which have been "penalized" in one way
   * or another. That is, they have either a penalty or a breakdown
   *
   * @return Array:Finish the list of finishes, regardless of race
   */
  public function getPenalizedFinishes() {
    $q = sprintf('select race.id, race.division, race.number, finish.team from finish ' .
		 'inner join race on finish.race = race.id ' .
		 'where finish.penalty is not null and race.regatta = %d',
		 $this->id);

    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object()) {
      $list[] = $this->getFinish($this->getRace(Division::get($obj->division), $obj->number),
				 $this->getTeam($obj->team));
    }
    $q->free();
    return $list;
  }

  /**
   * Returns a list of those finishes in the given division which are
   * set to be scored as average of the other finishes in the same
   * division. Confused, read the procedural rules for breakdowns, etc.
   *
   * @param Division $div the division whose average-scored finishes
   * to fetch
   *
   * @return Array:Finish the finishes
   */
  public function getAverageFinishes(Division $div) {
    $q = sprintf('select race.division, race.number, finish.team from finish ' .
		 'inner join race on finish.race = race.id ' .
		 'where finish.penalty in ("BKD", "RDG", "BYE") and finish.amount <= 0 ' .
		 '  and race.regatta = %d and race.division = "%s"',
		 $this->id, $div);

    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object()) {
      $rc = $this->getRace(Division::get($obj->division), $obj->number);
      $tm = $this->getTeam($obj->team);
      $list[] = $this->getFinish($rc, $tm);
    }
    $q->free();
    return $list;
  }

  /**
   * Like hasFinishes, but checks specifically for finishes
   *
   * @param Race $race optional, if given, returns status for only
   * that race
   * @return boolean
   * @see hasFinishes
   */
  public function hasPenalties(Race $race = null) {
    $and = '';
    if ($race !== null)
      $and = sprintf('and race = "%s"', $race->id);
    $q = $this->query(sprintf('select id from finish ' .
			      'where race in (select id from race where regatta = %d) ' .
			      '  and finish.penalty is not null %s', $this->id, $and));
    $cnt = ($q->num_rows > 0);
    $q->free();
    return $cnt;
  }

  private $has_finishes = null;
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

    $and = '';
    if ($race !== null)
      $and = sprintf('and race = "%s"', $race->id);
    $q = $this->query(sprintf('select id from finish ' .
			      'where race in (select id from race where regatta = %d) %s', $this->id, $and));
    $cnt = ($q->num_rows > 0);
    $q->free();
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
      $this->query($this->serializeFinish($finish->race, $finish));
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

  /**
   * Set team penalty
   *
   * @param TeamPenalty $penalty the penalty to register
   */
  public function setTeamPenalty(TeamPenalty $penalty) {
    $con = Preferences::getConnection();
    $q = sprintf('insert into %s values ("%s", "%s", "%s", "%s") ' .
		 'on duplicate key update type = values(type), comments = values(comments)',
		 TeamPenalty::TABLES,
		 $penalty->team->id,
		 $penalty->division,
		 $penalty->type,
		 $con->escape_string($penalty->comments));
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
    $pen = $q->fetch_object("TeamPenalty");
    $pen->team = $this->getTeam($pen->team);
    $q->free();
    return $pen;
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
    $q = sprintf('select %s from %s where id in (select account from host where regatta = "%s")',
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
    $q = sprintf('select %s from %s where regatta = "%s"',
		 Host::FIELDS, Host::TABLES, $this->id);
    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object("Host")) {
      $list[] = $obj;
    }
    return $list;
  }

  public function addHost(School $school) {
    $q = sprintf('insert ignore into host_school (regatta, school) values ("%s", "%s")',
		 $this->id, $school->id);
    $this->query($q);
  }

  /**
   * Removes all the host from the regatta. Careful! Each regatta must
   * have at least one host, so do not forget to ::addHost later
   *
   */
  public function resetHosts() {
    $this->query(sprintf('delete from host_school where regatta = "%s"', $this->id));
  }

  /**
   * Registers the given scorer with this regatta
   *
   * @param Account $acc the account to add
   */
  public function addScorer(Account $acc) {
    $q = sprintf('insert into host (account, regatta) values ("%s", "%s")',
		 $acc->id, $this->id);
    $this->query($q);
  }

  /**
   * Removes the specified account from this regatta
   *
   * @param Account $acc the account of the scorer to be removed
   * from this regatta
   */
  public function removeScorer(Account $acc) {
    $q = sprintf('delete from host where account = "%s" and regatta = "%s"', $acc->id, $this->id);
    $q = $this->query($q);
  }

  /**
   * Set the special column in the database for the creator of the
   * regatta. This should only be called once when the regatta is
   * created, for example.
   *
   * @param Account $acc the account to set as the creator
   */
  public function setCreator(Account $acc) {
    $con = Preferences::getConnection();
    $q = sprintf('update regatta set creator = "%s" where id = "%s"',
		 $con->escape_string($acc->id),
		 $this->id);
    $this->query($q);
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
    if ($this->rotation === null) {
      require_once('regatta/Rotation.php');
      $this->rotation = new Rotation($this);
    }
    return $this->rotation;
  }

  /**
   * Gets the RpManager object that manages this regatta's RP
   *
   * @return RpManager the rp manager
   */
  public function getRpManager() {
    if ($this->rp === null) {
      require_once('regatta/RpManager.php');
      $this->rp = new RpManager($this);
    }
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
   * Calls the 'score' method on this regatta's scorer, feeding it the
   * given race. This new method should be used during scoring, as it
   * updates only the one affected race at a time. Whereas the doScore
   * method is more appropriate for input data that needs to be
   * checked first for possible errors.
   *
   * Note that the scorer is responsible for committing the affected
   * finishes back to the database, and so there is no need to
   * explicitly call 'setFinishes' after calling this function.
   *
   * @param Race $race the race to run the score
   */
  public function runScore(Race $race) {
    $this->__get('scorer')->score($this, $race);
  }

  /**
   * Scores the entire regatta
   */
  public function doScore() {
    $scorer = $this->__get('scorer');
    foreach ($this->getScoredRaces() as $race)
      $scorer->score($this, $race);
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

  /**
   * Creates a new regatta with the given specs
   *
   * @param String $db the database to add the regatta to, must be in
   * the database map ($self::DB_MAP)
   * @param String $name the name of the regatta
   * @param DateTime $start_time the start time of the regatta
   * @param DateTime $end_date the end_date
   * @param String $type one of those listed in Preferences::getRegattaTypeAssoc()
   * @param String $participant one of those listed in Preferences::getRegattaParticipantAssoc()
   * @return int the ID of the regatta
   *
   * @throws InvalidArgumentException if illegal regatta type
   */
  protected static function addRegatta($db,
				       $name,
				       DateTime $start_time,
				       DateTime $end_date,
				       $type,
				       $scoring,
				       $participant = Regatta::PARTICIPANT_COED) {
    if (!in_array($type, array_keys(Preferences::getRegattaTypeAssoc())))
      throw new InvalidArgumentException("No such regatta type $type.");
    if (!in_array($scoring, array_keys(Preferences::getRegattaScoringAssoc())))
      throw new InvalidArgumentException("No such regatta scoring $scoring.");
    if (!in_array($participant, array_keys(Preferences::getRegattaParticipantAssoc())))
      throw new InvalidArgumentException("No such regatta scoring $scoring.");

    // Fetch the regatta back
    $con = Preferences::getConnection();
    $q = sprintf('insert into regatta ' .
		 '(name, start_time, end_date, type, scoring, participant) values ' .
		 '("%s", "%s", "%s", "%s", "%s", "%s")',
		 $con->escape_string($name),
		 $start_time->format("Y-m-d H:i:s"),
		 $end_date->format("Y-m-d"),
		 $type,
		 $scoring,
		 $participant);

    $res = Preferences::query($q);

    return $con->insert_id;
  }

  /**
   * Creates a new regatta with the given specs
   *
   * @param String $name the name of the regatta
   * @param DateTime $start_time the start time of the regatta
   * @param DateTime $end_date the end_date
   * @param String $type one of those listed in Preferences::getRegattaTypeAssoc()
   * @param String $scoring one of those listed in Preferences::getRegattaScoringAssoc()
   * @param String $participant one of those listed in Preferences::getRegattaParticipantAssoc()
   *
   * @throws InvalidArgumentException if illegal regatta type or name
   */
  public static function createRegatta($name,
				       DateTime $start_time,
				       DateTime $end_date,
				       $type,
				       $scoring,
				       $participant = Regatta::PARTICIPANT_COED) {
    $id = self::addRegatta(SQL_DB, $name, $start_time, $end_date, $type, $scoring, $participant);
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
