<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package regatta
 */

require_once('regatta/ICSACombinedScorer.php');

/**
 * A derivative of the ICSACombinedScorer, this class serves only to
 * rank combined division regattas such that each division is ranked
 * separately. Note that the scoring portion is unchanged.
 *
 * @author Dayan Paez
 * @version 2010-01-28
 */
class ICSASpecialCombinedRanker extends ICSACombinedScorer {

  /**
   * Rank the team-division pairs separately.
   *
   * This ranking mechanism pits every team's division against every
   * other in the regatta.
   *
   * Note that only race numbers are used, since this is a combined
   * division regatta.
   *
   * @param Regatta $reg the regatta
   * @param Array:Race|null the list of races to limit rank
   * @return Array:Rank the ranked teams
   */
  public function rank(Regatta $reg, $races = null) {
    $divisions = array();
    foreach ($reg->getDivisions() as $div)
      $divisions[(string)$div] = $div;

    // 1. Create associative array of division => list of races,
    // depending on $races argument
    if ($races === null) {
      $races = array();
      foreach ($divisions as $id => $div)
        $races[$id] = $reg->getScoredRaces($div);
    }
    else {
      $race_nums = array();
      foreach ($races as $race)
        $race_nums[$race->number] = $race;

      $races = array();
      foreach ($divisions as $id => $div) {
        $races[$id] = array();
        foreach ($race_nums as $num)
          $races[$id][] = $reg->getRace($div, $num);
      }
    }

    // 2. Track the division for each rank
    $ranks = array();
    foreach ($races as $id => $list) {
      foreach ($reg->getRanks($list) as $rank) {
        $rank->division = $divisions[$id];
        if ($reg->getTeamPenalty($rank->team, $divisions[$id]) !== null)
          $rank->score += 20;
        $ranks[] = $rank;
      }
    }

    // sort the ranks according to score
    usort($ranks, 'Rank::compareScore');

    // 3. Settle ties
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
      if (count($tiedRanks) > 1)
        $tiedRanks = $this->settleHeadToHead($tiedRanks, $reg, $races); // @TODO
      foreach ($tiedRanks as $rank)
        $newOrder[] = $rank;
    }

    // Add the last team, if necessary
    if (count($newOrder) < $numTeams)
      $newOrder[] = $tiedRanks[$numTeams - 1];

    return $newOrder;
  }

  /**
   * Shuffle ranks according to who has won the most
   *
   * @param Array:Rank $ranks the tied ranks (more than one)
   * @param Regatta $reg the regatta in question
   * @param Array $races the map of division => races
   * @return Array $ranks the new order
   * @see ICSAScorer::settleHeadToHead
   * @see rank
   */
  protected function settleHeadToHead(Array $ranks, Regatta $reg, $races) {
    $numTeams = count($ranks);
    if ($numTeams < 2)
      return $ranks;

    // Go through each race and re-score based only on tied teams
    $headWins = array();
    $rankMap = array();
    foreach ($ranks as $rank) {
      $hash = $rank->hash();
      $headWins[$hash] = 0;
      $rankMap[$hash] = $rank;
      $rank->explanation = "Head-to-head tiebreaker";
    }

    $divisions = $reg->getDivisions();
    foreach ($races[(string)Division::A()] as $race_id => $race) {
      $scoreList = array();
      $rankList = array();
      foreach ($ranks as $rank) {
        $finish = $reg->getFinish($races[(string)$rank->division][$race_id], $rank->team);
        $scoreList[] = $finish->score;
        $rankList[] = $rank;
      }
      array_multisort($scoreList, $rankList);

      // Update headwins
      $thisScore = $scoreList[0];
      $priorPlace = 0;
      $key = $rankList[0]->hash();
      for ($i = 1; $i < $numTeams; $i++) {
        $nextScore = $scoreList[$i];
        $key = $rankList[$i]->hash();
        $place = $i;
        if ($nextScore == $thisScore)
          $place = $priorPlace;
        $headWins[$key] += $place;

        // Reset variables
        $priorPlace = $place;
        $thisScore = $nextScore;
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
      $tiedRanks = array($ranks[$i]);
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

      if (count($tiedRanks) > 1)
        $tiedRanks = $this->rankMostHighFinishes($tiedRanks, $reg, $races);

      // Update original list with these findinds
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
                                          $races,
                                          $placeFinish = 1) {

    // Base cases
    if (count($ranks) < 2)
      return $ranks;

    $fleetSize = count($reg->getTeams()) * count($reg->getDivisions());
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

      foreach ($races[(string)Division::A()] as $id => $race) {
        $finish = $reg->getFinish($races[(string)$rank->division][$id], $rank->team);
        if ($finish !== null && $finish->score == $placeFinish)
          $numHighFinishes[$t]++;
      }
    }

    // Rank according to most wins
    array_multisort($numHighFinishes, SORT_DESC, $ranksCopy);

    // Go through ranked list and remove those no longer in a tie
    $originalSpot = 0;
    $i = 0;
    while ($i < $numTeams) {
      $thisScore = $numHighFinishes[$i];
      $tiedRanks = array();
      $tiedRanks[] = $ranksCopy[$i];
      $i++;
      while ($i < $numTeams) {
        $nextScore = $numHighFinishes[$i];
        if ($thisScore != $nextScore)
          break;
        $tiedRanks[] = $ranksCopy[$i];
        $thisScore = $nextScore;
        $i++;
      }

      if (count($tiedRanks) > 1)
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
   * @param Array:ints $races the race numbers
   * @param int $race_index the index of the previously ranked race
   * @see ICSAScorer::rankByLastRace
   */
  protected function rankByLastRace(Array $ranks, Regatta $reg, $races, $race_index = null) {

    $numRanks = count($ranks);
    if ($numRanks < 2)
      return $ranks;

    if ($race_index === null)
      $race_index = count($races[(string)Division::A()]);
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
    foreach ($ranks as $rank) {
      $race = $races[(string)$rank->division][$race_index];
      $finish = $reg->getFinish($race, $rank->team);
      $scoreList[] = $finish->score;
      $rank->explanation = sprintf("According to last race (%s)", $race);
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
