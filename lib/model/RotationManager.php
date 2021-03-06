<?php

/**
 * Encapsulates and manages a rotation
 *
 */
class RotationManager {

  // Private variables
  private $regatta;

  /**
   * Instantiate a new rotation object
   *
   * @param $reg a regatta object
   */
  public function __construct(FullRegatta $reg) {
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
      return count(DB::getAll(DB::T(DB::RACE),
                              new DBBool(array(new DBCond('regatta', $this->regatta),
                                               new DBCondIn('id', DB::prepGetAll(DB::T(DB::SAIL), null, array('race'))))))) > 0;
    }
    return count(DB::getAll(DB::T(DB::SAIL), new DBCond('race', $race))) > 0;
  }

  /**
   * Fetches the team with the given sail in the given race, or null
   * if no such team exists
   *
   * @param Race $race the race
   * @param String $sail the sail number
   */
  public function getTeam(Race $race, $sail) {
    $res = DB::getAll(DB::T(DB::SAIL), new DBBool(array(new DBCond('race', $race), new DBCond('sail', $sail))));
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
    $res = DB::getAll(DB::T(DB::SAIL), new DBBool(array(new DBCond('race', $race), new DBCond('team', $team))));
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
      $cond = new DBCondIn('race', DB::prepGetAll(DB::T(DB::RACE), new DBCond('regatta', $this->regatta), array('id')));
    $list = array();
    foreach (DB::getAll(DB::T(DB::SAIL), $cond) as $sail)
      $list[] = $sail;
    usort($list, 'RotationManager::compareSails');
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
   * @param Array|ArrayIterator:Race the list of races
   * @return Array:Sail the list of sails common to all the races
   */
  public function getCommonSails($races) {
    if (!($races instanceof ArrayIterator) && !is_array($races))
      throw new InvalidArgumentException("Argument is not iterable.");
    $nums = array();
    foreach ($races as $race) {
      foreach ($this->getSails($race) as $num)
        $nums[(string)$num] = $num;
    }
    usort($nums, 'RotationManager::compareSails');
    return $nums;
  }

  /**
   * Fetch sails involved in the races of the given round
   *
   * @param Round $round the round (must belong to this regatta)
   * @return Array:Sail rotations
   */
  public function getSailsInRound(Round $round) {
    return DB::getAll(DB::T(DB::SAIL),
                      new DBCondIn('race', DB::prepGetAll(DB::T(DB::RACE),
                                                          new DBBool(array(new DBCond('regatta', $this->regatta->id),
                                                                           new DBCond('round', $round))),
                                                          array('id'))));
  }

  /**
   * Fetch sails involved in the races of the rounds of the given group
   *
   * @param Round_Group $group the groups of round
   * @return Array:Sail rotations
   */
  public function getSailsInRoundGroup(Round_Group $group) {
    $cond = new DBCondIn('round', DB::prepGetAll(DB::T(DB::ROUND), new DBCond('round_group', $group), array('id')));
    return DB::getAll(DB::T(DB::SAIL),
                      new DBCondIn('race', DB::prepGetAll(DB::T(DB::RACE),
                                                          new DBBool(array(new DBCond('regatta', $this->regatta->id),
                                                                           $cond)),
                                                          array('id'))));
  }

  /**
   * Returns a list of races with rotation, ordered by division, number
   *
   * @param Division $div if given, the particular division
   * @return Array:Race list of races with rotations
   */
  public function getRaces(Division $div = null) {
    $conds = array(new DBCond('regatta', $this->regatta),
                   new DBCondIn('id', DB::prepGetAll(DB::T(DB::SAIL), null, array('race'))));
    if ($div !== null)
      $conds[] = new DBCond('division', (string)$div);
    return DB::getAll(DB::T(DB::RACE), new DBBool($conds));
  }

  /**
   * Returns list of divisions with rotation, ordered by division
   *
   * @return Array:Division list of divisions
   */
  public function getDivisions() {
    $q = DB::prepGetAll(DB::T(DB::RACE),
                        new DBBool(array(new DBCond('regatta', $this->regatta),
                                         new DBCondIn('id', DB::prepGetAll(DB::T(DB::SAIL), null, array('race'))))));
    $q->fields(array('division'), DB::T(DB::RACE)->db_name());
    $q->distinct(true);
    $q->order_by(array('division'=>true));

    $q = DB::query($q);
    $list = array();
    while ($obj = $q->fetch_object())
      $list[] = Division::get($obj->division);
    return $list;
  }

  /**
   * Applicable to team racing, return the rounds with sails
   *
   * @return Array:Round
   */
  public function getRounds() {
    $q = DB::prepGetAll(DB::T(DB::RACE),
                        new DBCondIn('id', DB::prepGetAll(DB::T(DB::SAIL), null, array('race'))),
                        array('round'));

    return DB::getAll(DB::T(DB::ROUND),
                      new DBBool(array(new DBCond('regatta', $this->regatta),
                                       new DBCondIn('id', $q))));
  }

  /**
   * Retrieves the last FleetRotation associated with this regatta.
   *
   * @return FleetRotation or null.
   */
  public function getFleetRotation() {
    $rotations = DB::getAll(DB::T(DB::FLEET_ROTATION), new DBCond('regatta', $this->regatta));
    if (count($rotations) == 0) {
      return null;
    }
    return $rotations[0];
  }

  public function removeFleetRotation() {
    DB::removeAll(DB::T(DB::FLEET_ROTATION), new DBCond('regatta', $this->regatta));
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
   * @param Array<int>  sails the list of sail numbers in order
   * @param Array<color> corresponding colors
   * @param Array<Team> teams the list of teams in order
   * @param Array<Division> the list of divisions
   * @param Array<int>  races the list of race numbers in order
   * @param int repeats the number of races per set
   * @param boolean updir the direction of the rotation, default is
   * "true"
   * @throws InvalidArgumentException should the sizes of the various
   * lists be incorrect
   */
  public function createStandard(Array $sails,
                                 Array $colors,
                                 Array $teams,
                                 Array $divisions,
                                 Array $races,
                                 $repeats,
                                 $updir = true) {
    $this->initQueue();

    $sails = array_values($sails);
    $colors = array_values($colors);
    $teams = array_values($teams);
    $races = array_values($races);

    $race_objs = array(); // race objects to reset

    // verify parameters
    $num_sails = count($sails);
    $num_teams = count($teams);
    $num_divisions = count($divisions);
    $num_races     = count($races);
    if ($num_sails != $num_teams)
      throw new InvalidArgumentException("There must be the same number of sails and teams.");
    if ($repeats < 1)
      throw new InvalidArgumentException("The number of races per set ($repeats) must be at least one.");
    if ($num_sails != count($colors))
      throw new InvalidArgumentException("There must be the same number of sails as colors.");

    $sail_colors = array();
    foreach ($sails as $i => $sail)
      $sail_colors[$sail] = $colors[$i];

    // standard scoring regatta
    if ($this->regatta->scoring == Regatta::SCORING_STANDARD &&
        count($this->regatta->getDivisions()) > 1) {
      // enforce correspondence between division and races
      if ($num_divisions != $num_races)
        throw new InvalidArgumentException("The list of divisions must be of the same size as the list of races.");
      $table = $this->createStandardTable($sails, $num_races, $repeats, $updir);
      foreach ($races as $r => $num) {
        $race = $this->regatta->getRace($divisions[$r], $num);
        $race_objs[$race->id] = $race;
        foreach ($teams as $t => $team) {
          $sail = new Sail();
          $sail->race = $race;
          $sail->team = $team;
          $sail->sail = $table[$t][$r];
          $sail->color = $sail_colors[$sail->sail];

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
          $race = $this->regatta->getRace($divisions[$t], $num);
          $race_objs[$race->id] = $race;

          $sail = new Sail();
          $sail->race = $race;
          $sail->team = $team;
          $sail->sail = $table[$t][$r];
          $sail->color = $sail_colors[$sail->sail];

          $this->queue($sail);
        }
      }
    }
    foreach ($race_objs as $race)
      $this->reset($race);
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
   * @param Array<int>  sails the list of sail numbers in order
   * @param Array<color> corresponding colors
   * @param Array<Team> teams the list of teams in order
   * @param Array<Division> the list of divisions
   * @param Array<int>  races the list of race numbers in order
   * @param int repeats the number of races per set
   * @param boolean updir the direction of the rotation, default is
   * "true"
   */
  public function createSwap(Array $sails,
                             Array $colors,
                             Array $teams,
                             Array $divisions,
                             Array $races,
                             $repeats,
                             $updir = true) {
    $this->initQueue();

    $sails = array_values($sails);
    $colors = array_values($colors);
    $teams = array_values($teams);
    $races = array_values($races);

    $race_objs = array(); // races to reset

    // verify parameters
    $num_sails = count($sails);
    $num_teams = count($teams);
    $num_divisions = count($divisions);
    $num_races     = count($races);
    if ($num_sails != $num_teams)
      throw new InvalidArgumentException("There must be the same number of sails and teams.");
    if ($repeats < 1)
      throw new InvalidArgumentException("The number of races per set ($repeats) must be at least one.");
    if ($num_sails != count($colors))
      throw new InvalidArgumentException("The number of sails must match the number of colors.");

    $sail_colors = array();
    foreach ($sails as $i => $sail)
      $sail_colors[$sail] = $colors[$i];

    // standard scoring regatta
    if ($this->regatta->scoring == Regatta::SCORING_STANDARD &&
        count($this->regatta->getDivisions()) > 1) {
      // enforce correspondence between division and races
      if ($num_divisions != $num_races)
        throw new InvalidArgumentException("The list of divisions must be of the same size as the list of races.");

      $table = $this->createSwapTable($sails, $num_races, $repeats, $updir);
      foreach ($races as $r => $num) {
        $race = $this->regatta->getRace($divisions[$r], $num);
        $race_objs[$race->id] = $race;
        foreach ($teams as $t => $team) {
          $sail = new Sail();
          $sail->race = $race;
          $sail->team = $team;
          $sail->sail = $table[$t][$r];
          $sail->color = $sail_colors[$sail->sail];

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
          $race = $this->regatta->getRace($divisions[$t], $num);
          $race_objs[$race->id] = $race;

          $sail = new Sail();
          $sail->race = $race;
          $sail->team = $team;
          $sail->sail = $table[$t][$r];
          $sail->color = $sail_colors[$sail->sail];

          $this->queue($sail);
        }
      }
    }
    foreach ($race_objs as $race)
      $this->reset($race);
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
   * Note that unlike the others, this function DOES NOT commit the
   * changes to the database.
   *
   * @param Array:Race $fromraces list of template races
   * @param Array:Race $toraces matching list of races to affect
   * @param int $offset the number of places to shift
   * @return Array:Race list of races whose sails were queued
   * @throws InvalidArgumentException if the array sizes do not match
   */
  public function queueOffset(Division $fromdiv, Division $todiv, Array $nums, $offset) {
    $queued_races = array();

    foreach ($nums as $num) {
      $from = $this->regatta->getRace($fromdiv, $num);
      $to = $this->regatta->getRace($todiv, $num);
      if ($from === null || $to === null)
        throw new InvalidArgumentException("Race num $num does not exist in both division $fromdiv and $todiv.");
      $sails = $this->getSails($from);
      $upper = count($sails);
      foreach ($sails as $j => $sail) {
        $offset_sail = $sails[($j + $offset + $upper) % $upper];

        $new_sail = new Sail();
        $new_sail->race = $to;
        $new_sail->team = $sail->team;
        $new_sail->sail = $offset_sail->sail;
        $new_sail->color = $offset_sail->color;

        $this->queue($new_sail);
      }

      $queued_races[] = $to;
    }
    return $queued_races;
  }

  /**
   * Appropriate for "combined" rotations, this method will shift all
   * the sails in the given race $nums by the given $offset, taking
   * the full fleet into account.
   *
   * @param Array $nums the race numbers
   * @param int $offset the offset amount
   * @throws InvalidArgumentException
   */
  public function queueCombinedOffset(Array $nums, $offset) {
    $divisions = $this->regatta->getDivisions();
    $queued_races = array();
    foreach ($nums as $num) {
      $races = array();
      foreach ($divisions as $div) {
        $race = $this->regatta->getRace($div, $num);
        $races[] = $race;
        $queued_races[] = $race;
      }
      $sails = $this->getCommonSails($races);

      $upper = count($sails);
      foreach ($sails as $j => $sail) {
        $offset_sail = $sails[($j + $offset + $upper) % $upper];

        $new_sail = clone $sail;
        $new_sail->sail = $offset_sail->sail;
        $new_sail->color = $offset_sail->color;

        $this->queue($new_sail);
      }
    }
    return $queued_races;
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
    $q->values(array('sail'), array(DBQuery::A_STR), array($repl), DB::T(DB::SAIL)->db_name());
    $q->where(new DBBool(array(new DBCond('race', $race), new DBCond('sail', $orig))));
    DB::query($q);
  }

  /**
   * This method is now necessary to use in conjunction with
   * RotationManager::queue.
   *
   * Client code is responsible for resetting the appropriate races
   * prior to calling this function.
   */
  public function commit() {
    DB::insertAll($this->queued_sails);
  }

  /**
   * Prepares the internal queue of sails.
   *
   * Sails are then committed using <pre>commit</pre> method.
   */
  public function initQueue() {
    $this->queued_sails = array();
  }

  /**
   * Prepares the sail to be used later in a commit call. The reason
   * is that one large query is more efficient than many small
   * queries.
   *
   * @param Sail $sail the sail to queue
   */
  public function queue(Sail $sail) {
    if ($sail->team instanceof ByeTeam)
      return;
    $this->queued_sails[$sail->hash()] = $sail;
  }


  public function dumpQueue() {
    echo "<pre>"; print_r($this->queued_sails); "</pre>";
    exit;
  }

  private $queued_sails;

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
      $cond = new DBCondIn('race', DB::prepGetAll(DB::T(DB::RACE), new DBCond('regatta', $this->regatta), array('id')));
    DB::removeAll(DB::T(DB::SAIL), $cond);
  }
}
