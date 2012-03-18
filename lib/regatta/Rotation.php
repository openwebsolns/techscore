<?php
/*
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
   * Determines whether there is a rotation assigned: i.e. if there
   * are sails in the database
   *
   * @param Race $race the optional race to check
   * @return boolean has sails or not. Simple, no?
   */
  public function isAssigned(Race $race = null) {
    if ($race === null) {
      // Curious fact: this version is much faster!
      return count(DB::getAll(DB::$RACE,
			      new DBBool(array(new DBCond('regatta', $this->regatta),
					       new DBCondIn('id', DB::prepGetAll(DB::$SAIL, null, array('race'))))))) > 0;
    }
    return count(DB::getAll(DB::$SAIL, new DBCond('race', $race))) > 0;
  }

  /**
   * Fetches the team with the given sail in the given race, or null
   * if no such team exists
   *
   * @param Race $race the race
   * @param String $sail the sail number
   */
  public function getTeam(Race $race, $sail) {
    $res = DB::getAll(DB::$SAIL, new DBBool(array(new DBCond('race', $race), new DBCond('sail', $sail))));
    if (count($res) == 0)
      return null;
    return $res[0]->team;
  }

  /**
   * Returns sail number for specified race and team
   *
   * @param Race $race the race
   * @param Team $team the team
   * @return Sail the sail number, null if none
   */
  public function getSail(Race $race, Team $team) {
    $res = DB::getAll(DB::$SAIL, new DBBool(array(new DBCond('race', $race), new DBCond('team', $team))));
    if (count($res) == 0)
      return null;
    return $res[0];
  }

  /**
   * Returns ordered array of sails for specified race, or all the
   * distinct sail numbers if no race is specified
   *
   * @return Array:Sail sails in the race, or all sails
   */
  public function getSails(Race $race = null) {
    if ($race !== null)
      $cond = new DBCond('race', $race);
    else
      $cond = new DBCondIn('race', DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->regatta), array('id')));
    $list = array();
    foreach (DB::getAll(DB::$SAIL, $cond) as $sail)
      $list[] = $sail;
    usort($list, 'Rotation::compareSails');
    return $list;
  }

  /**
   * Fetches the sails in the same race number across all divisions
   *
   * @param Race $race the race number
   * @param Array $divisions if null, all the regatta's divisions
   */
  public function getCombinedSails(Race $race, Array $divisions = null) {
    if ($divisions === null)
      $divisions = $this->regatta->getDivisions();
    $races = array();
    foreach ($divisions as $div)
      $races[] = $this->regatta->getRace($div, $race->number);
    return $this->getCommonSails($races);
  }

  /**
   * Returns a list of sail numbers common to the given list of races
   *
   * @param Array:Race the list of races
   * @return Array:Sail the list of sails common to all the races
   */
  public function getCommonSails(Array $races) {
    $nums = array();
    foreach ($races as $race) {
      foreach ($this->getSails($race) as $num)
	$nums[(string)$num] = $num;
    }
    usort($nums, 'Rotation::compareSails');
    return $nums;
  }

  /**
   * Returns a list of races with rotation, ordered by division, number
   *
   * @return Array:Race list of races with rotations
   */
  public function getRaces() {
    return DB::getAll(DB::$RACE,
		      new DBBool(array(new DBCond('regatta', $this->regatta),
				       new DBCondIn('id', DB::prepGetAll(DB::$SAIL, null, array('race'))))));
  }

  /**
   * Returns list of divisions with rotation, ordered by division
   *
   * @return Array:Division list of divisions
   */
  public function getDivisions() {
    $q = DB::prepGetAll(DB::$RACE,
			new DBBool(array(new DBCond('regatta', $this->regatta),
					 new DBCondIn('id', DB::prepGetAll(DB::$SAIL, null, array('race'))))));
    $q->fields(array('division'), DB::$RACE->db_name());
    $q->distinct(true);
    $q->order_by(array('division'=>true));

    $q = DB::query($q);
    $list = array();
    while ($obj = $q->fetch_object())
      $list[] = Division::get($obj->division);
    return $list;
  }


  /**
   * Commits the given sail into this rotation, except for those from ByeTeam
   *
   * @param Sail $sail the sail to commit
   */
  public function setSail(Sail $sail) {
    if ($sail->team instanceof ByeTeam) return;
    // Enforce uniqueness
    $cur = $this->getSail($sail->race, $sail->team);
    $arg = false;
    if ($cur !== null) {
      $sail->id = $cur->id;
      $arg = true;
    }
    DB::set($sail, $arg);
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
    if ($this->regatta->scoring == Regatta::SCORING_STANDARD &&
	count($this->regatta->getDivisions()) > 1) {
      // enforce correspondence between division and races
      if ($num_divisions != $num_races)
	throw new InvalidArgumentException("The list of divisions must be of the same size as the list of races.");
      $table = $this->createStandardTable($sails, $num_races, $repeats, $updir);
      foreach ($races as $r => $num) {
// @TODO getRace()
	$race = $this->regatta->getRace($divisions[$r], $num);
	foreach ($teams as $t => $team) {
	  $sail = new Sail();
	  $sail->race = $race;
	  $sail->team = $team;
	  $sail->sail = $table[$t][$r];

	  // print(sprintf("%3s | %2s | %s\n", $sail->race, $sail->sail, $sail->team->name));
	  $this->queue($sail);
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
// @TODO getRace()
	  $race = $this->regatta->getRace($divisions[$t], $num);

	  $sail = new Sail();
	  $sail->race = $race;
	  $sail->team = $team;
	  $sail->sail = $table[$t][$r];

	  // print(sprintf("%3s | %2s | %s\n", $sail->race, $sail->sail, $sail->team->name));
	  $this->queue($sail);
	}
      }
    }
    $this->commit();
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
    if ($this->regatta->scoring == Regatta::SCORING_STANDARD &&
	count($this->regatta->getDivisions()) > 1) {
      // enforce correspondence between division and races
      if ($num_divisions != $num_races)
	throw new InvalidArgumentException("The list of divisions must be of the same size as the list of races.");

      $table = $this->createSwapTable($sails, $num_races, $repeats, $updir);
      foreach ($races as $r => $num) {
// @TODO getRace()
	$race = $this->regatta->getRace($divisions[$r], $num);
	foreach ($teams as $t => $team) {
	  $sail = new Sail();
	  $sail->race = $race;
	  $sail->team = $team;
	  $sail->sail = $table[$t][$r];

	  // print(sprintf("%3s | %2s | %s\n", $sail->race, $sail->sail, $sail->team->name));
	  $this->queue($sail);
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
// @TODO getRace()
	  $race = $this->regatta->getRace($divisions[$t], $num);

	  $sail = new Sail();
	  $sail->race = $race;
	  $sail->team = $team;
	  $sail->sail = $table[$t][$r];

	  // print(sprintf("%3s | %2s | %s\n", $sail->race, $sail->sail, $sail->team->name));
	  $this->queue($sail);
	}
      }
    }
    $this->commit();
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
	$new_sail = clone($sails[($j + $offset + $upper) % $upper]);
	$new_sail->id = null;
	$new_sail->race = $top;

	$this->queue($new_sail);
      }
    }
    $this->commit();
  }

  /**
   * Adds the given amount to the sails in the given race
   *
   * @param Race $race the race to affect
   * @param int  $amount the amount to add/subtract
   */
  public function addAmount(Race $race, $amount) {
    // This needs to be done intelligently. Should we reinsert all the
    // sails? Or update them? I'm thinking the latter; only because
    // removing them takes as many SQL calls as updating
    $sails = array();
    foreach ($this->regatta->getTeams() as $team) {
      $sail = $this->getSail($race, $team);
      if ($sail !== null) {
	$parts = self::split3($sail->sail);
	$parts[1] += $amount;
	$sail->sail = implode("", $parts);
	DB::set($sail, true);
      }
    }
  }

  public static function compareSails(Sail $sail1, Sail $sail2) {
    $s1 = self::split3($sail1->sail);
    $s2 = self::split3($sail2->sail);
    if ($s1[1] != $s2[1])
      return $s1[1] - $s2[1];
    return strcmp($s1[0], $s2[0]);
  }

  /**
   * Splits sail number into a three part array: prefix, numerical
   * value, suffix.
   *
   * @param String $a the sail number
   * @return Array the parts (implode("",$ret) to recreate $a);
   */
  private static function split3($a) {
    $a = (string)$a;
    $pre = array("", "", "");
    for ($i = strlen($a) - 1; $i >= 0; $i--) {
      if (is_numeric($a[$i])) {
	if (strlen($pre[0]) > 0)
	  $pre[0] = $a[$i] . $pre[0];
	else
	  $pre[1] = $a[$i] . $pre[1];
      }
      else {
	if (strlen($pre[1]) > 0)
	  $pre[0] = $a[$i] . $pre[0];
	else
	  $pre[2] = $a[$i] . $pre[2];
      }
    }
    return $pre;
  }

  /**
   * Returns the minimum sail number among the given sails
   *
   * @param Array:String $nums the sail numbers
   * @return int $min the minimum value
   */
  public static function min($nums) {
    $min = null;
    foreach ($nums as $num) {
      $split = self::split3($num);
      if ($min === null || $split[1] < $min)
	$min = $split[1];
    }
    return (int)$min;
  }

  /**
   * Replaces the given sail in the given race
   *
   * @param Race $race the race
   * @param int $orig  the sail number to replace
   * @param int $repl  the replacement
   */
  public function replaceSail(Race $race, $orig, $repl) {
    // Ultimate cheating
    $q = DB::createQuery(DBQuery::UPDATE);
    $q->values(array('sail'), array(DBQuery::A_STR), array($repl), DB::$SAIL->db_name());
    $q->where(new DBBool(array(new DBCond('race', $race), new DBCond('sail', $orig))));
    DB::query($q);
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
   * This method is now necessary to use in conjunction with
   * Rotation::queue
   */
  private function commit() {
    $this->reset();
    DB::insertAll($this->queued_sails);
    $this->queued_sails = array();
  }

  /**
   * Prepares the sail to be used later in a commit call. The reason
   * is that one large query is more efficient than many small
   * queries.
   *
   * @param Sail $sail the sail to queue
   */
  private function queue(Sail $sail) {
    if ($sail->team instanceof ByeTeam)
      return;
    $this->queued_sails[] = $sail;
  }
  private $queued_sails = array();

  /**
   * Deletes the entire rotation, or just the rotation for the given race
   *
   * @param Race $race the optional race to reset, or the entire
   * regatta, otherwise
   */
  public function reset(Race $race = null) {
    if ($race !== null)
      $cond = new DBCond('race', $race);
    else
      $cond = new DBCondIn('race', DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->regatta), array('id')));
    DB::removeAll(DB::$SAIL, $cond);
  }
}
?>