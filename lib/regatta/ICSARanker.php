<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package regatta
 */

/**
 * Ranks the teams in a given regatta.
 *
 * Follows ICSA procedural rules for breaking ties, which involves
 * using head-to-head, then number of high place finishes, then last
 * race, lastly alphabetical.
 *
 * @author Dayan Paez
 * @created 2012-11-13
 */
class ICSARanker {

  /**
   * Rank the teams of the regatta according to given races.
   *
   * If $races is null, rank team across all races
   *
   * @param Regatta $reg the regatta
   * @param Array:Race|null the list of races to limit rank
   * @return Array:Rank the ranked teams
   */
  public function rank(FullRegatta $reg, $races = null) {
    if ($races === null) {
      $races = $reg->getScoredRaces();
      $divisions = $reg->getDivisions();
    }
    else {
      $divisions = array();
      foreach ($races as $race)
        $divisions[(string)$race->division] = $race->division;
    }

    $ranks = array();
    foreach ($reg->getTeamTotals($races) as $rank) {
      foreach ($divisions as $div) {
        // deal with team penalties
        $penalty = $reg->getDivisionPenalty($rank->team, $div);
        if ($penalty !== null) {
          $rank->score += $penalty->amount;
        }
      }
      $ranks[] = $rank;
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
        $i++;
      }

      // Head to head ties
      $tiedRanks = $this->settleHeadToHead($tiedRanks, $reg, $races);
      $newOrder = array_merge($newOrder, $tiedRanks);
    }

    // Add the last team, if necessary
    if (count($newOrder) < $numTeams)
      $newOrder[] = $tiedRanks[$numTeams - 1];

    // assign rank number successively
    foreach ($newOrder as $i => $rank)
      $rank->rank = ($i + 1);
    return $newOrder;
  }

  /**
   * Reshuffle the list of teams so that they are ranked in order of
   * the number of times one of the teams scored better than another
   * of the teams in the list
   *
   * @param Array:Rank $ranks a list of tied ranks
   * @param Regatta $reg the regatta
   * @param Array:Race the list of races to consider
   */
  protected function settleHeadToHead(Array $ranks, FullRegatta $reg, $races) {
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
   * @param Array:Rank $ranks the ranks
   * @param Regatta $reg the regatta
   * @param Array:Race $races the races
   * @param int $placeFinish = 1 by default, the place finish to check for
   */
  protected function rankMostHighFinishes(Array $ranks,
                                          FullRegatta $reg,
                                          $races,
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
   * @param Array:Rank $ranks the ranks to sort
   * @param Regatta $reg the regatta
   * @param Array:Race $races the races
   * @param int $race_index the index of the race previously
   * checked. The function will check the previous index.
   */
  protected function rankByLastRace(Array $ranks, FullRegatta $reg, $races, $race_index = null) {

    $numRanks = count($ranks);
    if ($numRanks < 2)
      return $ranks;

    if ($race_index === null)
      $race_index = count($races);
    $race_index--;
    if ($race_index < 0) {
      // Let's go alphabetical
      foreach ($ranks as $rank)
        $rank->explanation = "Alphabetical";
      usort($ranks, "Rank::compareTeam");
      return $ranks;
    }

    // Get the last race scores. If combined scoring, remove other
    // races with the same number.
    $scoreList = array();
    $lastRace = $races[$race_index];
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
      $tiedRanks = $this->rankByLastRace($tiedRanks, $reg, $races, $race_index);
      foreach ($tiedRanks as $rank)
        $ranks[$originalSpot++] = $rank;
    }
    return $ranks;
  }
}
?>
