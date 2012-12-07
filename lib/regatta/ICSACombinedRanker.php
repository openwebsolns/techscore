<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package regatta
 */

require_once('regatta/ICSARanker.php');

/**
 * Ranks a regatta according to ICSA rules.
 *
 * Ranks the *full* team, bundling all the divisions in the process.
 * This differs from the division-specific ranking also used for
 * combined division events, wherein two divisions from the same team
 * are ranked against each other. For that process, see
 * ICSASpecialCombinedRanker.
 *
 * @author Dayan Paez
 * @created 2012-11-13
 */
class ICSACombinedRanker extends ICSARanker {
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

    $fleetSize = count($reg->getTeams()) * count($reg->getDivisions());
    if ($placeFinish > $fleetSize) {
      $nums = array();
      foreach ($races as $race)
        $nums[] = $race->number;
      return $this->rankByLastRace($ranks, $reg, $nums);
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
   * @param Array:ints $races the race numbers
   * @param int $race_index the index of the previously ranked race
   * @see ICSAScorer::rankByLastRace
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
    $divisions = $reg->getDivisions();
    $lastNum  = $races[$race_index];

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
      $tiedRanks = $this->rankByLastRace($tiedRanks, $reg, $races, $race_index);
      foreach ($tiedRanks as $rank)
        $ranks[$originalSpot++] = $rank;
    }
    return $ranks;
  }
}
?>