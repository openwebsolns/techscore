<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package regatta
 */

require_once('conf.php');

/**
 * Scores a regatta according to ICSA rules. In addition, it deals
 * with tiebreakers according to their formula. For information, see
 * the ICSA procedural rules.
 *
 * 2010-02-24: Score combined divisions. For combined division
 * scoring, the finishes for all the divisions in the same race NUMBER
 * are combined into one virtual race, and points are awarded
 * accordingly. The FLEET value is then equal to the total number of
 * boats sailing: number of divisions * number of teams.
 *
 * In combined division scoring, "orphan" finishes (those race numbers
 * which are not finished in ALL divisions) are gracefully ignored
 * from the scoring process.
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
  protected function populateAverageFinishes(&$avg_finishes, Regatta $reg, $races) {
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
    $list = $reg->getFinishes($race);
    return $list;
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

    // map of finishes that need to be averaged
    $avg_finishes = array();

    $scored_races = array(); // track race numbers already scored
    $FLEET = null;
    foreach ($races as $race) {
      if (isset($scored_races[(string)$race]))
        continue;
      $scored_races[(string)$race] = $race;

      // Get each finish in order and set the score
      $finishes = $this->getFinishes($reg, $race);
      if ($FLEET === null)
        $FLEET = count($finishes);

      $score = 1;
      foreach ($finishes as $finish) {
        $penalty = $finish->getModifier();
        if ($penalty == null) {
          // ------------------------------------------------------------
          // clean finish
          $finish->score = new Score($score);
          $score++;
          $affected_finishes[] = $finish;
        }
        elseif ($penalty instanceof Penalty) {
          // ------------------------------------------------------------
          // penalty
          if ($penalty->amount <= 0) {
            $finish->score = new Score($FLEET + 1,
                                       sprintf("(%d, Fleet + 1) %s",
                                               $FLEET + 1,
                                               $penalty->comments));
          }
          elseif ($penalty->amount > $score) {
            $finish->score = new Score($penalty->amount,
                                       sprintf("(%d, Assigned) %s",
                                               $penalty->amount,
                                               $penalty->comments));
            if ($penalty->displace)
              $score++;
          }
          else {
            $finish->score = new Score($score,
                                       sprintf("(%d, Assigned penalty (%d) no worse) %s",
                                               $score,
                                               $penalty->amount,
                                               $penalty->comments));
            if ($penalty->displace)
              $score++;
          }
          $penalty->earned = $score;
          $affected_finishes[] = $finish;
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
            $penalty->earned = $score;
          }
          else {
            // for the time being, set their earned amount
            $avg_finishes[$finish->hash()] = $finish;
          }
          $penalty->earned = $score;
        
          // breakdowns always "displace"
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
    $this->populateAverageFinishes($avg_finishes, $reg, $races);

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

        // no other scores to average
        if ($avg === null) {
          $finish->score = new Score($finish->earned,
                                     sprintf("(%d: no other finishes to average) %s", $finish->earned, $finish->comments));
        }
        else {
          if ($avg <= $finish->earned) {
            $finish->score = new Score($avg, sprintf("(%d: average in division) %s", $avg, $finish->comments));
          }
          else {
            $finish->score = new Score($finish->earned,
                                       sprintf("(%d: average (%d) is no better) %s", $finish->earned, $avg, $finish->comments));
          }
        }
      } // end loop for divisions
    } // end loop through average finishes
    $reg->commitFinishes($affected_finishes);
  }

  /**
   * Ranks the teams of the regatta according to results from the
   * given set of races. If null, then just rank the whole regatta
   *
   * @param Regatta $reg the regatta
   * @param Division $division the division to rank, or all if null
   * @return Array<Rank> the ranked teams
   */
  public function rank(Regatta $reg, Division $division = null) {
    if ($division === null)
      $divisions = $reg->getDivisions();
    else
      $divisions = array($division);

    $ranks = $reg->getRanks($divisions);
    // deal with team penalties
    foreach ($ranks as $rank) {
      foreach ($divisions as $div) {
        if ($reg->getTeamPenalty($rank->team, $div) !== null)
          $rank->score += 20;
      }
    }

    // sort the ranks according to score
    usort($ranks, 'Rank::compareScore');

    // Settle ties
    $newOrder = array();
    $numTeams = count($ranks);
    $i = 0;
    while ($i < $numTeams) {
      $tiedRanks = array();
      $tiedRanks[] = $ranks[$i];

      $aScore = $ranks[$i]->score;
      $i++;
      while ($i < $numTeams) {
        $nextScore = $ranks[$i]->score;
        if ($nextScore != $aScore)
          break;
        $tiedRanks[] = $ranks[$i];
        $aScore = $nextScore;
        $i++;
      }

      // Head to head ties
      $tiedRanks = $this->settleHeadToHead($tiedRanks, $reg, $divisions);
      $newOrder = array_merge($newOrder, $tiedRanks);
    }

    // Add the last team, if necessary
    if (count($newOrder) < $numTeams)
      $newOrder[] = $tiedRanks[$numTeams - 1];

    return $newOrder;
  }

  /**
   * Reshuffle the list of teams so that they are ranked in order of
   * the number of times one of the teams scored better than another
   * of the teams in the list
   *
   * @param Array:Rank $ranks a list of tied ranks
   * @param Regatta $reg the regatta
   * @param Array:Division the list of races to consider
   */
  protected function settleHeadToHead(Array $ranks, Regatta $reg, Array $divisions) {
    $numTeams = count($ranks);
    if ($numTeams < 2)
      return $ranks;

    // Go through each race and score just the tied teams
    $headWins = array();
    $rankMap  = array();
    foreach ($ranks as $rank) {
      $headWins[$rank->team->id] = 0;
      $rank->explanation = "Head-to-head tiebreaker";
      $rankMap[$rank->team->id]  = $rank;
    }

    $races = array();
    foreach ($divisions as $div) {
      foreach ($reg->getScoredRaces($div) as $race) {
        $races[] = $race;
        $scoreList = array();
        $rankList  = array();
        foreach ($ranks as $rank) {
          $finish = $reg->getFinish($race, $rank->team);
          $scoreList[] = $finish->score;
          $rankList[] = $rank;
        }
        array_multisort($scoreList, $rankList);

        // Update headwins
        $thisScore = $scoreList[0];
        $priorPlace = 0;
        $key = $rankList[0]->team->id;
        $headWins[$key] += $priorPlace;
        for ($i = 1; $i < $numTeams; $i++) {
          $nextScore = $scoreList[$i];
          $key = $rankList[$i]->team->id;
          $place = $i;
          if ($nextScore == $thisScore)
            $place = $priorPlace;
          $headWins[$key] += $place;

          // Reset variables
          $priorPlace = $place;
          $thisScore  = $nextScore;
        }
      }
    }

    // Rank the teams again
    $scoreList = array();
    $i = 0;
    foreach ($headWins as $id => $total) {
      $ranks[$i++] = $rankMap[$id];
      $scoreList[] = $total;
    }
    array_multisort($scoreList, $ranks);

    // Determine if there are more ties
    $i = 0;
    $originalSpot = 0;
    while ($i < $numTeams) {
      $tiedRanks = array();
      $tiedRanks[] = $ranks[$i];
      $aScore = $scoreList[$i];
      $i++;
      while ($i < $numTeams) {
        $nextScore = $scoreList[$i];
        if ($nextScore != $aScore)
          break;

        // Update variables
        $tiedRanks[] = $ranks[$i];
        $aScore = $nextScore;
        $i++;
      }

      $tiedRanks = $this->rankMostHighFinishes($tiedRanks, $reg, $races);

      // Update original list with these findings
      foreach ($tiedRanks as $rank)
        $ranks[$originalSpot++] = $rank;
    }
    return $ranks;
  }

  /**
   * Recursive method for tiebreaking: rank the teams in order of
   * highest place finishes.
   *
   * @param Array:Rank $ranks the ranks
   * @param Regatta $reg the regatta
   * @param Array:Race $races the races
   * @param int $placeFinish = 1 by default, the place finish to check for
   */
  protected function rankMostHighFinishes(Array $ranks,
                                          Regatta $reg,
                                          Array $races,
                                          $placeFinish = 1) {

    // Base cases
    if (count($ranks) < 2)
      return $ranks;

    $fleetSize = count($reg->getTeams());
    if ($placeFinish > $fleetSize) {
      return $this->rankByLastRace($ranks, $reg, $races);
    }

    // Work with copy of ranks
    $ranksCopy = $ranks;

    $numTeams = count($ranks);
    $numHighFinishes = array();
    for ($t = 0; $t < $numTeams; $t++) {
      $numHighFinishes[$t] = 0;
      $rank = $ranks[$t];
      $rank->explanation = sprintf("Number of high-place (%d) finishes", $placeFinish);

      foreach ($races as $race) {
        $finish = $reg->getFinish($race, $rank->team);
        if ($finish !== null && $finish->score == $placeFinish)
          $numHighFinishes[$t]++;
      }
    }

    // Rank according to most wins
    $numRaces = count($races);
    $numWins = array();
    foreach ($numHighFinishes as $n)
      $numWins[] = $numRaces - $n;
    array_multisort($numWins, $ranksCopy);

    // Go through ranked list and remove those no longer in a tie
    $originalSpot = 0;
    $i = 0;
    while ($i < $numTeams) {
      $thisScore = $numWins[$i];
      $tiedRanks = array();
      $tiedRanks[] = $ranksCopy[$i];
      $i++;
      while ($i < $numTeams) {
        $nextScore = $numWins[$i];
        if ($thisScore != $nextScore)
          break;
        $tiedRanks[] = $ranksCopy[$i];
        $thisScore = $nextScore;
        $i++;
      }

      $tiedRanks = $this->rankMostHighFinishes($tiedRanks,
                                               $reg,
                                               $races,
                                               $placeFinish + 1);
      foreach ($tiedRanks as $rank)
        $ranks[$originalSpot++] = $rank;
    }
    return $ranks;
  }

  /**
   * Rank the teams by their performance in the last race
   *
   * @param Array<Rank> $ranks the ranks to sort
   * @param Regatta $reg the regatta
   * @param Array<Race> $races the races
   */
  protected function rankByLastRace(Array $ranks,
                                    Regatta $reg,
                                    Array $races) {

    $numRanks = count($ranks);
    if ($numRanks < 2)
      return $ranks;

    if (count($races) == 0) {
      // Let's go alphabetical
      foreach ($ranks as $rank)
        $rank->explanation = "Alphabetical";
      usort($ranks, "Rank::compareTeam");
      return $ranks;
    }

    // Get the last race scores. If combined scoring, remove other
    // races with the same number.
    $scoreList = array();
    $lastRace = array_pop($races);
    foreach ($ranks as $rank) {
      $finish = $reg->getFinish($lastRace, $rank->team);
      $scoreList[] = $finish->score;
      $rank->explanation = sprintf("According to last race (%s)", $lastRace);
    }
    array_multisort($scoreList, $ranks);

    // Check for more ties
    $ranksCopy = $ranks;
    $numTeams  = count($ranks);
    $i = 0;
    $originalSpot = 0;
    while ($i < $numTeams) {

      $thisScore = $scoreList[$i];
      $tiedRanks = array();
      $tiedRanks[] = $ranksCopy[$i];
      $i++;
      while ($i < $numTeams) {
        $nextScore = $scoreList[$i];
        if ($nextScore != $thisScore)
          break;
        $tiedRanks[] = $ranksCopy[$i];

        // Update variables
        $thisScore = $nextScore;
        $i++;
      }

      // Resolve ties
      $tiedRanks = $this->rankByLastRace($tiedRanks,
                                         $reg,
                                         $races);
      foreach ($tiedRanks as $rank)
        $ranks[$originalSpot++] = $rank;
    }
    return $ranks;
  }

  /**
   * Determines average of the array given
   *
   * @param Array<number> $list the list
   * @return int the average of those numbers
   */
  protected static function average(Array $list) {
    $num = 0;
    $total = 0;
    foreach ($list as $n) {
      $total += $n;
      $num++;
    }
    if ($num == 0)
      return null;
    return round($total / $num);
  }

}
?>
