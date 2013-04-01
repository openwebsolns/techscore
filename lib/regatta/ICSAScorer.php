<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package regatta
 */

/**
 * Scores a regatta according to ICSA rules.
 *
 * 2011-01-30: When scoring a regatta, optionally score only the given
 * races, instead of all the races, for speed sake. Note that when
 * scoring select races, ICSAScorer will still update those finishes
 * in other races whose score depends on averages.
 *
 * @author Dayan Paez
 * @version 2010-01-28
 */
class ICSAScorer {

  /**
   * Helper method to identify any additional average-scored finishes
   * in the given list of races
   *
   */
  protected function &getAverageFinishes(Regatta $reg, $races) {
    $avg_finishes = array();
    $divs = array();
    foreach ($races as $race) {
      if (in_array($race->division, $divs))
        continue;
      $divs[] = $race->division;
      foreach ($reg->getAverageFinishes($race->division) as $finish) {
        if (!isset($avg_finishes[$finish->hash()]))
          $avg_finishes[$finish->hash()] = $finish;
      }
    }
    return $avg_finishes;
  }

  /**
   * Helper method returns the finishes associated with a given race, ordered
   *
   * This method should be overridden by child classes as needed
   *
   * @param Regatta $reg the regatta
   * @param Race $race the race
   */
  protected function &getFinishes(Regatta $reg, Race $race) {
    $list = array();
    foreach ($reg->getFinishes($race) as $finish)
      $list[] = $finish;
    return $list;
  }

  /**
   * Determines the appropriate score to use for a given finish based
   * on the provided list of modifiers
   *
   * @param Finish $fin the finish
   * @param Array:FinishModifier (Penalty objects) the penalties
   * @return Score the score to use
   */
  public function getPenaltiesScore(Finish $fin, Array $mods) {
    // If any is assigned, then use that amount, otherwise use fleet
    $comments = array();
    foreach ($mods as $pen) {
      if ($pen->amount > 0)
        return new Score($pen->amount, sprintf("(%d, Assigned) %s", $pen->amount, $pen->comments));
      if (!empty($pen->comments))
        $comments[] = $pen->comments;
    }
    if ($this->fleet === null)
      $this->initFleet($fin->team->regatta);
    return new Score($this->fleet, sprintf("(%d, Fleet + 1) %s", $this->fleet, implode(". ", $comments)));
  }

  protected function initFleet(Regatta $reg) {
    $this->fleet = count($reg->getTeams()) + 1;
  }

  protected $fleet = null;

  /**
   * Determine whether to advance score counter for next team.
   *
   * A penalty or breakdown that displaces is one whose actual
   * finished is dictated not by the time entered but by the score.
   * Thus, any teams between the new score and the old score will be
   * affected.
   *
   * Otherwise, the score is assigned as earned, and then overridden
   * by the penalty/breakdown as appropriate.
   *
   * @return int the new index number (starting at 1)
   */
  protected function reorderScore(Finish $fin, FinishModifier $pen) {
    if ($pen->amount > 0)
      return ($pen->displace > 0) ? (int)$pen->amount : null;

    $bkdlist = Breakdown::getList();
    if (isset($bkdlist[$pen->type]))
      return null;

    if ($this->fleet === null)
      $this->initFleet($fin->team->regatta);
    return $this->fleet + 1;
  }

  /**
   * Scores the given regatta, and commits the affected finishes back
   * to the database.
   *
   * @param Regatta $reg the regatta to score
   * @param Array:Races the list of races to score
   */
  public function score(Regatta $reg, $races) {
    if (!is_array($races) && !($races instanceof ArrayIterator))
      throw new InvalidArgumentException("Races list should be a list");
    if (count($races) == 0)
      return;

    // track the finishes which need to be committed to database
    $affected_finishes = array();

    $penlist = Penalty::getList();
    $scored_races = array(); // track race numbers already scored
    foreach ($races as $race) {
      if (isset($scored_races[(string)$race]))
        continue;
      $scored_races[(string)$race] = $race;

      // Get each finish in order and set the score
      $finishes = $this->getFinishes($reg, $race);

      $sorted_finishes = array();
      $bottom_finishes = array();

      // Re-order based on assigned finishes which displace
      $count = count($finishes);
      $i = 0;
      while ($i < count($finishes)) {
        $finish = $finishes[$i];
        $hash = $finish->hash();

        if (isset($sorted_finishes[$hash])) {
          $i++;
          continue;
        }
        $sorted_finishes[$hash] = $finish;

        $modifier = $finish->getModifier();
        if ($modifier === null) {
          $i++;
          continue;
        }

        $new_index = $this->reorderScore($finish, $modifier);

	if ($new_index === null) {
          $i++;
          continue;
        }

        array_splice($finishes, $i, 1);
        if ($new_index > $count) {
          $bottom_finishes[] = $finish;
          continue;
        }
        $new_index = min($new_index, count($finishes) + 1);

        array_splice($finishes, $new_index - 1, 0, array($finish));
        $finish->earned = ($i + 1);
        if ($new_index <= $i)
          $i++;
      }

      foreach ($bottom_finishes as $finish)
        $finishes[] = $finish;

      $score = 0;
      foreach ($finishes as $finish) {
        $score++;

	$finish->earned = $score;
        $affected_finishes[] = $finish;
        $modifiers = $finish->getModifiers();
        if (count($modifiers) == 0) {
          // ------------------------------------------------------------
          // clean finish
          $finish->score = new Score($score);
          continue;
        }
        $penalty = $modifiers[0];
        if (!isset($penlist[$penalty->type])) {
          // ------------------------------------------------------------
          // breakdown
          // Should the amount be assigned, determine actual
          // score. If not, then keep track for average score
          if ($penalty->amount > 0) {
            $amount = $penalty->amount;
            $exp = sprintf("(%d, Assigned) %s", $amount, $penalty->comments);
            if ($score < $penalty->amount) {
              $amount = $score;
              $exp = sprintf("(%d, Assigned amount (%d) no better than actual) %s",
                             $amount, $penalty->amount, $penalty->comments);
            }
            $finish->score = new Score($amount, $exp);
          }
          continue;
        }
        // ------------------------------------------------------------
        // penalty, or penalties
        $penScore = $this->getPenaltiesScore($finish, $modifiers);
        if ($penScore->score >= $score) {
          $finish->score = $penScore;
        }
        else {
          $finish->score = new Score($score,
                                     sprintf("(%d, penalty (%d) no worse) %s",
                                             $score,
                                             $penScore->score,
                                             $penalty->comments));
        }
      }
    }
    $reg->commitFinishes($affected_finishes);

    // ------------------------------------------------------------
    // Part 2: deal with average finishes, including those from across
    // the regatta, not just this race
    // ------------------------------------------------------------
    $avg_finishes = $this->getAverageFinishes($reg, $races);

    if (count($avg_finishes) == 0)
      return;

    // For speed sake, track the list of scored races by division, to
    // avoid looking it up more than once
    $scored_races = array();

    $affected_finishes = array();
    while (count($avg_finishes) > 0) {
      $finish = array_shift($avg_finishes);
      $hash = $finish->hash();

      // finishes that shall get the average score from this same team
      $div_finishes = array($finish); 
      $count = 0;
      $total = 0;

      $div = (string)$finish->race->division;
      if (!isset($scored_races[$div]))
        $scored_races[$div] = $reg->getScoredRaces($finish->race->division);

      // loop through other races in the same division to determine
      // the average score. In doing so, you may stumble onto other
      // entries also in avg_finishes. These entries are not involved
      // in determining the score. For efficiency, remove them from
      // the avg_finishes as they are encountered
      foreach ($scored_races[$div] as $r) {
        $other = $reg->getFinish($r, $finish->team);
        $ohash = $other->hash();
        if ($ohash != $hash) {
          if (!isset($avg_finishes[$ohash])) {
            $total += $other->score;
            $count++;
          }
          else {
            $div_finishes[] = $other;
            unset($avg_finishes[$ohash]);
          }
        }
      }

      // Actually assign the scores to the div_finishes
      $avg = ($count == 0) ? null : round($total / $count);
      foreach ($div_finishes as $finish) {
        $affected_finishes[$finish->hash()] = $finish;
        $modifier = $finish->getModifier();

        // no other scores to average
        if ($avg === null) {
          $finish->score = new Score($finish->earned,
                                     sprintf("(%d: no other finishes to average) %s", $finish->earned, $modifier->comments));
        }
        else {
          if ($avg <= $finish->earned) {
            $finish->score = new Score($avg, sprintf("(%d: average in division) %s", $avg, $modifier->comments));
          }
          else {
            $finish->score = new Score($finish->earned,
                                       sprintf("(%d: average (%d) is no better) %s", $finish->earned, $avg, $modifier->comments));
          }
        }
      } // end loop for divisions
    } // end loop through average finishes
    $reg->commitFinishes($affected_finishes);
  }
}
?>
