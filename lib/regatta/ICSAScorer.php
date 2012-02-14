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

  // When ranking combined-scoring regattas for full scores (i.e., not
  // within a particular division), ranking by last race takes on a
  // new meaning, since it's the highest-numbered race across all
  // divisions. This variable is set by the 'rank' method should no
  // division be specified and the regatta use combined scoring
  private $rankCombined = false;

  /**
   * Scores the given regatta using the combined division method
   *
   * @param Regatta $reg the regatta to score
   */
  private function scoreCombined(Regatta $reg, Race $race) {
    $teams = $reg->getTeams();
    $divs  = $reg->getDivisions();
    $FLEET = count($teams) * count($divs);

    if ($FLEET == 0) return;

    // Go through the races across the divisions for the race number
    // given in the argument
    $finishes = array();
    foreach ($divs as $div) {
      $r = $reg->getRace($div, $race->number);
      foreach ($reg->getFinishes($r) as $fin)
	$finishes[] = $fin;
    }
    if (count($finishes) != 0 && count($finishes) != $FLEET)
      throw new InvalidArgumentException("Some divisions seem to be missing combined finishes for race $race");

    usort($finishes, "Finish::compareEntered");
    $affected_finishes = $finishes; // these, and the average
				    // finishes, need to be commited
				    // to database. So track 'em!

    $avg_finishes = array();
    $score = 1;
    foreach ($finishes as $i => $finish) {
      // no penalties or breakdown
      if ($finish->penalty == null) {
	$finish->score = new Score($score);
	$score++;
      }
      // penalty
      elseif ($finish->penalty instanceof Penalty) {
	if ($finish->penalty->amount <= 0)
	  $finish->score = new Score($FLEET + 1,
				     sprintf("(%d, Fleet + 1) %s", $FLEET + 1, $finish->penalty->comments));
	elseif ($finish->penalty->amount > $score) {
	  $finish->score = new Score($finish->penalty->amount,
				     sprintf("(%d, Assigned) %s",
					     $finish->penalty->amount,
					     $finish->penalty->comments));
	  if ($finish->penalty->displace)
	    $score++;
	}
	else {
	  $finish->score = new Score($score,
				     sprintf("(%d, Assigned penalty (%d) no worse) %s",
					     $score,
					     $finish->penalty->amount,
					     $finish->penalty->comments));
	  if ($finish->penalty->displace)
	    $score++;
	}
	$finish->penalty->earned = $score;
      }
      // breakdown
      else {
	// Should the amount be assigned, determine actual
	// score. If not, then keep track for average score
	if ($finish->penalty->amount > 0) {
	  $amount = $finish->penalty->amount;
	  $exp = sprintf("(%d, Assigned) %s", $amount, $finish->penalty->comments);
	  if ($score <= $finish->penalty->amount) {
	    $amount = $score;
	    $exp = sprintf("(%d, Assigned amount (%d) no better than actual) %s",
			   $amount, $finish->penalty->amount, $finish->penalty->comments);
	  }
	  $finish->score = new Score($amount, $exp);
	  $finish->penalty->earned = $score;
	}
	else {
	  // for the time being, set their earned amount
	  $avg_finishes[] = $finish;
	}
	$finish->penalty->earned = $score;
	$score++;
      }
    }

    // Part 2: deal with average finishes, including those from across
    // the regatta, not just this race
    foreach ($divs as $div)
      $avg_finishes = array_merge($avg_finishes, $reg->getAverageFinishes($div));
    while (count($avg_finishes) > 0) {
      $finish = array_shift($avg_finishes);

      // finishes that shall get the average score from this same team
      $div_finishes = array(); 
      $count = 0;
      $total = 0;

      foreach ($reg->getScoredRaces($finish->race->division) as $r) {
	$fin = $reg->getFinish($r, $finish->team);
	if ($fin == $finish) {
	  $div_finishes[] = $fin;
	}
	elseif (($i = array_search($fin, $avg_finishes)) === false) {
	  $total += $fin->score;
	  $count++;
	}
	else {
	  $affected_finishes[] = $fin;
	  $div_finishes[] = $fin;
	  unset($avg_finishes[$i]);
	}
      }

      // no other scores to average
      if ($count == 0) {
	foreach ($div_finishes as $fin) {
	  $fin->score = new Score($fin->penalty->earned,
				  sprintf("(%d: no other finishes to average) %s",
					  $fin->penalty->earned, $fin->penalty->comments));
	}
      }
      else {
	$avg = round($total / $count);
	foreach ($div_finishes as $fin) {
	  if ($avg <= $fin->penalty->earned) {
	    $fin->score = new Score($avg,
				    sprintf("(%d: average in division) %s",
					    $avg, $fin->penalty->comments));
	  }
	  else {
	    $fin->score = new Score($fin->penalty->earned,
				    sprintf("(%d: average (%d) is no better) %s",
					    $fin->penalty->earned, $avg, $fin->penalty->comments));
	  }
	}
      }
    } // end loop through average finishes
    $reg->commitFinishes($affected_finishes);
  }

  /**
   * Scores the given regatta, and commits the affected finishes back
   * to the database.
   *
   * @param Regatta $reg the regatta to score
   * @param Array:Races the optional list of races to score. Leave
   * empty to score the entire regatta. Yes, the races should belong
   * to the regatta you pass.
   */
  public function score(Regatta $reg, Race $race) {

    if ($reg->scoring == Regatta::SCORING_COMBINED) {
      $this->scoreCombined($reg, $race);
      return;
    }

    $teams = $reg->getTeams();
    $FLEET = count($teams);

    // Get each finish in order and set the score
    $score = 1;
    $finishes = $reg->getFinishes($race);
    // track the finishes which need to be committed to database
    $affected_finishes = array();
    foreach ($finishes as $finish)
      $affected_finishes[] = $finish;
    $avg_finishes = array(); // list of finishes that need to be averaged
    $finishes = $affected_finishes;
    foreach ($finishes as $finish) {
      $penalty = $finish->getModifier();
      // ------------------------------------------------------------
      // clean finish
      if ($penalty == null) {
	$finish->score = new Score($score);
	$score++;
      }
      // ------------------------------------------------------------
      // penalty
      elseif ($penalty instanceof Penalty) {
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
      }
      // ------------------------------------------------------------
      // breakdown
      else {
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
	  $avg_finishes[] = $finish;
	}
	$penalty->earned = $score;
	// breakdowns always "displace"
	$score++;
      }
      $finish->setModifier($penalty);
    }

    // Part 2: deal with average finishes, including those from across
    // the regatta, not just this race
    foreach ($reg->getAverageFinishes($race->division) as $finish)
      $avg_finishes[] = $finish;
    while (count($avg_finishes) > 0) {
      $finish = array_shift($avg_finishes);

      // finishes that shall get the average score from this same team
      $div_finishes = array(); 
      $count = 0;
      $total = 0;
      foreach ($reg->getScoredRaces($race->division) as $r) {
	$fin = $reg->getFinish($r, $finish->team);
	if ($fin == $finish) {
	  $div_finishes[] = $fin;
	}
	elseif (($i = array_search($fin, $avg_finishes)) === false) {
	  $total += $fin->score;
	  $count++;
	}
	else {
	  $div_finishes[] = $fin;
	  $affected_finishes[] = $fin;
	  unset($avg_finishes[$i]);
	}
      }

      // no other scores to average
      if ($count == 0) {
	foreach ($div_finishes as $fin) {
	  $fin->score = new Score($fin->penalty->earned,
				  sprintf("(%d: no other finishes to average) %s",
					  $fin->penalty->earned, $fin->penalty->comments));
	}
      }
      else {
	$avg = round($total / $count);
	foreach ($div_finishes as $fin) {
	  if ($avg <= $fin->penalty->earned) {
	    $fin->score = new Score($avg,
				    sprintf("(%d: average in division) %s",
					    $avg, $fin->penalty->comments));
	  }
	  else {
	    $fin->score = new Score($fin->penalty->earned,
				    sprintf("(%d: average (%d) is no better) %s",
					    $fin->penalty->earned, $avg, $fin->penalty->comments));
	  }
	}
      }
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
  private function settleHeadToHead(Array $ranks, Regatta $reg, Array $divisions) {
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
  private function rankMostHighFinishes(Array $ranks,
					Regatta $reg,
					Array $races,
					$placeFinish = 1) {

    // Base cases
    if (count($ranks) < 2)
      return $ranks;

    $fleetSize = count($reg->getTeams());
    if ($placeFinish > $fleetSize) {
      // There are still ties, go to the third tiebreaker
      // In case of combined scoring, use list of race numbers
      $list = ($this->rankCombined) ? $reg->getCombinedScoredRaces() : $races;
      return $this->rankByLastRace($ranks, $reg, $list);
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
  private function rankByLastRace(Array $ranks,
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
    if ($this->rankCombined) {
      $divisions = $reg->getDivisions();
      $lastNum  = array_pop($races);

      $scoreList = array();
      foreach ($ranks as $rank) {
	$total = 0;
	foreach ($divisions as $div) {
// @TODO getRace()
	  $race = $reg->getRace($div, $lastNum);
	  $finish = $reg->getFinish($race, $rank->team);
	  $total += $finish->score;
	  $rank->explanation = sprintf("According to last race across all divisions (%s)", $lastNum);
	}
	$scoreList[] = $total;
      }
    }
    else {
      $lastRace = array_pop($races);

      foreach ($ranks as $rank) {
	$finish = $reg->getFinish($lastRace, $rank->team);
	$scoreList[] = $finish->score;
	$rank->explanation = sprintf("According to last race (%s)", $lastRace);
      }
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
  private static function average(Array $list) {
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
