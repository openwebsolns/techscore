<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */


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
    $lst = array();
    foreach (explode("\0", DB::g(STN::SCORING_OPTIONS)) as $opt) {
      $n = null;
      switch ($opt) {
      case Regatta::SCORING_STANDARD: $n = "Standard"; break;
      case Regatta::SCORING_COMBINED: $n = "Combined divisions"; break;
      case Regatta::SCORING_TEAM:     $n = "Team racing"; break;
      default:                        $n = ucfirst($opt);
      }
      $lst[$opt] = $n;
    }
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
  public $host_venue;
  protected $inactive;
  protected $sponsor;

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
      return DB::T(DB::NOW);
    case 'creator':
      return DB::T(DB::ACCOUNT);
    case 'venue':
      return DB::T(DB::VENUE);
    case 'type':
      return DB::T(DB::TYPE);
    case 'sponsor':
      return DB::T(DB::PUB_SPONSOR);
    case 'dt_hosts':
    case 'dt_confs':
    case 'dt_boats':
      return array();
    case 'dt_season':
      return DB::T(DB::SEASON);
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
   * @return Daily_Summary the summary object
   */
  public function getSummary(DateTime $day) {
    $res = DB::getAll(DB::T(DB::DAILY_SUMMARY), new DBBool(array(new DBCond('regatta', $this->id), new DBCond('summary_date', $day->format('Y-m-d')))));
    $r = (count($res) == 0) ? null : $res[0];
    unset($res);
    return $r;
  }

  /**
   * Sets the daily summary for the given day
   *
   * @param DateTime $day
   * @param Daily_Summary|null $comment no comment
   */
  public function setSummary(DateTime $day, Daily_Summary $comment = null) {
    if ($comment === null)
      $comment = new Daily_Summary();

    // Enforce uniqueness
    $res = DB::getAll(DB::T(DB::DAILY_SUMMARY), new DBBool(array(new DBCond('regatta', $this->id), new DBCond('summary_date', $day->format('Y-m-d')))));
    if (count($res) > 0)
      $comment->id = $res[0]->id;

    $comment->regatta = $this->id;
    $comment->summary_date = $day;
    DB::set($comment);
  }

  /**
   * Returns an array of the divisions in this regatta
   *
   * @return list of divisions in this regatta
   */
  public function getDivisions() {
    if ($this->scoring == Regatta::SCORING_TEAM)
      return array(Division::A(), Division::B(), Division::C());

    $q = DB::prepGetAll(DB::T(DB::RACE), new DBCond('regatta', $this->id), array('division'));
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
   * Get effective number of divisions
   *
   * A regatta has effectively 1 division if any of these conditions
   * apply:
   *
   *   - Only division A exists
   *   - Divisions sail combined (i.e. combined or team racing)
   *
   */
  public function getEffectiveDivisionCount() {
    if ($this->scoring != self::SCORING_STANDARD)
      return 1;
    return count($this->getDivisions());
  }

  /**
   * Fetches the team with the given ID, or null
   *
   * @param int $id the id of the team
   * @return Team|null if the team exists
   */
  public function getTeam($id) {
    $res = DB::get($this->isSingleHanded() ? DB::T(DB::SINGLEHANDED_TEAM) : DB::T(DB::TEAM), $id);
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
   * Gets a list of schools in the regatta
   *
   * @return Array:School the schools
   */
  public function getSchools() {
    return DB::getAll(DB::T(DB::SCHOOL),
		      new DBCondIn('id', DB::prepGetAll(DB::T(DB::TEAM), new DBCond('regatta', $this->id), array('school'))));
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
    $res = DB::getAll($this->isSingleHanded() ? DB::T(DB::SINGLEHANDED_TEAM) : DB::T(DB::TEAM), $cond);
    if ($school !== null)
      return $res;

    $list = array();
    foreach ($res as $team)
      $list[] = $team;
    usort($list, 'Team::compare');
    return $list;
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
      $q = DB::prepGetAll(DB::T(DB::FINISH),
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
    $res = DB::getAll(DB::T(DB::RACE), new DBBool(array(new DBCond('regatta', $this->id),
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
    $r = DB::get(DB::T(DB::RACE), $id);
    if ($r === null || $r->regatta != $this)
      return null;
    return $r;
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
    return DB::getAll(DB::T(DB::RACE), $cond);
  }

  /**
   * Returns the set of teams participating in the round
   *
   * @param Round $round the round
   * @return Array:Team the teams that are participating
   */
  public function getTeamsInRound(Round $round) {
    if ($round->regatta->id != $this->id)
      throw new InvalidArgumentException("The round must be from this regatta.");
    return DB::getAll($this->isSingleHanded() ? DB::T(DB::SINGLEHANDED_TEAM) : DB::T(DB::TEAM),
                      new DBBool(array(new DBCondIn('id', DB::prepGetAll(DB::T(DB::RACE), new DBCond('round', $round),
                                                                         array('tr_team1'))),
                                       new DBCondIn('id', DB::prepGetAll(DB::T(DB::RACE), new DBCond('round', $round),
                                                                         array('tr_team2')))),
                                 DBBool::mOR));
  }

  /**
   * Gets the grouped rounds
   *
   * @return Array:Round_Group
   */
  public function getRoundGroups() {
    return DB::getAll(DB::T(DB::ROUND_GROUP),
                      new DBCondIn('id',
                                   DB::prepGetAll(DB::T(DB::ROUND), new DBCond('regatta', $this->id), array('round_group'))));
  }

  /**
   * Ordered list of rounds in the regatta
   *
   * @return Array:Round the list of rounds
   */
  public function getRounds() {
    return DB::getAll(DB::T(DB::ROUND), new DBCond('regatta', $this->id));
  }

  /**
   * Fetches the list of races in the given round
   *
   * @param Round $round the round
   * @param Division $div the specific division (if any)
   *
   * @return Array:Race the list of races
   */
  public function getRacesInRound(Round $round, Division $div = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id),
                             new DBCond('round', $round)));

    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::T(DB::RACE), $cond);
  }

  /**
   * Fetches all races in given group of rounds
   *
   * @param Round_Group the group of rounds
   * @param Division $div the specific division (if any)
   *
   * @return Array:Race the list of races
   */
  public function getRacesInRoundGroup(Round_Group $group, Division $div = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id),
                             new DBCondIn('round', DB::prepGetAll(DB::T(DB::ROUND), new DBCond('round_group', $group), array('id')))));

    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::T(DB::RACE), $cond);
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
    return DB::getAll(DB::T(DB::BOAT), new DBCondIn('id', DB::prepGetAll(DB::T(DB::RACE), $cond, array('boat'))));
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
    DB::removeAll(DB::T(DB::RACE), new DBBool(array(new DBCond('regatta', $this->id),
                                              new DBCond('division', (string)$race->division),
                                              new DBCond('number', $race->number))));
  }

  /**
   * Removes all the races from the given division
   *
   * @param Division $div the division whose races to remove
   */
  public function removeDivision(Division $div) {
    DB::removeAll(DB::T(DB::RACE), new DBBool(array(new DBCond('regatta', $this->id), new DBCond('division', (string)$div))));
  }

  /**
   * Returns a list of races in the given division which are unscored
   *
   * @param Division $div the division. If null, return all unscored races
   * @return Array<Race> a list of races
   */
  public function getUnscoredRaces(Division $div = null) {
    DB::T(DB::RACE)->db_set_order(array('number'=>true, 'division'=>true));

    $cond = new DBBool(array(new DBCond('regatta', $this->id),
                             new DBCondIn('id', DB::prepGetAll(DB::T(DB::FINISH), null, array('race')), DBCondIn::NOT_IN)));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    $res = DB::getAll(DB::T(DB::RACE), $cond);

    DB::T(DB::RACE)->db_set_order();
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
                             new DBCondIn('id', DB::prepGetAll(DB::T(DB::FINISH), null, array('race')))));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::T(DB::RACE), $cond);
  }

  /**
   * Returns the rounds that have at least one scored race
   *
   * This does not include rounds for which the only scored races are
   * carried over from previous rounds.
   *
   * @return Array:Round the list of (partially) scored rounds
   */
  public function getScoredRounds() {
    return DB::getAll(DB::T(DB::ROUND),
                      new DBCondIn('id',
                                   DB::prepGetAll(DB::T(DB::RACE),
                                                  new DBBool(array(new DBCond('regatta', $this->id),
                                                                   new DBCondIn('id',
                                                                                DB::prepGetAll(DB::T(DB::FINISH), null,
                                                                                               array('race'))))),
                                                  array('round'))));
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
    DB::T(DB::FINISH)->db_set_order(array('entered'=>false));
    $res = DB::getAll(DB::T(DB::FINISH),
                      new DBCondIn('race', DB::prepGetAll(DB::T(DB::RACE), new DBCond('regatta', $this->id), array('id'))));

    if (count($res) == 0)
      $r = null;
    else
      $r = $res[0]->race;
    unset($res);
    DB::T(DB::FINISH)->db_set_order();
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
    if ($this->scoring == Regatta::SCORING_TEAM) {
      $list = array();
      if ($race->tr_team1 !== null)
        $list[] = $race->tr_team1;
      if ($race->tr_team2 !== null)
        $list[] = $race->tr_team2;
      return $list;
    }
    else
      return $this->getTeams();
  }

  /**
   * Returns an ordered list of race numbers this team is
   * participating in.
   *
   * This is of particular interest to team race regattas. For fleet
   * racing, this is equivalent to calling getRaces()
   *
   * @param Team $team the team whose participation to retrieve
   * @param Division $div the specific division
   * @return Array:Race the races
   * @see getRaces
   */
  public function getRacesForTeam(Division $div, Team $team) {
    $races = $this->getRaces($div);
    if ($this->scoring != Regatta::SCORING_TEAM)
      return $races;
    $list = array();
    foreach ($races as $race) {
      if ($race->tr_team1 !== null && $race->tr_team1->id == $team->id)
        $list[] = $race;
      elseif ($race->tr_team2 !== null && $race->tr_team2->id == $team->id)
        $list[] = $race;
    }
    return $list;
  }

  /**
   * Like getScoredRaces, but for a specific team
   *
   * This is indeed identical to getScoredRaces for non-team scoring
   * regattas.
   *
   * @param Division $div the specific division
   * @param Team $team the specific team
   * @return Array
   */
  public function getScoredRacesForTeam(Division $div, Team $team) {
    if ($this->scoring == Regatta::SCORING_TEAM) {
      return DB::getAll(DB::T(DB::RACE),
                        new DBBool(array(new DBCond('regatta', $this->id),
                                         new DBCond('division', $div),
                                         new DBCondIn('id', DB::prepGetAll(DB::T(DB::FINISH), new DBCond('team', $team->id), array('race'))))));
    }
    else {
      return $this->getScoredRaces($div);
    }
  }

  /**
   * Returns races in which the given teams sailed against each other.
   *
   * This is particularly useful for team racing regattas.
   *
   * @param Team $t1 one of the teams
   * @param Team $t2 the second team
   * @param Round $round the optional round to limit races
   * @param Division $div the optional division to limit
   */
  public function getRacesForMatchup(Team $t1, Team $t2, Round $round = null, Division $div = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id),
                             new DBBool(array(new DBBool(array(new DBCond('tr_team1', $t1),
                                                               new DBCond('tr_team2', $t2))),
                                              new DBBool(array(new DBCond('tr_team1', $t2),
                                                               new DBCond('tr_team2', $t1)))),
                                        DBBool::mOR)));
    if ($round !== null)
      $cond->add(new DBCond('round', $round));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::T(DB::RACE), $cond);
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
    $id = sprintf('%s-%s', $race->id, $team->id);
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
    $res = DB::getAll(DB::T(DB::FINISH), new DBBool(array(new DBCond('race', $race), new DBCond('team', $team))));
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
    return DB::getAll(DB::T(DB::FINISH), new DBCond('race', $race));
  }

  /**
   * Returns an array of finish objects for all the races with the
   * same number across all divisions.
   *
   * @param Race $race whose finishes to get
   * @return Array the list of finishes
   */
  public function getCombinedFinishes(Race $race) {
    $races = DB::prepGetAll(DB::T(DB::RACE),
                            new DBBool(array(new DBCond('regatta', $this),
                                             new DBCond('number', $race->number))),
                            array('id'));
    return DB::getAll(DB::T(DB::FINISH), new DBCondIn('race', $races));
  }

  /**
   * Returns all the finishes which have been "penalized" in one way
   * or another. That is, they have either a penalty or a breakdown
   *
   * @return Array:Finish the list of finishes, regardless of race
   */
  public function getPenalizedFinishes() {
    return DB::getAll(DB::T(DB::FINISH),
                      new DBBool(array(new DBCondIn('race', DB::prepGetAll(DB::T(DB::RACE), new DBCond('regatta', $this->id), array('id'))),
                                       new DBCondIn('id', DB::prepGetAll(DB::T(DB::FINISH_MODIFIER), null, array('finish'))))));
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
    return DB::getAll(DB::T(DB::FINISH),
                      new DBBool(array(new DBCondIn('race',
                                                    DB::prepGetAll(DB::T(DB::RACE),
                                                                   new DBBool(array(new DBCond('regatta', $this->id),
                                                                                    new DBCond('division', (string)$div))),
                                                                   array('id'))),
                                       new DBCondIn('id',
                                                    DB::prepGetAll(DB::T(DB::FINISH_MODIFIER),
                                                                   new DBBool(array(new DBCondIn('type', array(Breakdown::BKD, Breakdown::RDG, Breakdown::BYE)),
                                                                                    new DBCond('amount', 0, DBCond::LE))),
                                                                   array('finish'))))));
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
    if ($race === null)
      $cond = new DBCondIn('race', DB::prepGetAll(DB::T(DB::RACE), new DBCond('regatta', $this->id), array('id')));
    else
      $cond = new DBCond('race', $race);
    return count(DB::getAll(DB::T(DB::FINISH_MODIFIER),
                            new DBCondIn('finish', DB::prepGetAll(DB::T(DB::FINISH), $cond, array('id'))))) > 0;
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
      return count(DB::getAll(DB::T(DB::RACE),
                              new DBBool(array(new DBCond('regatta', $this),
                                               new DBCondIn('id', DB::prepGetAll(DB::T(DB::FINISH), null, array('race'))))))) > 0;
    }
    return count(DB::getAll(DB::T(DB::FINISH), new DBCond('race', $race))) > 0;
  }

  /**
   * Commits the given finishes to the database.
   *
   * @param Array:Finish $finishes the finishes to commit
   * @see setFinishes
   */
  public function commitFinishes(Array $finishes) {
    foreach ($finishes as $finish) {
      DB::set($finish, ($finish->id !== null));
      if ($finish->hasChangedModifier()) {
        $modifiers = $finish->getModifiers();
        DB::removeAll(DB::T(DB::FINISH_MODIFIER), new DBCond('finish', $finish->id));
        foreach ($modifiers as $mod) {
          DB::set($mod);
        }
      }
      $this->finishes[$finish->race->id . '-' . $finish->team->id] = $finish;
    }
  }

  /**
   * Deletes the finishes in the given race, without scoring the
   * regatta.
   *
   * @param Race $race the race whose finishes to drop
   */
  public function deleteFinishes(Race $race) {
    DB::removeAll(DB::T(DB::FINISH), new DBCond('race', $race));
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
      $cond->add(new DBCondIn('team', DB::prepGetAll(DB::T(DB::TEAM), new DBCond('regatta', $this->id), array('id'))));
    else
      $cond->add(new DBCond('team', $team));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::T(DB::TEAM_PENALTY), $cond);
  }

  /**
   * Returns the timestamp of the last score update
   *
   * @return DateTime, or null if no update found
   */
  public function getLastScoreUpdate() {
    require_once('public/UpdateRequest.php');
    DB::T(DB::UPDATE_REQUEST)->db_set_order(array('request_time'=>false));
    $res = DB::getAll(DB::T(DB::UPDATE_REQUEST), new DBCond('regatta', $this->id));
    $r = (count($res) == 0) ? null : $res[0]->request_time;
    unset($res);
    DB::T(DB::UPDATE_REQUEST)->db_set_order();
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
    return DB::getAll(DB::T(DB::SCHOOL),
                      new DBCondIn('id', DB::prepGetAll(DB::T(DB::HOST_SCHOOL), new DBCond('regatta', $this->id), array('school'))));
  }

  public function addHost(School $school) {
    // Enforce unique key
    $res = DB::getAll(DB::T(DB::HOST_SCHOOL), new DBBool(array(new DBCond('regatta', $this->id), new DBCond('school', $school))));
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
    DB::removeAll(DB::T(DB::HOST_SCHOOL), new DBCond('regatta', $this->id));
  }

  /**
   * Returns suitable host venue for this regatta
   *
   * If ALLOW_HOST_VENUE is on and $this->host_venue is not null, then
   * return that value. Otherwise, an implosion of hosts nick_names
   *
   * @return String
   */
  public function getHostVenue() {
    if (DB::g(STN::ALLOW_HOST_VENUE) && $this->host_venue !== null)
      return $this->host_venue;

    if ($this->dt_hosts !== null)
      return implode("/", $this->__get('dt_hosts'));

    $schools = array();
    foreach ($this->getHosts() as $host)
      $schools[$host->id] = $host->nick_name;
    return implode("/", $schools);
  }

  /**
   * Return a list of scorers for this regatta
   *
   * @return Array:Account a list of scorers
   */
  public function getScorers() {
    return DB::getAll(DB::T(DB::ACCOUNT), new DBCondIn('id', DB::prepGetAll(DB::T(DB::SCORER), new DBCond('regatta', $this->id), array('account'))));
  }

  /**
   * Registers the given scorer with this regatta
   *
   * @param Account $acc the account to add
   */
  public function addScorer(Account $acc) {
    $res = DB::getAll(DB::T(DB::SCORER), new DBBool(array(new DBCond('regatta', $this->id), new DBCond('account', $acc))));
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
    DB::removeAll(DB::T(DB::SCORER), new DBBool(array(new DBCond('regatta', $this->id), new DBCond('account', $acc))));
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
    if ($this->scoring != Regatta::SCORING_STANDARD)
      return false;

    $divisions = $this->getDivisions();
    if (count($divisions) != 1)
      return false;

    $res = DB::getAll(DB::T(DB::RACE),
                      new DBBool(array(new DBCond('regatta', $this),
                                       new DBCondIn('boat', DB::prepGetAll(DB::T(DB::BOAT), new DBCond('max_crews', 0, DBCond::GT), array('id'))))));
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
    $this->setStatus();
    if ($this->scoring == Regatta::SCORING_STANDARD)
      $this->setRanks($race->division);
    else
      $this->setRanks();
  }

  /**
   * Scores the entire regatta
   */
  public function doScore() {
    $this->__get('scorer')->score($this, $this->getScoredRaces());
    $this->setStatus();
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
      return DB::getAll(DB::T(DB::NOTE), new DBCond('race', $race->id));
    $races = array();
    foreach ($this->getRaces() as $race)
      $races[] = $race->id;
    return DB::getAll(DB::T(DB::NOTE), new DBCondIn('race', $races));
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
   * regatta's name. Nick names are guaranteed to be unique per
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
    // list of regatta names in the same season as this one
    $season = $this->getSeason();
    if ($season === null)
      throw new InvalidArgumentException("No season for this regatta.");

    $name = DB::slugify($this->name, true, array(
                          "the", "of", "for", "and", "an", "in", "is", "at",
                          "trophy", "championship", "intersectional",
                          "college", "university", "regatta", "memorial",
                          "professor"));

    // eastern -> east
    $name = str_replace("eastern", "east", $name);
    $name = str_replace("western", "west", $name);
    $name = str_replace("northern", "north", $name);
    $name = str_replace("southern", "south", $name);

    // semifinals -> semis
    $name = str_replace("semifinals", "semis", $name);
    $name = str_replace("semifinal",  "semis", $name);

    // Any other with this exact name?
    $other = $season->getRegattaWithURL($name);
    if ($other !== null && $other->id != $this->id)
      throw new InvalidArgumentException(sprintf("Nick name \"%s\" already in use by (%d).", $name, $other->id));
    return $name;
  }

  /**
   * Returns the public path to this regatta.
   *
   * The path is /<season>/<name>/
   *
   * @param Season $season optional season override for URL generation
   * @return String the path, calculating it once
   * @throws InvalidArgumentException for regattas that have no
   * nick_names, (i.e. personal regattas)
   */
  public function getURL(Season $season = null) {
    if ($this->nick === null)
      throw new InvalidArgumentException("Private regattas are not published.");
    if ($season === null)
      $season = $this->getSeason();
    return sprintf('%s%s/', $season->getURL(), $this->nick);
  }

  // ------------------------------------------------------------
  // Data caching
  // ------------------------------------------------------------

  public function setDivisionRank(Division $div, Rank $rank) {
    $team_division = $rank->team->getRank($div);
    if ($team_division === null) {
      $team_division = new Dt_Team_Division();
      $team_division->team = $rank->team;
      $team_division->division = (string)$div;
    }

    $team_division->rank = $rank->rank;
    $team_division->explanation = $rank->explanation;
    $team_division->score = $rank->score;
    $team_division->wins = $rank->wins;
    $team_division->losses = $rank->losses;
    $team_division->ties = $rank->ties;

    $team_division->penalty = null;
    $team_division->comments = null;

    // Penalty?
    if (($pen = $this->getTeamPenalty($rank->team, $div)) !== null) {
      $team_division->penalty = $pen->type;
      $team_division->comments = $pen->comments;
    }
    DB::set($team_division);
  }

  /**
   * Cache the information regarding the team ranks
   *
   * This method should be called whenever a race is entered (and is
   * done so implicitly by the runScore and doScore methods) to create
   * a snapshot in the team and dt_team_division tables of the teams
   * and how they ranked.
   *
   * This method will silently fail if there are no races
   *
   * @param Division $division optional specific division to rank
   */
  public function setRanks(Division $division = null) {
    if ($this->dt_num_races === null)
      $this->setData();
    if ($this->dt_num_divisions == 0)
      return;

    // ------------------------------------------------------------
    // Set the team-level ranking first:
    //
    // For team racing, every "boat" or "division" receives the same
    // rank as the team itself, so do those rankings here as well
    $divs = ($division === null ) ? $this->getDivisions() : array($division);

    $ranked_teams = array();
    $ranker = $this->getRanker();
    foreach ($ranker->rank($this) as $rank) {
      $rank->team->dt_rank = $rank->rank;
      $rank->team->dt_score = $rank->score;
      $rank->team->dt_explanation = $rank->explanation;

      if ($this->scoring == Regatta::SCORING_TEAM) {
        $rank->team->dt_rank = $rank->rank;
        $rank->team->dt_wins = $rank->wins;
        $rank->team->dt_losses = $rank->losses;
        $rank->team->dt_ties = $rank->ties;

        foreach ($divs as $div) {
          $this->setDivisionRank($div, $rank);
        }
      }
      $ranked_teams[$rank->team->id] = $rank->team;
      DB::set($rank->team);
    }

    // In team racing, the ranker may not return all teams, so reset
    // the record for the remaining teams
    if ($this->scoring == Regatta::SCORING_TEAM) {
      foreach ($this->getTeams() as $team) {
        if (!isset($ranked_teams[$team->id])) {
          $team->dt_rank = count($ranked_teams) + 1;
          $team->dt_wins = 0;
          $team->dt_losses = 0;
          $team->dt_ties = 0;
          $team->dt_explanation = "";

          $rank = new TeamRank($team);
          $rank->rank = $team->dt_rank;
          $rank->wins = 0;
          $rank->losses = 0;
          $rank->ties = 0;
          $rank->explanation = $team->dt_explanation;
          foreach ($divs as $div) {
            $this->setDivisionRank($div, $rank);
          }
          DB::set($rank->team);
        }
      }
    }

    // ------------------------------------------------------------
    // do the team divisions
    $ranker = $this->getDivisionRanker();
    if ($this->scoring == Regatta::SCORING_STANDARD) {
      foreach ($divs as $div) {
        $races = $this->getScoredRaces($div);
        if (count($races) == 0) {
          // Delete any possible caching for this division
          DB::removeAll(DB::T(DB::DT_TEAM_DIVISION), new DBBool(array(new DBCond('division', $div),
                                                                new DBCondIn('team', $this->getTeams()))));
          continue;
        }
        foreach ($ranker->rank($this, $races) as $rank) {
          $this->setDivisionRank($div, $rank);
        }
      }
    }
    if ($this->scoring == Regatta::SCORING_COMBINED) {
      foreach ($ranker->rank($this) as $rank) {
        $this->setDivisionRank($rank->division, $rank);
      }
    }
    
    // Remove extraneous entries
    $keep = array();
    foreach ($this->getDivisions() as $div)
      $keep[(string)$div] = $div;
    $cond = new DBCondIn('team', DB::prepGetAll(DB::T(DB::TEAM), new DBCond('regatta', $this), array('id')));
    foreach (DB::getAll(DB::T(DB::DT_TEAM_DIVISION), $cond) as $team_div) {
      if (!isset($keep[$team_div->division]))
        DB::remove($team_div);
    }
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
    return DB::getAll($this->dt_singlehanded ? DB::T(DB::RANKED_SINGLEHANDED_TEAM) : DB::T(DB::RANKED_TEAM), $cond);
  }

  /**
   * Return the teams ranked in the given division
   *
   * @param Division $div the division (optional)
   * @return Array:Dt_Team_Division
   */
  public function getRanks(Division $div = null) {
    $cond = new DBBool(array(new DBCondIn('team',
                                          DB::prepGetAll(DB::T(DB::TEAM), new DBCond('regatta', $this), array('id')))));
    if ($div !== null)
      $cond->add(new DBCond('division', $div));
    return DB::getAll(DB::T(DB::DT_TEAM_DIVISION), $cond);
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
    $this->dt_status = $this->calcStatus();
    DB::set($this);
  }

  /**
   * Shortcut for committing the dt_status property
   *
   * This function should be called whenever the status of the regatta
   * needs to be updated. It is a shortcut to avoid calling setData.
   *
   */
  public function setStatus() {
    if ($this->dt_num_races === null)
      $this->setData();
    else {
      $this->dt_status = $this->calcStatus();
      DB::set($this);
    }
  }

  /**
   * Determines the correct value to use for dt_status property
   *
   * Will return one of the class constants, or possibly the last
   * scored race.
   *
   * @return String
   */
  public function calcStatus() {
    $now = new DateTime();
    $end = $this->__get('end_date');
    $end->setTime(23,59,59);
    if ($this->__get('finalized') !== null)
      return Regatta::STAT_FINAL;
    elseif (!$this->hasFinishes()) {
      if ($this->dt_num_races > 0)
        return Regatta::STAT_READY;
      return Regatta::STAT_SCHEDULED;
    }
    elseif (count($this->getUnscoredRaces()) == 0)
      return Regatta::STAT_FINISHED;
    else {
      $last_race = $this->getLastScoredRace();
      return ($last_race === null) ? Regatta::STAT_READY : (string)$last_race;
    }
  }

  /**
   * Get the Dt_RP entries for the given sailor.
   *
   * @param Sailor $sailor the sailor whose data to retrieve
   * @param Division $div the specific division, if any
   * @param Const $role the role, if any
   */
  public function getRpData(Sailor $sailor, $division = null, $role = null) {
    $team = DB::prepGetAll(DB::T(DB::TEAM), new DBCond('regatta', $this), array('id'));

    $cond = new DBBool(array(new DBCondIn('team', $team)));
    if ($division !== null)
      $cond->add(new DBCond('division', $division));
    $tdiv = DB::prepGetAll(DB::T(DB::DT_TEAM_DIVISION), $cond, array('id'));

    $cond = new DBBool(array(new DBCondIn('team_division', $tdiv),
                             new DBCond('sailor', $sailor->id)));
    if ($role !== null)
      $cond->add(new DBCond('boat_role', $role));
    return DB::getAll(DB::T(DB::DT_RP), $cond);
  }

  /**
   * Determine whether all team's RP information is complete
   *
   * Looks at the cached information only. May be misleading if there
   * are no teams in the regatta.
   *
   * @return boolean true if every team claims to be complete
   */
  public function isRpComplete() {
    $res = $this->getTeamsMissingRpComplete();
    return count($res) == 0;
  }

  /**
   * Which of this regatta's teams are missing RP?
   *
   * Calculation is done based on saved status (dt_complete)
   *
   * @return Array:Team
   */
  public function getTeamsMissingRpComplete() {
    return DB::getAll(
      DB::T(DB::TEAM),
      new DBBool(
        array(
          new DBCond('regatta', $this->id),
          new DBCond('dt_complete_rp', null),
        )
      )
    );
  }

  /**
   * Sets the Dt_RP entries for this regatta, or particular division
   *
   * @param Division $division the optional division to set
   */
  public function setRpData(Division $division = null) {
    if ($this->dt_num_races === null)
      $this->setData();
    if ($this->dt_num_divisions == 0)
      return;

    $rpm = $this->getRpManager();
    // ------------------------------------------------------------
    // For team racing, a sailor's record is equal to his team's
    // overall record in that regatta, as dictated by
    // dt_team_division. There is no way to partially rank a team
    // racing regatta.
    if ($this->scoring == Regatta::SCORING_TEAM) {
      $ranks = $this->getRanks();
      if (count($ranks) == 0) {
        $this->setRanks();
        $ranks = $this->getRanks();
      }
      foreach ($ranks as $rank) {
        $div = new Division($rank->division);
        $rank->team->resetRpData($div);
        foreach (array(RP::SKIPPER, RP::CREW) as $role) {
          foreach ($rpm->getRP($rank->team, $div, $role) as $rp) {
            if ($rp->sailor !== null) {
              $drp = new Dt_Rp();
              $drp->sailor = $rp->sailor;
              $drp->team_division = $rank;
              $drp->boat_role = $role;
              $drp->race_nums = $rp->races_nums;
              $drp->rank = $rank->rank;
              $drp->explanation = $rank->explanation;
              DB::set($drp);
            }
          }
        }
      }
      return;
    }

    $team_divs = array();
    $scored_nums = array();
    $scored_races = array();

    // An attempt to minimize the amount of times a partial regatta
    // needs to be ranked. For each division as key, contains a map of
    // comma-delimited race numbers => list of ranks
    $scored_ranks = array();

    $divisions = ($division === null) ? $this->getDivisions() : array($division);

    foreach ($divisions as $div) {
      $scored_races[(string)$div] = $this->getScoredRaces($div);
      $scored_ranks[(string)$div] = array();
      $scored_nums[(string)$div] = array();
      foreach ($scored_races[(string)$div] as $race)
        $scored_nums[(string)$div][] = $race->number;
      foreach ($this->getRanks($div) as $team) {
        $team_divs[] = $team;
        $team_objs[$team->id] = $this->getTeam($team->team->id);
      }
    }

    $ranker = $this->getDivisionRanker();

    foreach ($team_divs as $team) {
      $team->team->resetRpData(new Division($team->division));
      foreach (array(RP::SKIPPER, RP::CREW) as $role) {
        $division = Division::get($team->division);
        $rps = $rpm->getRP($team_objs[$team->id], $division, $role);
        foreach ($rps as $rp) {
          if ($rp->sailor !== null) {
            $drp = new Dt_Rp();
            $drp->sailor = $rp->sailor;
            $drp->team_division = $team;
            $drp->boat_role = $role;
            $drp->race_nums = $rp->races_nums;

            // rank: assign the team's rank if participating in every
            // scored race, otherwise, rank in only those races.
            $intersection = array_intersect($scored_nums[$team->division], $rp->races_nums);
            if ($intersection == $scored_nums[$team->division]) {
              $drp->rank = $team->rank;
              $drp->explanation = $team->explanation;
            }
            elseif (count($intersection) == 0) {
              // non-participation == non-inclusion
              continue;
            }
            else {
              $id = implode(',', $intersection);
              if (!isset($scored_ranks[$team->division][$id])) {
                $races = array();
                foreach ($intersection as $num)
                  $races[] = $this->getRace($division, $num);
                $scored_ranks[$team->division][$id] = $ranker->rank($this, $races);
              }
              foreach ($scored_ranks[$team->division][$id] as $rank) {
                if ($rank->team->id == $team->team->id &&
                    ($rank->division === null || (string)$rank->division == (string)$team->division)) {
                  $drp->rank = $rank->rank;
                  $drp->explanation = $rank->explanation;
                  break;
                }
              }
            }
            DB::set($drp);
          }
        }
      }
    }
  }

  /**
   * Fetches human-readable representation of scoring
   *
   * The data scoring is arrived using dt_scoring, dt_singlehanded,
   * and dt_num_divisions, so it is important that setData exist for
   * this purpose.
   *
   * The possible resulting values are:
   *
   *   - Singlehanded
   *   - 1 Division
   *   - 2 Divisions
   *   - 3 Divisions
   *   - 4 Divisions
   *   - Combined
   *   - Team
   *   - NULL if no divisions available!
   *
   * @return String the scoring representation
   */
  public function getDataScoring() {
    if ($this->dt_num_divisions === null)
      $this->setData();
    if ($this->dt_num_divisions == 0)
      return null;

    if ($this->dt_singlehanded)
      return "Singlehanded";
    if ($this->scoring == Regatta::SCORING_COMBINED)
      return "Combined";
    if ($this->scoring == Regatta::SCORING_TEAM)
      return "Team";
    switch ($this->dt_num_divisions) {
    case 1: return "1 Division";
    case 2: return "2 Divisions";
    case 3: return "3 Divisions";
    case 4: return "4 Divisions";
    }
    return null;
  }

  // ------------------------------------------------------------
  // Rank groups
  // ------------------------------------------------------------

  /**
   * Return the teams grouped by ordered rank groups
   *
   * @param Array:Team $teams the optional list of teams to limit to
   * @return Array:Array:Team
   */
  public function getTeamsInRankGroups(Array $teams = array()) {
    $groups = array();
    $ungrouped = array();
    foreach ($this->getTeams() as $team) {
      if (count($teams) > 0) {
        $in_list = false;
        foreach ($teams as $t) {
          if ($t->id == $team->id) {
            $in_list = true;
            break;
          }
        }
        if (!$in_list)
          continue;
      }

      if ($team->rank_group === null)
        $ungrouped[] = $team;
      else {
        if (!isset($groups[$team->rank_group]))
          $groups[$team->rank_group] = array();
        $groups[$team->rank_group][] = $team;
      }
    }
    ksort($groups, SORT_NUMERIC);
    $groups = array_values($groups);
    if (count($ungrouped) > 0)
      $groups[] = $ungrouped;
    return $groups;
  }

  /**
   * Removes all the rank groups in use for this regatta
   *
   */
  public function dissolveRankGroups() {
    foreach ($this->getTeams() as $team) {
      $team->rank_group = null;
      DB::set($team, true);
    }
  }

  // ------------------------------------------------------------
  // Used team rotation templates
  // ------------------------------------------------------------

  /**
   * Get all the saved rotations for this regatta
   *
   * @return Array:Round
   */
  public function getTeamRotations() {
    $list = array();
    foreach ($this->getRounds() as $round) {
      if ($round->rotation !== null)
        $list[] = $round;
    }
    return $list;
  }

  // ------------------------------------------------------------
  // Public URL cache
  // ------------------------------------------------------------

  private $url_cache;

  /**
   * Retrieves URLs from saved cache
   *
   * @return Array:String the "relative" URLs
   */
  public function getPublicPages() {
    if ($this->url_cache === null) {
      $this->url_cache = array();
      foreach (DB::getAll(DB::T(DB::PUB_REGATTA_URL), new DBCond('regatta', $this->id)) as $url)
        $this->url_cache[] = $url->url;
    }
    return $this->url_cache;
  }

  /**
   * Stores the given list of URLs in cache
   *
   * @param Array:String $urls the list of "relative" URLs
   * @see calculatePublicPages
   */
  public function setPublicPages(Array $urls) {
    $objs = array();
    $this->url_cache = array();
    foreach ($urls as $url) {
      $obj = new Pub_Regatta_Url();
      $obj->regatta = $this;
      $obj->url = $url;
      $objs[] = $obj;
      $this->url_cache[] = $url;
    }
    DB::removeAll(DB::T(DB::PUB_REGATTA_URL), new DBCond('regatta', $this->id));
    if (count($objs) > 0)
      DB::insertAll($objs);
  }

  /**
   * Create list of URLs that should be used for this regatta.
   *
   * The list takes into consideration the state of the regatta for
   * public viewing.
   *
   * @return Array:String $urls the appropriate "relative" URLs
   */
  public function calculatePublicPages() {
    $list = array();
    if ($this->private !== null)
      return $list;

    if ($this->dt_status === null)
      $this->setData();

    if ($this->dt_status == Regatta::STAT_SCHEDULED)
      return $list;

    $root = $this->getUrl();
    $list[] = $root . 'index.html';
    if ($this->hasFinishes()) {
      $list[] = $root . 'full-scores/index.html';
      if ($this->scoring == Regatta::SCORING_STANDARD) {
        if (count($this->getScoredRaces()) > 1)
          $list[] = $root . 'history.svg';
        if (!$this->isSingleHanded()) {
          foreach ($this->getDivisions() as $div) {
            $list[] = $root . sprintf('%s/index.html', $div);
            if (count($this->getScoredRaces($div)) > 1)
              $list[] = $root . sprintf('%s/history.svg', $div);
          }
        }
      }
        
      elseif ($this->scoring == Regatta::SCORING_COMBINED) {
        $list[] = $root . 'divisions/index.html';
        if (count($this->getScoredRaces(Division::A())) > 1)
          $list[] = $root . 'history.svg';
      }

      elseif ($this->scoring == Regatta::SCORING_TEAM)
        $list[] = $root . 'sailors/index.html';
    }
      
    if ($this->scoring == Regatta::SCORING_TEAM) {
      if (count($this->getRaces()) > 0) {
        $list[] = $root . 'all/index.html';
        $list[] = $root . 'rotations/index.html';
      }
    }
    else {
      $rotation = $this->getRotation();
      if ($rotation->isAssigned())
        $list[] = $root . 'rotations/index.html';
    }

    // Documents?
    if ($this->private === null) {
      $docs = $this->getDocuments();
      if (count($docs) > 0) {
        $list[] = $root . 'notices/index.html';
        foreach ($docs as $doc) {
          $list[] = $root . 'notices/' . $doc->url;
        }
      }
    }
    return $list;
  }

  // ------------------------------------------------------------
  // Document management
  // ------------------------------------------------------------

  /**
   * Get list of files associated with this regatta
   *
   * @param boolean $full set to true to include the full document
   * @param Const $category limit to only those of given type
   * @return Array:Document_Summary list of documents
   */
  public function getDocuments($full = false, $category = null) {
    $cond = new DBCond('regatta', $this->id);
    if ($category !== null)
      $cond = new DBBool(array($cond, new DBCond('category', $category)));

    $obj = ($full !== false) ? DB::T(DB::REGATTA_DOCUMENT) : DB::T(DB::REGATTA_DOCUMENT_SUMMARY);
    return DB::getAll($obj, $cond);
  }

  /**
   * Fetch the document, if any, with the given url.
   *
   * @param String $url the filename of the document in question
   * @param boolean $full set to true to return full document
   */
  public function getDocument($url, $full = false) {
    $obj = ($full !== false) ? DB::T(DB::REGATTA_DOCUMENT) : DB::T(DB::REGATTA_DOCUMENT_SUMMARY);
    $r = DB::getAll($obj, new DBBool(array(new DBCond('regatta',  $this->id),
                                           new DBCond('url', $url))));
    $res = null;
    if (count($r) > 0)
      $res = $r[0];
    unset($r);
    return $res;
  }

  /**
   * Append this document to this regatta
   *
   * Will automatically set the relative order and the URL for the
   * document.
   *
   * @param Document $doc the document to add
   */
  public function addDocument(Document $doc) {
    $curr = $this->getDocuments();
    $cnt = count($curr);
    $doc->relative_order = 1;
    if ($cnt > 0)
      $doc->relative_order = $curr[$cnt - 1]->relative_order + 1;
    $doc->regatta = $this;
    $doc->id = null;

    if (strlen($doc->name) == 0)
      throw new InvalidArgumentException("No name provided");

    $url = DB::slugify($doc->name, true, array("the", "of", "for", "and", "an", "in", "is", "at"));

    $ext = "";
    // append the extension
    switch ($doc->filetype) {
    case 'application/pdf':  $ext = 'pdf'; break;
    case 'image/jpeg':       $ext = 'jpg'; break;
    case 'image/png':        $ext = 'png'; break;
    case 'image/gif':        $ext = 'gif'; break;
    default:
      throw new SoterException("Unsupported filetype: " . $doc->filetype);
    }

    // make sure it is unique
    $doc->url = sprintf('%s.%s', $url, $ext);
    $other_urls = array();
    foreach ($curr as $other)
      $other_urls[] = $other->url;

    $suf = 1;
    while (in_array($doc->url, $other_urls)) {
      $doc->url = sprintf('%s-%d.%s', $url, $suf, $ext);
      $suf++;
    }

    DB::set($doc, false);
  }

  /**
   * Remove document with the given URL (whether or not it exists)
   *
   * @param String $url the url to remove
   */
  public function deleteDocument($url) {
    DB::removeAll(DB::T(DB::REGATTA_DOCUMENT_SUMMARY), new DBBool(array(new DBCond('regatta', $this->id),
                                                                  new DBCond('url', $url))));
  }

  /**
   * Retrieves all the races in this regatta associated with the given doc
   *
   * @param Document_Summary $doc the document in question
   * @param Division $div the optional division for the races
   * @return Array:Race list is empty if it applies to the entire regatta
   * @throws InvalidArgumentException if invalid passed document
   */
  public function getDocumentRaces(Document_Summary $doc, Division $div = null) {
    if ($doc->regatta === null || $doc->regatta->id != $this->id)
      throw new InvalidArgumentException("Invalid document provided: " . $doc->id);

    $cond = new DBBool(array(new DBCond('regatta', $this->id),
                             new DBCondIn('id', DB::prepGetAll(DB::T(DB::REGATTA_DOCUMENT_RACE), new DBCond('document', $doc->id), array('race')))));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));

    return DB::getAll(DB::T(DB::RACE), $cond);
  }

  /**
   * Sets the list of races associated with this document
   *
   * @param Document_Summary $doc the document whose races to set
   * @param Array $races list of races. Empty means "whole regatta"
   * @throws InvalidArgumentException if invalid document or race
   */
  public function setDocumentRaces(Document_Summary $doc, $races = array()) {
    if ($doc->id === null || $doc->regatta === null || $doc->regatta->id != $this->id)
      throw new InvalidArgumentException("Invalid document provided: " . $doc->id);

    $list = array();
    foreach ($races as $race) {
      if ($race->id === null)
        throw new InvalidArgumentException("Race provided not registered with regatta.");
      if ($race->regatta === null || $race->regatta->id != $this->id)
        throw new InvalidArgumentException("Race provided does not belong to this regatta: " . $race->id);

      $obj = new Document_Race();
      $obj->race = $race;
      $obj->document = $doc;

      $list[] = $obj;
    }

    // Remove old ones, add new ones
    DB::removeAll(DB::T(DB::REGATTA_DOCUMENT_RACE), new DBCond('document', $doc->id));
    DB::insertAll($list);
  }

  /**
   * Retrieves the (first) regatta document of type COURSE_FORMAT
   * associated with the given race
   *
   * @param Race $race the race
   * @return Document_Summary the document, if any
   */
  public function getRaceCourseFormat(Race $race) {
    foreach ($this->getDocuments(false, Document::CATEGORY_COURSE_FORMAT) as $doc) {
      $races = $this->getDocumentRaces($doc);
      if (count($races) == 0)
        return $doc;

      foreach ($races as $other) {
        if ($other->id == $race->id)
          return $doc;
      }
    }
    return null;
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
