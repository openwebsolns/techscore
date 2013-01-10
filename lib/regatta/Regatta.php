<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

require_once('regatta/DB.php');

/**
 * Encapsulates a regatta object.
 *
 * This is the central class for all operations on regattas. This
 * prototype class does not impose any conditions (db_where). As such,
 * for everyday use, use either the regular Regatta class or any of
 * its derivatives (like Public_Regatta).
 *
 * @author Dayan Paez
 * @version 2009-11-30
 */
class FullRegatta extends DBObject {

  /**
   * Standard scoring
   */
  const SCORING_STANDARD = "standard";

  /**
   * Combined scoring
   */   
  const SCORING_COMBINED = "combined";

  /**
   * Team racing
   */
  const SCORING_TEAM = 'team';

  /**
   * Women's regatta
   */
  const PARTICIPANT_WOMEN = "women";

  /**
   * Coed regatta (default)
   */
  const PARTICIPANT_COED = "coed";

  /**
   * @const status for regattas that have been scheduled (details only)
   */
  const STAT_SCHEDULED = 'scheduled';
  /**
   * @const status for regattas that are ready
   */
  const STAT_READY = 'ready';
  /**
   * @const status for regattas that have no more finishes, but they
   * are not finalized
   */
  const STAT_FINISHED = 'finished';
  /**
   * @const status for regattas that have been finalized
   */
  const STAT_FINAL = 'final';

  /**
   * Gets an assoc. array of the possible scoring rules
   *
   * @return Array a dict of scoring rules
   */
  public static function getScoringOptions() {
    $lst = array(Regatta::SCORING_STANDARD => "Standard",
                 Regatta::SCORING_COMBINED => "Combined divisions",
                 Regatta::SCORING_TEAM => "Team racing");
    foreach (Conf::$REGATTA_SCORING_BLACKLIST as $rem)
      unset($lst[$rem]);
    return $lst;
  }

  /**
   * Gets an assoc. array of the possible participant values
   *
   * @return Array a dict of scoring rules
   */
  public static function getParticipantOptions() {
    return array(Regatta::PARTICIPANT_COED => "Coed",
                 Regatta::PARTICIPANT_WOMEN => "Women");
  }

  // Variables
  public $name;
  public $nick;
  protected $start_time;
  protected $end_date;
  protected $type;
  protected $finalized;
  protected $creator;
  protected $venue;
  public $participant;
  public $scoring;
  public $private;
  protected $inactive;

  // Data properties
  public $dt_num_divisions;
  public $dt_num_races;
  protected $dt_hosts;
  protected $dt_confs;
  protected $dt_boats;
  public $dt_singlehanded;
  protected $dt_season;
  /**
   * @var String the status of the race. Could be race number, or one
   * of Regatta::STAT_* constants.
   */
  public $dt_status;

  // Managers
  private $rotation;
  private $rp;
  private $season;
  private $season_start_time; // the start_time the current season
                              // value is basd on
  private $scorer;
  /**
   * @var ICSARanker the team-ranking object
   */
  private $ranker;
  /**
   * @var ICSARanker the ranker object to use for individual
   * divisions. Only used for combined division events, and only when
   * requesting division ranker.
   */
  private $ranker_division;

  // ------------------------------------------------------------
  // DBObject stuff
  // ------------------------------------------------------------
  public function db_name() { return 'regatta'; }
  protected function db_order() { return array('start_time'=>false); }
  protected function db_cache() { return true; }
  public function db_type($field) {
    switch ($field) {
    case 'start_time':
    case 'end_date':
    case 'finalized':
    case 'inactive':
      return DB::$NOW;
    case 'creator':
      require_once('regatta/Account.php');
      return DB::$ACCOUNT;
    case 'venue':
      return DB::$VENUE;
    case 'type':
      return DB::$TYPE;
    case 'dt_hosts':
    case 'dt_confs':
    case 'dt_boats':
      return array();
    case 'dt_season':
      return DB::$SEASON;
    default:
      return parent::db_type($field);
    }
  }

  public function &__get($name) {
    switch ($name) {
    case 'scorer':
      if ($this->scorer === null) {
        switch ($this->scoring) {
        case Regatta::SCORING_COMBINED:
          require_once('regatta/ICSACombinedScorer.php');
          $this->scorer = new ICSACombinedScorer();
          break;

	case Regatta::SCORING_TEAM:
	  require_once('regatta/ICSATeamScorer.php');
	  $this->scorer = new ICSATeamScorer();
	  break;

        default:
          require_once('regatta/ICSAScorer.php');
          $this->scorer = new ICSAScorer();
        }
      }
      return $this->scorer;

    default:
      return parent::__get($name);
    }
  }

  /**
   * Fetch the object responsible for ranking
   *
   * @return ICSARanker the ranking object
   */
  public function getRanker() {
    if ($this->ranker === null) {
      switch ($this->scoring) {
      case Regatta::SCORING_TEAM:
	require_once('regatta/ICSATeamRanker.php');
        $this->ranker = new ICSATeamRanker();
        break;

      case Regatta::SCORING_COMBINED:
        require_once('regatta/ICSACombinedRanker.php');
        $this->ranker = new ICSACombinedRanker();
        break;

      default:
        require_once('regatta/ICSARanker.php');
        $this->ranker = new ICSARanker();
        break;
      }
    }
    return $this->ranker;
  }

  /**
   * Fetch the ranking object for division-level ranks
   *
   * @return ICSARanker the ranking object
   */
  public function getDivisionRanker() {
    switch ($this->scoring) {
    case Regatta::SCORING_COMBINED:
      if ($this->ranker_division === null) {
        require_once('regatta/ICSASpecialCombinedRanker.php');
        $this->ranker_division = new ICSASpecialCombinedRanker();
      }
      return $this->ranker_division;

    default:
      return $this->getRanker();
    }
  }

  public function getSeason() {
    if ($this->season === null || $this->season_start_time != $this->__get('start_time')) {
      $this->season_start_time = $this->__get('start_time');
      $this->season = Season::forDate($this->season_start_time);
    }
    return $this->season;
  }

  /**
   * Fetches the number of days (inclusive) for this event
   *
   * @return int the number of days
   */
  public function getDuration() {
    $start = $this->__get('start_time');
    if ($start === null)
      return 0;
    $start = new DateTime($start->format('r'));
    $start->setTime(0, 0);
    return 1 + floor(($this->__get('end_date')->format('U') - $start->format('U')) / 86400);
  }

  /**
   * Sets the scoring for this regatta.
   *
   * It is important to use this method instead of setting the scoring
   * directly so that the Regatta can choose the appropriate scorer
   *
   * @param Const the regatta scoring
   */
  public function setScoring($value) {
    if ($value == $this->scoring)
      return;
    $this->scoring = $value;
    $this->scorer = null;
    $this->ranker = null;
    $this->ranker_division = null;
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
    $res = DB::getAll(DB::$DAILY_SUMMARY, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('summary_date', $day->format('Y-m-d')))));
    $r = (count($res) == 0) ? '' : $res[0]->summary;
    unset($res);
    return $r;
  }

  /**
   * Sets the daily summary for the given day
   *
   * @param DateTime $day
   * @param String|null $comment no comment
   */
  public function setSummary(DateTime $day, $comment = null) {
    // Enforce uniqueness
    $res = DB::getAll(DB::$DAILY_SUMMARY, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('summary_date', $day->format('Y-m-d')))));
    if ($comment === null) {
      foreach ($res as $cur)
        DB::remove($cur);
      return;
    }

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
   * Returns an array of the divisions in this regatta
   *
   * @return list of divisions in this regatta
   */
  public function getDivisions() {
    $q = DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->id), array('division'));
    $q->distinct(true);
    $q->order_by(array('division'=>true));
    $q = DB::query($q);
    $divisions = array();
    while ($row = $q->fetch_object()) {
      $divisions[$row->division] = Division::get($row->division);
    }
    return array_values($divisions);
  }

  /**
   * Fetches the team with the given ID, or null
   *
   * @param int $id the id of the team
   * @return Team|null if the team exists
   */
  public function getTeam($id) {
    $res = DB::get($this->isSingleHanded() ? DB::$SINGLEHANDED_TEAM : DB::$TEAM, $id);
    if ($res === null || $res->regatta->id != $this->id)
      return null;
    return $res;
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
    $team->regatta = $this;
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
   * Returns rank objects of the teams in the database.
   *
   * Total the team's score across the given list of races. A
   * tiebreaker procedure should be used after that.
   *
   * If $races is empty, then it will return an empty array.
   *
   * @param Array:Race $races the races to use for the ranking
   * @return Array:Rank the unordered rank objects
   */
  public function getTeamTotals($races) {
    if (count($races) == 0)
      return array();
    $ranks = array();
    foreach ($this->getTeams() as $team) {
      $q = DB::prepGetAll(DB::$FINISH,
                          new DBBool(array(new DBCond('team', $team), new DBCondIn('race', $races))),
                          array(new DBField('score', 'sum', 'total')));
      $q = DB::query($q);
      $r = $q->fetch_object();

      $ranks[] = new Rank($team, $r->total);
    }
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
   * @return Race|null the race object which matches the description
   */
  public function getRace(Division $div, $num) {
    $res = DB::getAll(DB::$RACE, new DBBool(array(new DBCond('regatta', $this->id),
                                                  new DBCond('division', (string)$div),
                                                  new DBCond('number', $num))));
    if (count($res) == 0)
      return null;
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
    if ($r === null || $r->regatta != $this)
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
  private $total_races;

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
   * Ordered list of rounds in the regatta
   *
   * @return Array:int the list of rounds
   */
  public function getRounds() {
    return DB::getAll(DB::$ROUND, new DBCondIn('id', DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->id), array('round'))));
  }

  /**
   * Fetches the list of races in the given round
   *
   * @param int $round not verified to exist in this regatta
   * @param Division $div the specific division (if any)
   * @return Array:Race the list of races
   */
  public function getRacesInRound(Round $round, Division $div = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id),
                             new DBCond('round', $round)));
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
    $race->regatta = $this;
    DB::set($race);
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
    asort($nums, SORT_NUMERIC);
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
   * Fetches the race that was last scored in the regatta, or the
   * specific division if one is provided. This method will look at
   * the timestamp of the first finish in each race to determine which
   * is the latest to be scored.
   *
   * @param Division $div (optional) only look in this division
   * @return Race|null the race or null if none yet scored
   */
  public function getLastScoredRace(Division $div = null) {
    DB::$FINISH->db_set_order(array('entered'=>false));
    $res = DB::getAll(DB::$FINISH,
                      new DBCondIn('race', DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->id), array('id'))));

    if (count($res) == 0)
      $r = null;
    else
      $r = $res[0]->race;
    unset($res);
    DB::$FINISH->db_set_order();
    return $r;
  }

  // ------------------------------------------------------------
  // Team Scoring: race teams
  // ------------------------------------------------------------

  /**
   * Returns the teams (in an array) participating in the given race.
   *
   * This is specially applicable for team racing, where only the race
   * number is of any use. For fleet racing, this is the same as
   * calling getTeams(), regardless of the value of $race.
   *
   * @param Race $race the race whose teams to return
   * @return Array:Team the teams
   * @see setRaceTeams
   */
  public function getRaceTeams(Race $race) {
    if ($race->tr_team1 !== null && $race->tr_team2 !== null)
      return array($race->tr_team1, $race->tr_team2);
    return $this->getTeams();
  }

  /**
   * Sets the teams which will participate in a given race. This is
   * useful for team racing regattas. The teams should be already
   * registered with the regatta.
   *
   * @param Race $race the race whose teams to set
   * @param Team $team1 the first team in the race
   * @param Team $team2 the second team in the race
   *
   * @throws InvalidArgumentException if there is no such race as the
   * one given. This check is done based on race number only. For
   * expediency, no check is done on the identities of the given
   * teams. It is your responsibility to make sure they actually
   * belong to this regatta.
   *   
   * @see getRaceTeams
   */
  public function setRaceTeams(Race $race, Team $team1, Team $team2) {
    foreach ($this->getDivisions() as $division) {
      $race = $this->getRace($division, $race->number);
      $race->tr_team1 = $team1;
      $race->tr_team2 = $team2;
      DB::set($race);
    }
  }

  /**
   * Returns an ordered list of race numbers this team is
   * participating in.
   *
   * This is of particular interest to team race regattas. For fleet
   * racing, this is equivalent to calling getCombinedRaces()
   *
   * @param Team $team the team whose participation to retrieve
   * @param Division $div the specific division
   * @return Array:Race the races
   * @see getCombinedRaces
   */
  public function getRacesForTeam(Division $div, Team $team) {
    $races = $this->getRaces($div);
    if ($this->scoring != Regatta::SCORING_TEAM)
      return $races;
    $list = array();
    foreach ($races as $race) {
      if ($race->tr_team1->id == $team->id || $race->tr_team2->id == $team->id)
        $list[] = $race;
    }
    return $list;
  }

  /**
   * Returns ordered list of rounds team is participating in.
   *
   * @param Team $team the team
   * @return Array:Round the rounds
   * @see getRacesForTeam
   * @throws InvalidArgumentException if regatta type is not scoring
   */
  public function getRoundsForTeam(Team $team) {
    if ($this->scoring != Regatta::SCORING_TEAM)
      throw new InvalidArgumentException("Rounds only applicable to team-racing regattas.");

    return DB::getAll(DB::$ROUND,
		      new DBCondIn('id', DB::prepGetAll(DB::$RACE,
							new DBBool(array(new DBCond('regatta', $this->id),
									 new DBBool(array(new DBCond('tr_team1', $team),
											  new DBCond('tr_team2', $team)),
										    DBBool::mOR))),
							array('round'))));
  }


  // ------------------------------------------------------------
  // Finishes
  // ------------------------------------------------------------

  /**
   * @var Array attempt to cache finishes, index is 'race-team_id'
   */
  private $finishes = array();

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
    $id = $race->id . '-' . $team->id;
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
   * @param Race $race whose finishes to get
   * @return Array a list of ordered finishes in the race. If null,
   * return all the finishes ordered by race, and timestamp.
   *
   */
  public function getFinishes(Race $race) {
    return DB::getAll(DB::$FINISH, new DBCond('race', $race));
  }

  /**
   * Returns an array of finish objects for all the races with the
   * same number across all divisions.
   *
   * @param Race $race whose finishes to get
   * @return Array the list of finishes
   */
  public function getCombinedFinishes(Race $race) {
    $races = DB::prepGetAll(DB::$RACE,
                            new DBBool(array(new DBCond('regatta', $this),
                                             new DBCond('number', $race->number))),
                            array('id'));
    return DB::getAll(DB::$FINISH, new DBCondIn('race', $races));
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
    if ($race === null) {
      return count(DB::getAll(DB::$RACE,
                              new DBBool(array(new DBCond('regatta', $this),                                               
                                               new DBCondIn('id', DB::prepGetAll(DB::$FINISH,
                                                                                 new DBCond('penalty', null, DBCond::NE),
                                                                                 array('race'))))))) > 0;
    }
    return count(DB::getAll(DB::$FINISH,
                            new DBBool(array(new DBCond('penalty', null, DBCond::NE),
                                             new DBCond('race', $race))))) > 0;
  }

  /**
   * Are there finishes for this regatta?
   *
   * @param Race $race optional, if given, returns status for just
   * that race. Otherwise, the whole regatta
   * @return boolean
   */
  public function hasFinishes(Race $race = null) {
    if ($race === null) {
      return count(DB::getAll(DB::$RACE,
                              new DBBool(array(new DBCond('regatta', $this),
                                               new DBCondIn('id', DB::prepGetAll(DB::$FINISH, null, array('race'))))))) > 0;
    }
    return count(DB::getAll(DB::$FINISH, new DBCond('race', $race))) > 0;
  }

  /**
   * Commits the given finishes to the database.
   *
   * @param Array:Finish $finishes the finishes to commit
   * @see setFinishes
   */
  public function commitFinishes(Array $finishes) {
    foreach ($finishes as $finish)
      DB::set($finish, ($finish->id !== null));
  }

  /**
   * Deletes the finishes in the given race, without scoring the
   * regatta.
   *
   * @param Race $race the race whose finishes to drop
   */
  public function deleteFinishes(Race $race) {
    DB::removeAll(DB::$FINISH, new DBCond('race', $race));
  }

  /**
   * Drops all the finishes registered with the given race and
   * rescores the regatta. Respects the regatta scoring option.
   *
   * @param Race $race the race whose finishes to drop
   */
  public function dropFinishes(Race $race) {
    if ($this->scoring == Regatta::SCORING_STANDARD)
      $this->deleteFinishes($race);
    else {
      foreach ($this->getDivisions() as $div)
        $this->deleteFinishes($this->getRace($div, $race->number));
    }
    $this->runScore($race);
  }

  // ------------------------------------------------------------
  // Team penalties
  // ------------------------------------------------------------

  /**
   * Set team penalty
   *
   * @param TeamPenalty $penalty the penalty to register
   */
  public function setTeamPenalty(TeamPenalty $penalty) {
    // Ascertain unique key compliance
    $cur = $this->getTeamPenalty($penalty->team, $penalty->division);
    if ($cur !== null)
      $penalty->id = $cur->id;
    DB::set($penalty);
  }

  /**
   * Drops the team penalty for the given team in the given division
   *
   * @param Team $team the team whose penalty to drop
   * @param Division $div the division to drop
   * @return boolean true if a penalty was dropped
   */
  public function dropTeamPenalty(Team $team, Division $div) {
    $cur = $this->getTeamPenalty($team, $div);
    if ($cur === null)
      return false;
    DB::remove($cur);
    return true;
  }

  /**
   * Returns the team penalty, or null
   *
   * @param Team $team the team
   * @param Division $div the division
   * @return TeamPenalty if one exists, or null otherwise
   */
  public function getTeamPenalty(Team $team, Division $div) {
    $res = $this->getTeamPenalties($team, $div);
    $r = (count($res) == 0) ? null : $res[0];
    unset($res);
    return $r;
  }

  /**
   * Returns list of all the team penalties for the given team, or all
   * if null
   *
   * @param Team $team the team whose penalties to return, or all if null
   * @param Division $div the division to fetch, or all if null
   * @return Array:TeamPenalty list of team penalties
   */
  public function getTeamPenalties(Team $team = null, Division $div = null) {
    $cond = new DBBool(array());
    if ($team === null)
      $cond->add(new DBCondIn('team', DB::prepGetAll(DB::$TEAM, new DBCond('regatta', $this->id), array('id'))));
    else
      $cond->add(new DBCond('team', $team));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::$TEAM_PENALTY, $cond);
  }

  /**
   * Returns the timestamp of the last score update
   *
   * @return DateTime, or null if no update found
   */
  public function getLastScoreUpdate() {
    require_once('public/UpdateRequest.php');
    DB::$UPDATE_REQUEST->db_set_order(array('request_time'=>false));
    $res = DB::getAll(DB::$UPDATE_REQUEST, new DBCond('regatta', $this->id));
    $r = (count($res) == 0) ? null : $res[0]->request_time;
    unset($res);
    DB::$UPDATE_REQUEST->db_set_order();
    return $r;
  }

  // ------------------------------------------------------------
  // Scorers
  // ------------------------------------------------------------

  /**
   * Returns a list of hosts for this regatta
   *
   * @return Array:School a list of hosts
   */
  public function getHosts() {
    return DB::getAll(DB::$SCHOOL,
                      new DBCondIn('id', DB::prepGetAll(DB::$HOST_SCHOOL, new DBCond('regatta', $this->id), array('school'))));
  }

  public function addHost(School $school) {
    // Enforce unique key
    $res = DB::getAll(DB::$HOST_SCHOOL, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('school', $school))));
    if (count($res) > 0)
      return;

    $cur = new Host_School();
    $cur->regatta = $this;
    $cur->school = $school;
    DB::set($cur);
    unset($res);
  }

  /**
   * Removes all the host from the regatta. Careful! Each regatta must
   * have at least one host, so do not forget to ::addHost later
   *
   */
  public function resetHosts() {
    DB::removeAll(DB::$HOST_SCHOOL, new DBCond('regatta', $this->id));
  }

  /**
   * Return a list of scorers for this regatta
   *
   * @return Array:Account a list of scorers
   */
  public function getScorers() {
    return DB::getAll(DB::$ACCOUNT, new DBCondIn('id', DB::prepGetAll(DB::$SCORER, new DBCond('regatta', $this->id), array('account'))));
  }

  /**
   * Registers the given scorer with this regatta
   *
   * @param Account $acc the account to add
   */
  public function addScorer(Account $acc) {
    $res = DB::getAll(DB::$SCORER, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('account', $acc))));
    if (count($res) > 0)
      return;
    $cur = new Scorer();
    $cur->regatta = $this->id;
    $cur->account = $acc;
    DB::set($cur);
    unset($res);
  }

  /**
   * Removes the specified account from this regatta
   *
   * @param Account $acc the account of the scorer to be removed
   * from this regatta
   */
  public function removeScorer(Account $acc) {
    DB::removeAll(DB::$SCORER, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('account', $acc))));
  }

  //------------------------------------------------------------
  // Misc
  // ------------------------------------------------------------

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
   * Determines whether the regatta is a singlehanded regatta or
   * not. Singlehanded regattas consist of one division, and each race
   * consists of single-occupant boats
   *
   * @return boolean is this regatta singlehanded?
   */
  public function isSingleHanded() {
    $divisions = $this->getDivisions();
    if (count($divisions) > 1) return false;

    $res = DB::getAll(DB::$RACE,
                      new DBBool(array(new DBCond('regatta', $this),
                                       new DBCondIn('boat', DB::prepGetAll(DB::$BOAT, new DBCond('occupants', 1, DBCond::GT), array('id'))))));
    $r = (count($res) == 0);
    unset($res);
    return $r;
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
    $this->__get('scorer')->score($this, array($race));
    $this->setRanks($race->division);
  }

  /**
   * Scores the entire regatta
   */
  public function doScore() {
    $this->__get('scorer')->score($this, $this->getScoredRaces());
    $this->setRanks();
  }

  // ------------------------------------------------------------
  // Race notes
  // ------------------------------------------------------------

  /**
   * Fetches a list of all the notes for the given race, or the entire
   * regatta if no race provided
   *
   * @return Array:Note the list of notes
   */
  public function getNotes(Race $race = null) {
    if ($race !== null)
      return DB::getAll(DB::$NOTE, new DBCond('race', $race->id));
    $races = array();
    foreach ($this->getRaces() as $race)
      $races[] = $race->id;
    return DB::getAll(DB::$NOTE, new DBCondIn('race', $races));
  }

  /**
   * Adds the given note to the regatta. Updates the Note object
   *
   * @param Note $note the note to add and update
   */
  public function addNote(Note $note) {
    DB::set($note);
  }

  /**
   * Deletes the given note from the regatta
   *
   * @param Note $note the note to delete
   */
  public function deleteNote(Note $note) {
    DB::remove($note);
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
    $name = strtolower($this->name);
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

    // in the unlikely event that *every* token was a blacklisted
    // element, use the entire token
    if ($name == "")
      $name = implode("-", $tok_copy);

    // eastern -> east
    $name = str_replace("eastern", "east", $name);
    $name = str_replace("western", "west", $name);
    $name = str_replace("northern", "north", $name);
    $name = str_replace("southern", "south", $name);

    // semifinals -> semis
    $name = str_replace("semifinals", "semis", $name);
    $name = str_replace("semifinal",  "semis", $name);

    // list of regatta names in the same season as this one
    $season = $this->getSeason();
    if ($season === null)
      throw new InvalidArgumentException("No season for this regatta.");
    foreach ($season->getRegattas() as $n) {
      if ($n->nick == $name && $n->id != $this->id)
        throw new InvalidArgumentException(sprintf("Nick name \"%s\" already in use by (%d).", $name, $n->id));
    }
    return $name;
  }

  /**
   * Returns the public path to this regatta.
   *
   * The path is /<season>/<name>/
   *
   * @return String the path, calculating it once
   * @throws InvalidArgumentException for regattas that have no
   * nick_names, (i.e. personal regattas)
   */
  public function getURL() {
    if ($this->nick === null)
      throw new InvalidArgumentException("Private regattas are not published.");
    if ($this->url !== null)
      return $this->url;
    $s = $this->getSeason();
    return sprintf('/%s/%s/', $s->id, $this->nick);
  }
  private $url;

  // ------------------------------------------------------------
  // Data caching
  // ------------------------------------------------------------

  /**
   * Cache the information regarding the team ranks
   *
   * This method should be called whenever a race is entered (and is
   * done so implicitly by the runScore and doScore methods) to create
   * a snapshot in the team and dt_team_division tables of the teams
   * and how they ranked.
   *
   * Note that this ONLY applies to fleet racing regattas, as ranking
   * of teams is a manual process in team racing events. Thus, this
   * method will quietly return if the scoring is Team.
   *
   * This method will silently fail if there are no races
   *
   * @param Division $division optional specific division to rank
   */
  public function setRanks(Division $division = null) {
    if ($this->dt_num_races === null)
      $this->setData();
    if ($this->dt_num_divisions == 0 || $this->scoring == Regatta::SCORING_TEAM)
      return;

    // Set the team-level ranking first
    $ranker = $this->getRanker();
    foreach ($ranker->rank($this) as $i => $rank) {
      $rank->team->dt_rank = ($i + 1);
      $rank->team->dt_explanation = $rank->explanation;
      $rank->team->dt_score = $rank->score;
      DB::set($rank->team);
    }

    // ------------------------------------------------------------
    // do the team divisions
    $ranker = $this->getDivisionRanker();
    if ($this->scoring == Regatta::SCORING_STANDARD) {
      $divs = ($division === null ) ? $this->getDivisions() : array($division);

      foreach ($divs as $div) {
        $races = $this->getScoredRaces($div);
        foreach ($ranker->rank($this, $races) as $i => $rank) {
          $team_division = $rank->team->getRank($div);
          if ($team_division === null) {
            $team_division = new Dt_Team_Division();
            $team_division->team = $rank->team;
            $team_division->division = $div;
          }

          $team_division->rank = ($i + 1);
          $team_division->explanation = $rank->explanation;
          $team_division->penalty = null;
          $team_division->comments = null;
          $team_division->score = $rank->score;

          // Penalty?
          if (($pen = $this->getTeamPenalty($rank->team, $div)) !== null) {
            $team_division->penalty = $pen->type;
            $team_division->comments = $pen->comments;
          }
          DB::set($team_division);
        }
      }
    }
    elseif ($this->scoring == Regatta::SCORING_COMBINED) {
      foreach ($ranker->rank($this) as $i => $rank) {
        $team_division = $rank->team->getRank($rank->division);
        if ($team_division === null) {
            $team_division = new Dt_Team_Division();
            $team_division->team = $rank->team;
            $team_division->division = $rank->division;
        }

        $team_division->rank = ($i + 1);
        $team_division->explanation = $rank->explanation;
        $team_division->penalty = null;
        $team_division->comments = null;
        $team_division->score = $rank->score;

        // Penalty?
        if (($pen = $this->getTeamPenalty($rank->team, $rank->division)) !== null) {
          $team_division->penalty = $pen->type;
          $team_division->comments = $pen->comments;
        }
        DB::set($team_division);
      }
    }
    // @TODO: Team racing?

    // Remove extraneous entries
    DB::removeAll(DB::$DT_TEAM_DIVISION, new DBCondIn('division', $this->getDivisions(), DBCondIn::NOT_IN));
  }

  /**
   * Get list of teams in order
   *
   * @param School $school the optional school whose teams to return
   * @return Array:Team the teams in order of their rank
   */
  public function getRankedTeams(School $school = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id)));
    if ($school !== null)
      $cond->add(new DBCond('school', $school));
    return DB::getAll($this->dt_singlehanded ? DB::$RANKED_SINGLEHANDED_TEAM : DB::$RANKED_TEAM, $cond);
  }

  /**
   * Return the teams ranked in the given division
   *
   * @param Division $div the division (optional)
   * @return Array:Dt_Team_Division
   */
  public function getRanks(Division $div = null) {
    $cond = new DBBool(array(new DBCondIn('team',
                                          DB::prepGetAll(DB::$TEAM, new DBCond('regatta', $this), array('id')))));
    if ($div !== null)
      $cond->add(new DBCond('division', $div));
    return DB::getAll(DB::$DT_TEAM_DIVISION, $cond);
  }

  /**
   * Call this method to sync cacheable data about this regatta
   *
   */
  public function setData() {
    $this->dt_num_divisions = count($this->getDivisions());
    if ($this->dt_num_divisions == 0)
      $this->dt_num_races = 0;
    else
      $this->dt_num_races = floor(count($this->getRaces()) / $this->dt_num_divisions);

    // hosts and conferences
    $this->dt_confs = array();
    $this->dt_hosts = array();
    foreach ($this->getHosts() as $host) {
      $this->dt_confs[$host->conference->id] = $host->conference->id;
      $this->dt_hosts[$host->id] = $host->nick_name;
    }

    // boats
    $this->dt_boats = array();
    foreach ($this->getBoats() as $boat)
      $this->dt_boats[$boat->id] = $boat->name;

    $this->dt_singlehanded = ($this->isSingleHanded()) ? 1 : null;
    $this->dt_season = $this->getSeason();

    // status
    $now = new DateTime();
    $end = $this->__get('end_date');
    $end->setTime(23,59,59);
    if ($this->__get('finalized') !== null)
      $this->dt_status = Regatta::STAT_FINAL;
    elseif (count($this->getUnscoredRaces()) == 0)
      $this->dt_status = Regatta::STAT_FINISHED;
    elseif (!$this->hasFinishes()) {
      if ($this->dt_num_races > 0)
        $this->dt_status = Regatta::STAT_READY;
      else
        $this->dt_status = Regatta::STAT_SCHEDULED;
    }
    else {
      $last_race = $this->getLastScoredRace();
      $this->dt_status = ($last_race === null) ? Regatta::STAT_READY : (string)$last_race;
    }

    DB::set($this);
  }

  /**
   * Get the Dt_RP entries for the given sailor.
   *
   * @param Sailor $sailor the sailor whose data to retrieve
   * @param Division $div the specific division, if any
   * @param Const $role the role, if any
   */
  public function getRpData(Sailor $sailor, $division = null, $role = null) {
    $team = DB::prepGetAll(DB::$TEAM, new DBCond('regatta', $this), array('id'));

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

  // ------------------------------------------------------------
  // Regatta creation
  // ------------------------------------------------------------

  /**
   * Creates a new regatta with the given specs
   *
   * @param String $db the database to add the regatta to, must be in
   * the database map ($self::DB_MAP)
   * @param String $name the name of the regatta
   * @param DateTime $start_time the start time of the regatta
   * @param DateTime $end_date the end_date
   * @param Type $type one of the active regatta types
   * @param String $participant one of those listed in Regatta::getParticipantOptions()
   * @param boolean $private true to create a private regatta
   * @return int the ID of the regatta
   *
   * @throws InvalidArgumentException if unable to create nick name, etc.
   */
  public static function createRegatta($name,
                                       DateTime $start_time,
                                       DateTime $end_date,
                                       Active_Type $type,
                                       $scoring = Regatta::SCORING_STANDARD,
                                       $participant = Regatta::PARTICIPANT_COED,
                                       $private = false) {
    $opts = Regatta::getScoringOptions();
    if (!isset($opts[$scoring]))
      throw new InvalidArgumentException("No such regatta scoring $scoring.");
    $opts = Regatta::getParticipantOptions();
    if (!isset($opts[$participant]))
      throw new InvalidArgumentException("No such regatta participant $participant.");

    $r = new Regatta();
    $r->name = $name;
    $r->start_time = $start_time;
    $r->end_date = $end_date;
    $r->end_date->setTime(0, 0);
    $r->setScoring($scoring);
    $r->participant = $participant;
    $r->type = $type;
    if ($private)
      $r->private = 1;
    else
      $r->nick = $r->createNick();
    DB::set($r);
    return $r;
  }

  // ------------------------------------------------------------
  // Comparators
  // ------------------------------------------------------------

  /**
   * Compares two regattas based on start_time
   *
   * @param Regatta $r1 a regatta
   * @param Regatta $r2 a regatta
   */
  public static function cmpStart(Regatta $r1, Regatta $r2) {
    $r1s = $r1->__get('start_time');
    $r2s = $r2->__get('start_time');
    if ($r1s < $r2s)
      return -1;
    if ($r1s > $r2s)
      return 1;
    return 0;
  }

  /**
   * Compares two regattas based on start_time, descending
   *
   * @param Regatta $r1 a regatta
   * @param Regatta $r2 a regatta
   */
  public static function cmpStartDesc(Regatta $r1, Regatta $r2) {
    return -1 * self::cmpStart($r1, $r2);
  }

  /**
   * Compares two regattas based on their type, then their start time
   *
   * @param Regatta $r1 a regatta
   * @param Regatta $r2 a regatta
   * @return int the result of question $r1 < $r2
   */
  public static function cmpTypes(Regatta $r1, Regatta $r2) {
    $diff = $r1->__get('type')->rank - $r2->__get('type')->rank;
    if ($diff == 0)
      return Regatta::cmpStart($r1, $r2);
    return $diff;
  }
}

/**
 * A non-inactive regatta
 *
 * @author Dayan Paez
 * @version 2012-11-26
 */
class Regatta extends FullRegatta {
  public function db_where() { return new DBCond('inactive', null); }
}

/**
 * A non-private regatta: a convenience handle
 *
 * @author Dayan Paez
 * @version 2012-10-26
 */
class Public_Regatta extends Regatta {
  public function db_where() {
    return new DBBool(array(new DBCond('private', null),
                            parent::db_where()));
  }
}

DB::$FULL_REGATTA = new FullRegatta();
DB::$REGATTA = new Regatta();
DB::$PUBLIC_REGATTA = new Public_Regatta();
?>