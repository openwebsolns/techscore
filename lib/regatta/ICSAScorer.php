<?php
/**
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
 * @author Dayan Paez
 * @created 2010-01-28
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
  private function scoreCombined(Regatta $reg) {

    $teams = $reg->getTeams();
    $divs  = $reg->getDivisions();
    $FLEET = count($teams) * count($divs);

    if ($FLEET == 0) return;

    // Go through all the race numbers, while keeping track of average
    // scores for each team within each division
    $div_scores = array();
    $avg_finishes = array();
    $avg_finishes_real = array(); // keep track of would-be score
    foreach ($divs as $div)
      $div_scores[(string)$div] = array();

    foreach ($reg->getCombinedScoredRaces() as $num) {

      // create list of finishes for all races with this number
      $finishes = array();
      foreach ($divs as $div) {
	$race = $reg->getRace($div, $num);
	$finishes = array_merge($finishes, $reg->getFinishes($race));
      }
      usort($finishes, "Finish::compareEntered");
      
      $score = 1;
      foreach ($finishes as $finish) {
	$div_index = (string)$finish->race->division;

	if (!isset($div_scores[$div_index][$finish->team->id]))
	  $div_scores[$div_index][$finish->team->id] = array();

	// no penalties or breakdown
	if ($finish->penalty == null) {
	  $div_scores[$div_index][$finish->team->id][] = $score;
	  $finish->score = new Score($score, $score);
	  $score++;
	}
	// penalty
	elseif ($finish->penalty instanceof Penalty) {
	  $div_scores[$div_index][$finish->team->id][] = $FLEET + 1;
	  $finish->score = new Score($finish->penalty->type,
				     $FLEET + 1,
				     sprintf("(%d, Fleet + 1)", $FLEET + 1));
	}
	// breakdown
	else {
	  // Should the amount be assigned, determine actual
	  // score. If not, then keep track for average score
	  if ($finish->penalty->amount > 0) {
	    $amount = $finish->penalty->amount;
	    $exp = sprintf("(%d, Assigned)", $amount);
	    if ($score <= $finish->penalty->amount) {
	      $amount = $score;
	      $exp = sprintf("%d, Assigned amount (%d) no better than actual.",
			     $amount, $finish->penalty->amount);
	    }
	    $div_scores[$div_index][$finish->team->id][] = $amount;
	    $finish->score = new Score($finish->penalty->type, $amount, $exp);
	  }
	  else {
	    $avg_finishes[] = $finish;
	    $avg_finishes_real[$finish->id] = $score;
	  }
	  $score++;
	}
      }
    }

    // Deal with average scores
    foreach ($avg_finishes as $finish) {
      $avg = ICSAScorer::average($div_scores[(string)$finish->race->division][$finish->team->id]);
      if ($avg == null) {
	$finish->score = new Score($finish->penalty->type,
				   $avg_finishes_real[$finish->id],
				   sprintf("(Actual: %d, no other scores to average)",
					   $avg_finishes_real[$finish->id]));
      }
      elseif ($avg < $avg_finishes_real[$finish->id]) {
	$finish->score = new Score($finish->penalty->type,
				   $avg,
				   sprintf("(%d, average within division)", $avg));
      }
      else {
	$finish->score = new Score($finish->penalty->type,
				   $avg_finishes_real[$finish->id],
				   sprintf("(Actual: %d, average (%d) no better)",
					   $avg_finishes_real[$finish->id],
					   $avg));
      }
    }
  }

  /**
   * Scores the given regatta
   *
   * @param Regatta $reg the regatta to score
   */
  public function score(Regatta $reg) {

    if ($reg->get(Regatta::SCORING) == Regatta::SCORING_COMBINED) {
      $this->scoreCombined($reg);
      return;
    }

    $teams = $reg->getTeams();
    $FLEET = count($teams);

    // Go through all the races, one division at a time, while keeping
    // track of average scores for each team
    foreach ($reg->getDivisions() as $division) {
      $div_scores = array();
      $avg_finishes = array();
      $avg_finishes_real = array(); // keep track of would-be score
      foreach ($reg->getRaces($division) as $race) {

	// Get each finish in order and set the score
	$score = 1;
	foreach ($reg->getFinishes($race) as $finish) {
	  if (!isset($div_scores[$finish->team->id]))
	    $div_scores[$finish->team->id] = array();

	  // no penalties or breakdown
	  if ($finish->penalty == null) {
	    $div_scores[$finish->team->id][] = $score;
	    $finish->score = new Score($score, $score);
	    $score++;
	  }
	  // penalty
	  elseif ($finish->penalty instanceof Penalty) {
	    $div_scores[$finish->team->id][] = $FLEET + 1;
	    $finish->score = new Score($finish->penalty->type,
				       $FLEET + 1,
				       sprintf("(%d, Fleet + 1)", $FLEET + 1));
	  }
	  // breakdown
	  else {
	    // Should the amount be assigned, determine actual
	    // score. If not, then keep track for average score
	    if ($finish->penalty->amount > 0) {
	      $amount = $finish->penalty->amount;
	      $exp = sprintf("(%d, Assigned)", $amount);
	      if ($score <= $finish->penalty->amount) {
		$amount = $score;
		$exp = sprintf("%d, Assigned amount (%d) no better than actual.",
			       $amount, $finish->penalty->amount);
	      }
	      $div_scores[$finish->team->id][] = $amount;
	      $finish->score = new Score($finish->penalty->type, $amount, $exp);
	    }
	    else {
	      $avg_finishes[] = $finish;
	      $avg_finishes_real[$finish->id] = $score;
	    }
	    $score++;
	  }
	}
      }

      // Deal with average scores
      foreach ($avg_finishes as $finish) {
	$avg = ICSAScorer::average($div_scores[$finish->team->id]);
	if ($avg == null) {
	  $finish->score = new Score($finish->penalty->type,
				     $avg_finishes_real[$finish->id],
				     sprintf("(Actual: %d, no other scores to average)",
					     $avg_finishes_real[$finish->id]));
	}
	elseif ($avg < $avg_finishes_real[$finish->id]) {
	  $finish->score = new Score($finish->penalty->type,
				     $avg,
				     sprintf("(%d, average within division)", $avg));
	}
	else {
	  $finish->score = new Score($finish->penalty->type,
				     $avg_finishes_real[$finish->id],
				     sprintf("(Actual: %d, average (%d) no better)",
					     $avg_finishes_real[$finish->id],
					     $avg));
	}
      }
    }
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
    if ($division == null) {
      $divisions = $reg->getDivisions();
      if ($reg->get(Regatta::SCORING) == Regatta::SCORING_COMBINED)
	$this->rankCombined = true;
    }
    else
      $divisions = array($division);

    $races = $reg->getScoredRaces($division);
    usort($races, "Race::compareNumber");
    $teamList = $reg->getTeams();

    // Total the score for each team
    $totalList = array();
    $rankList = array();
    foreach ($teamList as $team) {
      $total = 0;
      foreach ($races as $race) {
	$f = $reg->getFinish($race, $team);
	$total += $f->score->score;
      }
      foreach ($divisions as $division) {
	foreach ($reg->getTeamPenalties($team, $division) as $pen) {
	  if (count($pen) > 0) {
	    $total += 20;
	  }
	}
      }
      $totalList[] = $total;
      $rankList[]  = new Rank($team, "Natural order");
    }

    // Order
    array_multisort($totalList, $rankList);

    // Settle ties
    $newOrder = array();
    $numTeams = count($teamList);
    $i = 0;
    while ($i < $numTeams) {
      $tiedRanks = array();
      $tiedRanks[] = $rankList[$i];

      $aScore = $totalList[$i];
      $i++;
      while ($i < $numTeams) {
	$nextScore = $totalList[$i];
	if ($nextScore != $aScore)
	  break;
	$tiedRanks[] = $rankList[$i];
	$aScore = $nextScore;
	$i++;
      }

      // Head to head ties
      $tiedRanks = $this->settleHeadToHead($tiedRanks, $reg, $races);
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
   * @param Array<Rank> $ranks a list of tied ranks
   * @param Regatta $reg the regatta
   * @param Array<Race> the list of races to consider
   */
  private function settleHeadToHead(Array $ranks, Regatta $reg, Array $races) {
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

    foreach ($races as $race) {
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
   * @param Array<Rank> $ranks the ranks
   * @param Regatta $reg the regatta
   * @param Array<Race> $races the race
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
	if ($finish->score->score == $placeFinish)
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
	  $race = $reg->getRace($div, $lastNum);
	  $finish = $reg->getFinish($race, $rank->team);
	  $total += $finish->score->score;
	  $rank->explanation = sprintf("According to last race across all divisions (%s)", $lastNum);
	}
	$scoreList[] = $total;
      }
    }
    else {
      $lastRace = array_pop($races);

      foreach ($ranks as $rank) {
	$finish = $reg->getFinish($lastRace, $rank->team);
	$scoreList[] = $finish->score->score;
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
    return $num / $total;
  }
  
}

if (basename(__FILE__) == $argv[0]) {

  // $reg->doScore();
  $reg = new Regatta(96);
  foreach ($reg->scorer->rank($reg) as $rank)
    print(sprintf("%6s | %s\n", $rank->team->school->id, $rank->explanation));
}
?>