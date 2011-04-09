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
 * Encapsulates and manages a rotation
 *
 */
class Rotation {

  // Private variables
  private $regatta;

  /**
   * Instantiate a new rotation object
   *
   * @param $reg a regatta object
   */
  public function __construct(Regatta $reg) {
    $this->regatta = $reg;
  }

  /**
   * @var boolean attempt to cache whether or not there is a rotation
   */
  private $has_sails = null;
  /**
   * Determines whether there is a rotation assigned: i.e. if there
   * are sails in the database
   *
   * @return boolean has sails or not. Simple, no?
   */
  public function isAssigned() {
    if ($this->has_sails !== null)
      return $this->has_sails;

    $q = $this->regatta->query(sprintf('select race from rotation where race in (select id from race where regatta = %d)', $this->regatta->id()));
    $this->has_sails = ($q->num_rows > 0);
    $q->free();
    return $this->has_sails;
  }

  /**
   * Fetches the team with the given sail in the given race, or null
   * if no such team exists
   *
   * @param Race $race the race
   * @param int  $sail the sail number
   */
  public function getTeam(Race $race, $sail) {
    $q = sprintf('select team from rotation where (race, sail) = ("%s", "%s")', $race->id, $sail);
    $r = $this->regatta->query($q);
    if ($r->num_rows == 0) {
      $r->free();
      return null;
    }
    $r = $r->fetch_object();
    foreach ($this->regatta->getTeams() as $team) {
      if ($team->id == $r->team)
	return $team;
    }
    return null;
  }

  /**
   * Returns sail number for specified race and team
   *
   * @param Race $race the race
   * @param Team $team the team
   * @return String the sail number, null if none
   */
  public function getSail(Race $race, Team $team) {
    $q = sprintf('select sail from rotation ' .
		 'where (race, team) = ("%s", "%s")',
		 $race->id, $team->id);
    $q = $this->regatta->query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    $q = $q->fetch_object();
    return $q->sail;
  }

  /**
   * Returns array of sail numbers for specified race, or all the
   * distinct sail numbers if no race is specified
   *
   * @return Array<String> sail number in the race, or all sails
   */
  public function getSails(Race $race = null) {
    if ($race == null) {
      return $this->getAllSails();
    }
    
    $q = sprintf('select sail from rotation ' .
		 'where race = "%s" ' .
		 'order by sail',
		 $race->id);
    $q = $this->regatta->query($q);
    $sails = array();
    while ($sail = $q->fetch_object()) {
      $sails[] = $sail->sail;
    }
    return $sails;
  }

  private function getAllSails() {
    $q = sprintf('select distinct sail from rotation ' .
		 'inner join race on rotation.race = race.id ' .
		 'where race.regatta = "%s" ' .
		 'order by sail',
		 $this->regatta->id());
    $q = $this->regatta->query($q);
    $sails = array();
    while ($sail = $q->fetch_object()) {
      $sails[] = $sail->sail;
    }
    return $sails;
  }

  /**
   * Returns a list of sail numbers common to the given list of races
   *
   * @param Array<Race> the list of races
   * @return Array<String> the list of sails common to all the races
   */
  public function getCommonSails(Array $races) {
    $common_nums = null;
    foreach ($races as $race) {
      $nums = $this->getSails($race);
      if ($common_nums == null)	$common_nums = $nums;
      else
	$common_nums = array_intersect($common_nums, $nums);
    }
    return $common_nums;
  }

  /**
   * Returns a list of races with rotation, ordered by division, number
   *
   * @return Array<Race> list of races with rotations
   */
  public function getRaces() {
    $q = sprintf('select distinct rotation.race from rotation ' .
		 'inner join race on (race.id = rotation.race) ' .
		 'where race.regatta = "%s"',
		 $this->regatta->id());
    $q = $this->regatta->query($q);
    $rot_races = array();
    while ($obj = $q->fetch_object())
      $rot_races[] = $obj->race;

    $list = array();
    foreach ($this->regatta->getRaces() as $race)
      if (in_array($race->id, $rot_races))
	$list[] = $race;
    return $list;
  }

  /**
   * Returns list of divisions with rotation, ordered by division
   *
   * @return Array<Division> list of divisions
   */
  public function getDivisions() {
    $q = sprintf('select distinct race.division ' .
		 'from race inner join rotation ' .
		 '  on (rotation.race = race.id) ' .
		 'where race.regatta = "%s" ' .
		 'order by race.division',
		 $this->regatta->ID());
    $q = $this->regatta->query($q);
    $list = array();
    while ($obj = $q->fetch_object()) {
      $list[] = Division::get($obj->division);
    }
    return $list;
  }


  /**
   * Commits the given sail into this rotation, except for those from ByeTeam
   *
   * @param Sail $sail the sail to commit
   */
  public function setSail(Sail $sail) {
    if ($sail->team instanceof ByeTeam) return;

    $con = Preferences::getConnection();
    $q = sprintf('insert into rotation (race, team, sail) values ("%s", "%s", "%s") on duplicate key update sail=values(sail)',
		 $sail->race->id, $sail->team->id, $con->real_escape_string($sail->sail));
    $this->regatta->query($q);
  }


  /**
   * 'Standard' rotation: +/-1 per set
   * Example (standard):
   * <pre>
   * |     | A1 | B1 | A2 | B2 | A3 | B3 |
   * |-----+----+----+----+----+----+----|
   * | MIT |  1 |  2 |  3 |  1 |  2 |  3 |
   * | HAR |  2 |  3 |  1 |  2 |  3 |  1 |
   * | BC  |  3 |  1 |  2 |  3 |  1 |  2 |
   * </pre>
   *
   * Example (combined):
   * <pre>
   * |     |  1 |  2 |  3 |  4 |  5 |  6 |
   * |-----+----+----+----+----+----+----|
   * |A-MIT|  1 |  2 |  3 |  4 |  5 |  6 |
   * |A-HAR|  2 |  3 |  4 |  5 |  6 |  1 |
   * |A-BC |  3 |  4 |  5 |  6 |  1 |  2 |
   * |B-MIT|  4 |  5 |  6 |  1 |  2 |  3 |
   * |B-HAR|  5 |  6 |  1 |  2 |  3 |  4 |
   * |B-BC |  6 |  1 |  2 |  3 |  4 |  5 |
   * </pre>
   *
   * In all cases, the array of teams and the array of sails must be
   * the same size. In the case of standard scoring, the list of
   * divisions corresponds with the list of race numbers. The races
   * (division and number) will be consumed one at a time.
   *
   * If the regatta is a combined scoring regatta, then the list of
   * divisions must match the list of sails and teams instead.
   *
   * @param Array<Team> teams the list of teams in order
   * @param Array<int>  sails the list of sail numbers in order
   * @param Array<Division> the list of divisions
   * @param Array<int>  races the list of race numbers in order
   * @param int repeats the number of races per set
   * @param boolean updir the direction of the rotation, default is
   * "true"
   * @throws InvalidArgumentException should the sizes of the various
   * lists be incorrect
   */
  public function createStandard(Array $sails,
				 Array $teams,
				 Array $divisions,
				 Array $races,
				 $repeats,
				 $updir = true) {
    $sails = array_values($sails);
    $teams = array_values($teams);
    $races = array_values($races);

    // verify parameters
    $num_sails = count($sails);
    $num_teams = count($teams);
    $num_divisions = count($divisions);
    $num_races     = count($races);
    if ($num_sails != $num_teams)
      throw new InvalidArgumentException("There must be the same number of sails and teams.");
    if ($repeats < 1)
      throw new InvalidArgumentException("The number of races per set ($repeats) must be at least one.");

    // standard scoring regatta
    if ($this->regatta->get(Regatta::SCORING) == Regatta::SCORING_STANDARD &&
	!$this->regatta->isSinglehanded()) {
      // enforce correspondence between division and races
      if ($num_divisions != $num_races)
	throw new InvalidArgumentException("The list of divisions must be of the same size as the list of races.");
      $table = $this->createStandardTable($sails, $num_races, $repeats, $updir);
      foreach ($races as $r => $num) {
	$race = $this->regatta->getRace($divisions[$r], $num);
	foreach ($teams as $t => $team) {
	  $sail = new Sail();
	  $sail->race = $race;
	  $sail->team = $team;
	  $sail->sail = $table[$t][$r];

	  // print(sprintf("%3s | %2s | %s\n", $sail->race, $sail->sail, $sail->team->name));
	  $this->setSail($sail);
	}
      }
    }
    
    // combined scoring, or singlehanded regattas
    else {
      // enforce correspondence between divisions and teams
      if ($num_divisions != $num_teams)
	throw new InvalidArgumentException("In combined scoring, the list of divisions must be " .
					   "of the same size as the list of teams.");

      $table = $this->createStandardTable($sails, $num_races, $repeats, $updir);
      foreach ($races as $r => $num) {
	foreach ($teams as $t => $team) {
	  $race = $this->regatta->getRace($divisions[$t], $num);

	  $sail = new Sail();
	  $sail->race = $race;
	  $sail->team = $team;
	  $sail->sail = $table[$t][$r];

	  // print(sprintf("%3s | %2s | %s\n", $sail->race, $sail->sail, $sail->team->name));
	  $this->setSail($sail);
	}
      }
    }
    $this->has_sails = true;
  }

  /**
   * 'Swap' rotation: +/-1 per set if either odd/even
   * Example (standard scoring):
   * <pre>
   * |     | A1 | B1 | A2 | B2 | A3 | B3 |
   * |-----+----+----+----+----+----+----|
   * | MIT |  1 |  2 |  3 |  4 |  1 |  2 |
   * | HAR |  2 |  1 |  4 |  3 |  2 |  1 |
   * | BC  |  3 |  4 |  1 |  2 |  3 |  4 |
   * | BU  |  4 |  3 |  2 |  1 |  4 |  3 |
   * </pre>
   *
   * Example (combined):
   * <pre>
   * |     |  1 |  2 |  3 |  4 |  5 |  6 |
   * |-----+----+----+----+----+----+----|
   * |A-MIT|  1 |  2 |  3 |  4 |  1 |  2 |
   * |A-HAR|  2 |  1 |  4 |  3 |  2 |  1 |
   * |B-MIT|  3 |  4 |  1 |  2 |  3 |  4 |
   * |B-HAR|  4 |  3 |  2 |  1 |  4 |  3 |
   * </pre>
   *
   * In all cases, the array of teams and the array of sails must be
   * the same size. In the case of standard scoring, the list of
   * divisions corresponds with the list of race numbers. The races
   * (division and number) will be consumed one at a time.
   *
   * If the regatta is a combined scoring regatta, then the list of
   * divisions must match the list of sails and teams instead.
   *
   * @param Array<Team> teams the list of teams in order
   * @param Array<int>  sails the list of sail numbers in order
   * @param Array<Division> the list of divisions
   * @param Array<int>  races the list of race numbers in order
   * @param int repeats the number of races per set
   * @param boolean updir the direction of the rotation, default is
   * "true"
   */
  public function createSwap(Array $sails,
			     Array $teams,
			     Array $divisions,
			     Array $races,
			     $repeats,
			     $updir = true) {
    $sails = array_values($sails);
    $teams = array_values($teams);
    $races = array_values($races);

    // verify parameters
    $num_sails = count($sails);
    $num_teams = count($teams);
    $num_divisions = count($divisions);
    $num_races     = count($races);
    if ($num_sails != $num_teams)
      throw new InvalidArgumentException("There must be the same number of sails and teams.");
    if ($repeats < 1)
      throw new InvalidArgumentException("The number of races per set ($repeats) must be at least one.");

    // standard scoring regatta
    if ($this->regatta->get(Regatta::SCORING) == Regatta::SCORING_STANDARD &&
	!$this->regatta->isSinglehanded()) {
      // enforce correspondence between division and races
      if ($num_divisions != $num_races)
	throw new InvalidArgumentException("The list of divisions must be of the same size as the list of races.");

      $table = $this->createSwapTable($sails, $num_races, $repeats, $updir);
      foreach ($races as $r => $num) {
	$race = $this->regatta->getRace($divisions[$r], $num);
	foreach ($teams as $t => $team) {
	  $sail = new Sail();
	  $sail->race = $race;
	  $sail->team = $team;
	  $sail->sail = $table[$t][$r];

	  // print(sprintf("%3s | %2s | %s\n", $sail->race, $sail->sail, $sail->team->name));
	  $this->setSail($sail);
	}
      }
    }
    
    // combined scoring
    else {
      // enforce correspondence between divisions and teams
      if ($num_divisions != $num_teams)
	throw new InvalidArgumentException("In combined scoring, the list of divisions must be " .
					   "of the same size as the list of teams.");

      $table = $this->createSwapTable($sails, $num_races, $repeats, $updir);
      foreach ($races as $r => $num) {
	foreach ($teams as $t => $team) {
	  $race = $this->regatta->getRace($divisions[$t], $num);

	  $sail = new Sail();
	  $sail->race = $race;
	  $sail->team = $team;
	  $sail->sail = $table[$t][$r];

	  // print(sprintf("%3s | %2s | %s\n", $sail->race, $sail->sail, $sail->team->name));
	  $this->setSail($sail);
	}
      }
    }
    $this->has_sails = true;
  }

  /**
   * Creates the table of sails using the standard (+/-1) procedure.
   *
   * @param Array<int> $sails the sails in order
   * @param int $num_races the total number of races
   * @param int $repeats   the number of races per set
   * @param boolean $updir whether to go up (default) or down
   * @return Array<Array<int>> a table of [team][race] sails
   */
  private function createStandardTable(Array $sails, $num_races, $repeats = 1, $updir = true) {

    $dir = ($updir) ? 1 : -1;

    $num_sails = count($sails);
    $table = array();
    $race = 0;
    while ($race < $num_races) {
      for ($r = 0; $r < $repeats; $r++) {
	if ($race >= $num_races) break;

	for ($s = 0; $s < $num_sails; $s++) {
	  if (!isset($table[$s])) $table[$s] = array();

	  // pull corresponding sail: start at original value
	  // shift the index to the current sail number by moving up
	  // or down the number of sets of sails already consumed
	  $shift = $dir * (int)($race / $repeats);
	  $index = ($s + $shift) % $num_sails;
	  if ($index < 0) $index += $num_sails;

	  $table[$s][] = $sails[$index];
	}
	$race++;
      }
    }
    return $table;
  }

  /**
   * Creates the table of sails using the swap procedure.
   *
   * @param Array<int> $sails the sails in order
   * @param int $num_races the total number of races
   * @param int $repeats   the number of races per set
   * @param boolean $updir whether to go up (default) or down
   * @return Array<Array<int>> a table of [team][race] sails
   */
  private function createSwapTable(Array $sails, $num_races, $repeats = 1, $updir = true) {

    $updir = ($updir) ? 1 : -1;

    $num_sails = count($sails);
    $table = array();
    $race = 0;
    while ($race < $num_races) {
      for ($r = 0; $r < $repeats; $r++) {
	if ($race >= $num_races) break;

	for ($s = 0; $s < $num_sails; $s++) {
	  if (!isset($table[$s])) $table[$s] = array();

	  // pull corresponding sail: start at original value
	  $dir = (($s % 2) == 0) ? $updir : -1 * $updir;

	  // shift the index to the current sail number by moving up
	  // or down the number of sets of sails already consumed
	  $shift = $dir * (int)($race / $repeats);
	  $index = ($s + $shift) % $num_sails;
	  if ($index < 0) $index += $num_sails;

	  $table[$s][] = $sails[$index];
	}
	$race++;
      }
    }
    return $table;
  }


  /**
   * Offset rotation: set the sails in the 'to' races to be in the
   * same order as those in the 'from' races (one at a time,
   * respectively), but offset by a given number of places.
   *
   * This method works by working through each race in 'fromrace' at a
   * time, and shifting the sails for that race by the offset amount
   * before placing it in the corresponding index of the 'torace'
   * array. This means that both lists must have the same size.
   *
   * @param Array:Race $fromraces list of template races
   * @param Array:Race $toraces matching list of races to affect
   * @param int $offset the number of places to shift
   * @throws InvalidArgumentException if the array sizes do not match
   */
  public function createOffset(Division $fromdiv, Division $todiv, Array $nums, $offset) {
    foreach ($nums as $num) {
      $from = $this->regatta->getRace($fromdiv, $num);
      $to = $this->regatta->getRace($todiv, $num);
      $sails = $this->getSails($from);
      $upper = count($sails);
      foreach ($sails as $j => $sail) {
	$new_sail = new Sail();
	$new_sail->race = $to;
	$new_sail->team = $this->getTeam($from, $sail);
	$new_sail->sail = $sails[($j + $offset + $upper) % $upper];

	$this->setSail($new_sail);
      }
    }
    $this->has_sails = true;
  }

  /**
   * Adds the given amount to the sails in the given race
   *
   * @param Race $race the race to affect
   * @param int  $amount the amount to add/subtract
   */
  public function addAmount(Race $race, $amount) {
    $q = sprintf('update rotation set sail = (sail + %d) where race = "%s"', $amount, $race->id);
    $this->regatta->query($q);
  }

  /**
   * Replaces the given sail in the given race
   *
   * @param Race $race the race
   * @param int $orig  the sail number to replace
   * @param int $repl  the replacement
   */
  public function replaceSail(Race $race, $orig, $repl) {
    $q = sprintf('update rotation set sail = "%s" ' .
		 'where sail = "%s" and race = "%s"',
		 (int)$repl, (int)$orig, $race->id);
    $this->regatta->query($q);
    $this->has_sails = true;
  }

  /**
   * As of 2011-02-06, this function no longer notifies the update
   * manager that a rotation change has happened. Instead, client code
   * is responsible for doing so. This function, which is currently
   * empty, should be called anyways, because it is possible that in
   * the future a call to this method will be required in order to
   * actually commit the changes to the database. At the moment,
   * however, each call to <pre>replaceSail</pre> and its brethren
   * issues a SQL query on its own.
   *
   */
  public function commit() {}
}
?>