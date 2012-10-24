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
   * Helper method to identify any additional average-scored finishes
   * in the given list of races
   *
   */
  protected function &getAverageFinishes(Regatta $reg, $races) {
    $avg_finishes = array();
    foreach ($reg->getDivisions() as $div) {
      foreach ($reg->getAverageFinishes($div) as $finish) {
        if (!isset($avg_finishes[$finish->hash()]))
          $avg_finishes[$finish->hash()] = $finish;
      }
    }
    return $avg_finishes;
  }

  protected function &getFinishes(Regatta $reg, Race $race) {
    $finishes = array();
    foreach ($reg->getDivisions() as $div) {
      $r = $reg->getRace($div, $race->number);
      foreach ($reg->getFinishes($r) as $fin)
        $finishes[] = $fin;
    }
    usort($finishes, "Finish::compareEntered");
    return $finishes;
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
  protected function rankByLastRace(Array $ranks, Regatta $reg, $races, $race_index = null) {

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
