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
   * Sets the penalty amount for the given finish.
   *
   * Applies only to amount = -1 penalties.
   *
   * @param Regatta $reg the regatta
   * @return Score the penalty object
   */
  public function getPenaltyScore(Finish $fin, FinishModifier $pen) {
    if ($pen->amount <= 0) {
      if ($this->fleet === null)
	$this->fleet = count($fin->team->regatta->getTeams()) + 1;
      return new Score($this->fleet, sprintf("(%d, Fleet + 1) %s", $this->fleet, $pen->comments));
    }
    return new Score($pen->amount, sprintf("(%d, Assigned) %s", $pen->amount, $pen->comments));
  }

  protected $fleet = null;

  /**
   * Determine whether to advance score for next team
   *
   * @return boolean
   */
  protected function displaceScore(Finish $fin, FinishModifier $pen) {
    $bkdlist = Breakdown::getList();
    if (isset($bkdlist[$pen->type]))
      return true;

    if ($pen->amount <= 0)
      return false;
    return $pen->displace;
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

      // Resort assigned finishes
      for ($i = 0; $i < count($finishes); $i++) {
	$finish = $finishes[$i];
        $modifier = $finish->getModifier();
	if ($modifier !== null && $modifier->amount > 0 && $modifier->amount <= $i) {
	  unset($finishes[$i]);
	  array_splice($finishes, $modifier->amount - 1, 0, array($finish));
	}
      }

      $score = 1;
      foreach ($finishes as $finish) {
	$finish->earned = $score;
        $affected_finishes[] = $finish;
        $penalty = $finish->getModifier();
        if ($penalty == null) {
          // ------------------------------------------------------------
          // clean finish
          $finish->score = new Score($score);
          $score++;
        }
        elseif (isset($penlist[$penalty->type])) {
          // ------------------------------------------------------------
          // penalty
	  $penScore = $this->getPenaltyScore($finish, $penalty);
          if ($penScore->score > $score) {
            $finish->score = $penScore;
          }
          else {
            $finish->score = new Score($score,
                                       sprintf("(%d, penalty (%d) no worse) %s",
                                               $score,
                                               $penScore->score,
                                               $penalty->comments));
          }
	  if ($this->displaceScore($finish, $penalty))
	    $score++;

        }
        else {
          // ------------------------------------------------------------
          // breakdown
          // Should the amount be assigned, determine actual
          // score. If not, then keep track for average score
          if ($penalty->amount > 0) {
            $amount = $penalty->amount;
            $exp = sprintf("(%d, Assigned) %s", $amount, $penalty->comments);
            if ($score <= $penalty->amount) {
              $amount = $score;
              $exp = sprintf("(%d, Assigned amount (%d) no better than actual) %s",
                             $amount, $penalty->amount, $penalty->comments);
            }
            $finish->score = new Score($amount, $exp);
          }
        
	  if ($this->displaceScore($finish, $penalty))
	    $score++;
        }
        $finish->setModifier($penalty);
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
