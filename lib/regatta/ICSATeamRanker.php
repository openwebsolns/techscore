<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package regatta
 */

require_once('ICSARanker.php');
require_once('Rank.php');

/**
 * Ranks a team-racing regatta according to win percentages.
 *
 * But only among teams that have no rank already established. This
 * should technically be either all or none, but may not be the case
 * if the regatta is already under way. Unranked teams will always be
 * listed BELOW already ranked teams.
 *
 * @author Dayan Paez
 * @created 2013-01-10
 */
class ICSATeamRanker extends ICSARanker {

  /**
   * Ranks the team according to their winning percentages.
   *
   * Respect the locked ranks, and also the groupings
   */
  public function rank(FullRegatta $reg, $races = null, $ignore_rank_groups = false) {
    $divisions = $reg->getDivisions();
    if ($races === null)
      $races = $reg->getScoredRaces(Division::A());

    // Track matchups as Team ID => Team ID => Races in A
    $matchups = array();

    // Teams of interest
    $teams = array();

    // Determine records for each team in given races
    $records = array();
    foreach ($races as $race) {
      if ($race->tr_team1 === null || $race->tr_team2 === null)
        continue;

      $teams[$race->tr_team1->id] = $race->tr_team1;
      $teams[$race->tr_team2->id] = $race->tr_team2;

      $a_finishes = $reg->getFinishes($race);
      if (count($a_finishes) == 0)
        continue;

      // initialize TeamRank objects
      if (!isset($records[$race->tr_team1->id])) {
        $records[$race->tr_team1->id] = new TeamRank($race->tr_team1);
        $matchups[$race->tr_team1->id] = array();
      }
      if (!isset($records[$race->tr_team2->id])) {
        $records[$race->tr_team2->id] = new TeamRank($race->tr_team2);
        $matchups[$race->tr_team2->id] = array();
      }

      if (!isset($matchups[$race->tr_team1->id][$race->tr_team2->id]))
        $matchups[$race->tr_team1->id][$race->tr_team2->id] = array();

      if (!isset($matchups[$race->tr_team2->id][$race->tr_team1->id]))
        $matchups[$race->tr_team2->id][$race->tr_team1->id] = array();

      $matchups[$race->tr_team1->id][$race->tr_team2->id][$race->number] = array();
      $matchups[$race->tr_team2->id][$race->tr_team1->id][$race->number] = array();

      $finishes = array();
      foreach ($a_finishes as $finish) {
        $finishes[] = $finish;
        if ($finish->team == $race->tr_team1)
          $matchups[$race->tr_team1->id][$race->tr_team2->id][$race->number][] = $finish;
        else
          $matchups[$race->tr_team2->id][$race->tr_team1->id][$race->number][] = $finish;
      }
      for ($i = 1; $i < count($divisions); $i++) {
        foreach ($reg->getFinishes($reg->getRace($divisions[$i], $race->number)) as $finish) {
          $finishes[] = $finish;
          if ($finish->team == $race->tr_team1)
            $matchups[$race->tr_team1->id][$race->tr_team2->id][$race->number][] = $finish;
          else
            $matchups[$race->tr_team2->id][$race->tr_team1->id][$race->number][] = $finish;
        }
      }

      // "Tiebreaker" races do not factor into records
      if ($race->round->sailoff_for_round !== null)
        continue;

      $myScore = 0;
      $theirScore = 0;

      foreach ($finishes as $finish) {
        if ($finish->team->id == $race->tr_team1->id)
          $myScore += $finish->score;
        else
          $theirScore += $finish->score;
      }
      if ($race->tr_ignore1 === null) {
        if ($myScore < $theirScore)
          $records[$race->tr_team1->id]->wins++;
        elseif ($myScore > $theirScore)
          $records[$race->tr_team1->id]->losses++;
        else
          $records[$race->tr_team1->id]->ties++;
      }
      if ($race->tr_ignore2 === null) {
        if ($myScore < $theirScore)
          $records[$race->tr_team2->id]->losses++;
        elseif ($myScore > $theirScore)
          $records[$race->tr_team2->id]->wins++;
        else
          $records[$race->tr_team2->id]->ties++;
      }
    }

    $min_rank = 1;
    $all_ranks = array();
    if ($ignore_rank_groups === false) {
      foreach ($reg->getTeamsInRankGroups($teams) as $group) {
        $group = $this->rankGroup($reg, $group, $records, $min_rank, $matchups);
        foreach ($group as $rank)
          $all_ranks[] = $rank;
        $min_rank += count($group);
      }
    }
    else {
      foreach ($this->rankGroup($reg, $teams, $records, $min_rank, $matchups, true) as $rank)
        $all_ranks[] = $rank;
    }
    return $all_ranks;
  }

  /**
   * Ranks the team according to their winning percentages.
   *
   * Respect the locked ranks, and also the groupings, unless
   * otherwise noted with $ignore_locks
   */
  public function rankGroup(FullRegatta $reg, $teams, &$ranks, $min_rank, Array $matchups, $ignore_locks = false) {
    $max_rank = $min_rank + count($teams) - 1;

    // separate teams into "locked" and "open" groups
    $locked_records = array();
    $open_records = array();
    foreach ($teams as $team) {
      $rank = (isset($ranks[$team->id])) ? $ranks[$team->id] : new TeamRank($team);
      if ($team->lock_rank !== null && $team->dt_rank !== null && $ignore_locks === false) {
        if ($team->dt_rank < $min_rank || $team->dt_rank > $max_rank)
          throw new InvalidArgumentException(sprintf("Locked rank of %d for %s outside the range of group %d-%d.", $team->dt_rank, $team, $team->dt_rank, $min_rank, $max_rank));
        $locked_records[] = $rank;
      }
      else
        $open_records[] = $rank;
    }

    usort($locked_records, function(TeamRank $r1, TeamRank $r2) {
        if ($r1->team->dt_rank < $r2->team->dt_rank)
          return -1;
        if ($r1->team->dt_rank > $r2->team->dt_rank)
          return 1;
        return strcmp((string)$r1->team, (string)$r2->team);
      });

    usort($open_records, function(TeamRank $a, TeamRank $b) {
        $perA = $a->getWinPercentage();
        $perB = $b->getWinPercentage();
        if ($perA == $perB) {
          if ($a->wins == $b->wins) {
            if ($a->losses == $b->losses) {
              return 0;
            }
            return $a->losses - $b->losses;
          }
          return $b->wins - $a->wins;
        }
        if ($perA - $perB > 0)
          return -1;
        return 1;
      });

    // separate into tiedGroups and tiebreak as necessary
    $tiedGroups = array();
    foreach ($open_records as $rank) {
      $id = $rank->getRecord();
      if (!isset($tiedGroups[$id]))
        $tiedGroups[$id] = array();
      $tiedGroups[$id][] = $rank;
    }

    // breakties
    $newGroups = array();
    foreach ($tiedGroups as $group) {
      if (count($group) > 1) {
        foreach ($this->breakHeadToHead($group, $matchups) as $group) {
          $newGroups[] = $group;
        }
      }
      else {
        $newGroups[] = $group;
      }
    }

    // Assign ranks by reassembling lists
    $records = array();
    $openIndex = 0; $lockedIndex = 0;
    $prevRank = null;
    while ($openIndex < count($newGroups) && $lockedIndex < count($locked_records)) {
      // Add the appropriate locked_records
      if (($prevRank === null && $locked_records[$lockedIndex]->team->dt_rank == $min_rank) ||
          ($prevRank !== null && $locked_records[$lockedIndex]->team->dt_rank <= $prevRank->rank + 1)) {

        $locked_records[$lockedIndex]->rank = $locked_records[$lockedIndex]->team->dt_rank;
        $records[] = $locked_records[$lockedIndex];
        $prevRank = $locked_records[$lockedIndex];
        $lockedIndex++;
        continue;
      }

      // Deal with "open" records
      if ($prevRank === null ||
          $prevRank->team->lock_rank !== null ||
          $prevRank->getRecord() != $newGroups[$openIndex][0]->getRecord()) {

        foreach ($newGroups[$openIndex] as $rank)
          $rank->rank = $min_rank + count($records);
      }
      else {
        foreach ($newGroups[$openIndex] as $rank)
          $rank->rank = $prevRank->rank;
      }

      foreach ($newGroups[$openIndex] as $rank)
        $records[] = $rank;
      $prevRank = $rank;
      $openIndex++;
    }
    // Add remaining ones
    while ($lockedIndex < count($locked_records)) {
      $locked_records[$lockedIndex]->rank = $locked_records[$lockedIndex]->team->dt_rank;
      $records[] = $locked_records[$lockedIndex];
      $lockedIndex++;
    }
    while ($openIndex < count($newGroups)) {
      foreach ($newGroups[$openIndex] as $rank)
	$rank->rank = $min_rank + count($records);
      foreach ($newGroups[$openIndex] as $rank)
        $records[] = $rank;
      $prevRank = $rank;
      $openIndex++;
    }
    return $records;
  }

  protected function breakHeadToHead(Array $ranks, Array &$matchups) {
    $matchesWon = array_fill(0, count($ranks), 0);
    for ($i = 0; $i < count($ranks) - 1; $i++) {
      for ($j = $i + 1; $j < count($ranks); $j++) {
        $a = $ranks[$i];
        $b = $ranks[$j];

        if (!isset($matchups[$a->team->id]) || !isset($matchups[$b->team->id]) ||
            !isset($matchups[$a->team->id][$b->team->id]))
          continue;

        foreach ($matchups[$a->team->id][$b->team->id] as $num => $a_finishes) {
          $aRaceTotal = 0;
          $bRaceTotal = 0;
          foreach ($a_finishes as $finish) {
            $aRaceTotal += $finish->score;
          }
          foreach ($matchups[$b->team->id][$a->team->id][$num] as $finish) {
            $bRaceTotal += $finish->score;
          }

          if ($aRaceTotal < $bRaceTotal)
            $matchesWon[$i]++;
          elseif ($aRaceTotal > $bRaceTotal)
            $matchesWon[$j]++;
        }
      }
    }

    array_multisort($matchesWon, SORT_NUMERIC | SORT_DESC, $ranks);

    $tiedGroups = array();
    foreach ($ranks as $i => $rank) {
      $rank->explanation = sprintf("Number of races won when tied teams met (%d)", $matchesWon[$i]);
      if (!isset($tiedGroups[$matchesWon[$i]]))
        $tiedGroups[$matchesWon[$i]] = array();
      $tiedGroups[$matchesWon[$i]][] = $rank;
    }

    $newGroups = array();
    foreach ($tiedGroups as $group) {
      if (count($group) > 1) {
        foreach ($this->breakByPoints($group, $matchups) as $group) {
          $newGroups[] = $group;
        }
      }
      else {
        $newGroups[] = $group;
      }
    }

    return $newGroups;
  }

  protected function breakByPoints(Array $ranks, Array &$matchups) {
    $pointsTotal = array_fill(0, count($ranks), 0);
    for ($i = 0; $i < count($ranks) - 1; $i++) {
      for ($j = $i + 1; $j < count($ranks); $j++) {
        $a = $ranks[$i];
        $b = $ranks[$j];

        if (!isset($matchups[$a->team->id]) || !isset($matchups[$b->team->id]) ||
            !isset($matchups[$a->team->id][$b->team->id]))
          continue;

        foreach ($matchups[$a->team->id][$b->team->id] as $num => $a_finishes) {
          foreach ($a_finishes as $finish) {
            $pointsTotal[$i] += $finish->score;
          }
          foreach ($matchups[$b->team->id][$a->team->id][$num] as $finish) {
            $pointsTotal[$j] += $finish->score;
          }
        }
      }
    }

    array_multisort($pointsTotal, SORT_NUMERIC, $ranks);

    $tiedGroups = array();
    foreach ($ranks as $i => $rank) {
      if ($pointsTotal[$i] > 0)
        $rank->explanation = sprintf("Total points scored when tied teams met (%d)", $pointsTotal[$i]);
      if (!isset($tiedGroups[$pointsTotal[$i]]))
        $tiedGroups[$pointsTotal[$i]] = array();
      $tiedGroups[$pointsTotal[$i]][] = $rank;
    }

    $newGroups = array();
    foreach ($tiedGroups as $group) {
      if (count($group) > 1) {
        usort($group, function(TeamRank $a, TeamRank $b) {
            return strcmp((string)$a->team, (string)$b->team);
          });
        foreach ($group as $rank)
          $rank->explanation = "Tie stands";
      }
      $newGroups[] = $group;
    }
    return $newGroups;
  }
}
?>