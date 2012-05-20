<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package regatta
 */

require_once('regatta/ICSAScorer.php');

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
class ICSACombinedScorer extends ICSAScorer {

  /**
   * Scores the given regatta using the combined division method
   *
   * @param Regatta $reg the regatta to score
   */
  public function score(Regatta $reg, Race $race) {
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
      $penalty = $finish->getModifier();
      if ($penalty == null) {
	$finish->score = new Score($score);
	$score++;
      }
      // penalty
      elseif ($penalty instanceof Penalty) {
	if ($penalty->amount <= 0)
	  $finish->score = new Score($FLEET + 1,
				     sprintf("(%d, Fleet + 1) %s", $FLEET + 1, $penalty->comments));
	elseif ($penalty->amount > $score) {
	  $finish->score = new Score($penalty->amount,
				     sprintf("(%d, Assigned) %s", $penalty->amount, $penalty->comments));
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
	$finish->earned = $score;
      }
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
	  $finish->earned = $score;
	}
	else {
	  // for the time being, set their earned amount
	  $avg_finishes[] = $finish;
	}
	$finish->earned = $score;
	$score++;
      }
    }

    // Part 2: deal with average finishes, including those from across
    // the regatta, not just this race
    foreach ($divs as $div) {
      foreach ($reg->getAverageFinishes($div) as $finish)
	$avg_finishes[] = $finish;
    }
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
	  $fin->score = new Score($fin->earned,
				  sprintf("(%d: no other finishes to average) %s", $fin->earned, $fin->comments));
	}
      }
      else {
	$avg = round($total / $count);
	foreach ($div_finishes as $fin) {
	  if ($avg <= $fin->earned) {
	    $fin->score = new Score($avg,
				    sprintf("(%d: average in division) %s", $avg, $fin->comments));
	  }
	  else {
	    $fin->score = new Score($fin->earned,
				    sprintf("(%d: average (%d) is no better) %s", $fin->earned, $avg, $fin->comments));
	  }
	}
      }
    } // end loop through average finishes
    $reg->commitFinishes($affected_finishes);
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
      return $this->rankByLastRace($ranks, $reg, $reg->getCombinedScoredRaces());
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
    $divisions = $reg->getDivisions();
    $lastNum  = array_pop($races);

    $scoreList = array();
    foreach ($ranks as $rank) {
      $total = 0;
      foreach ($divisions as $div) {
	$race = $reg->getRace($div, $lastNum);
	$finish = $reg->getFinish($race, $rank->team);
	$total += $finish->score;
	$rank->explanation = sprintf("According to last race across all divisions (%s)", $lastNum);
      }
      $scoreList[] = $total;
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
}
?>
