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
  public function rank(FullRegatta $reg, $races = null) {
    $divisions = $reg->getDivisions();
    if ($races === null)
      $races = $reg->getScoredRaces(Division::A());

    // Determine records for each team in given races
    $records = array();
    foreach ($races as $race) {
      // initialize TeamRank objects
      if (!isset($records[$race->tr_team1->id]))
        $records[$race->tr_team1->id] = new TeamRank($race->tr_team1);
      if (!isset($records[$race->tr_team2->id]))
        $records[$race->tr_team2->id] = new TeamRank($race->tr_team2);

      $a_finishes = $reg->getFinishes($race);
      if (count($a_finishes) > 0) {
        $finishes = array();
        foreach ($a_finishes as $finish)
          $finishes[] = $finish;
        for ($i = 1; $i < count($divisions); $i++) {
          foreach ($reg->getFinishes($reg->getRace($divisions[$i], $race->number)) as $finish)
            $finishes[] = $finish;
        }

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
    }

    $min_rank = 1;
    $all_ranks = array();
    foreach ($reg->getTeamsInRankGroups() as $group) {
      $group = $this->rankGroup($reg, $races, $group, $records, $min_rank);
      foreach ($group as $rank)
        $all_ranks[] = $rank;
      $min_rank += count($group);
    }
    return $all_ranks;
  }

  /**
   * Ranks the team according to their winning percentages.
   *
   * Respect the locked ranks, and also the groupings
   */
  public function rankGroup(FullRegatta $reg, $races, $teams, &$ranks, $min_rank) {
    $max_rank = $min_rank + count($teams) - 1;

    // separate teams into "locked" and "open" groups
    $locked_records = array();
    $open_records = array();
    foreach ($teams as $team) {
      $rank = (isset($ranks[$team->id])) ? $ranks[$team->id] : new TeamRank($team);
      if ($team->lock_rank !== null && $team->dt_rank !== null) {
        if ($team->dt_rank < $min_rank || $team->dt_rank > $max_rank)
          throw new InvalidArgumentException(sprintf("Locked rank of %d for %s outside the range of group %d-%d.", $team, $team->dt_rank, $min_rank, $max_rank));
        $locked_records[] = $rank;
      }
      else
        $open_records[] = $rank;
    }

    $open_records = $this->order($open_records);
    usort($locked_records, function(TeamRank $r1, TeamRank $r2) {
        if ($r1->team->dt_rank < $r2->team->dt_rank)
          return -1;
        if ($r1->team->dt_rank > $r2->team->dt_rank)
          return 1;
        return strcmp((string)$r1->team, (string)$r2->team);
      });

    // Assign ranks by reassembling lists
    $records = array();
    $openIndex = 0; $lockedIndex = 0;
    $prevRank = null;
    while ($openIndex < count($open_records) && $lockedIndex < count($locked_records)) {
      if (($prevRank === null && $locked_records[$lockedIndex]->team->dt_rank == $min_rank) ||
          ($prevRank !== null && $locked_records[$lockedIndex]->team->dt_rank <= $prevRank->rank + 1)) {

        $locked_records[$lockedIndex]->rank = $locked_records[$lockedIndex]->team->dt_rank;
        $records[] = $locked_records[$lockedIndex];
        $prevRank = $locked_records[$lockedIndex];
        $lockedIndex++;
        continue;
      }
      if ($prevRank === null ||
          $prevRank->team->lock_rank !== null ||
          $this->compare($prevRank, $open_records[$openIndex]) != 0) {
        $open_records[$openIndex]->rank = $min_rank + count($records);
      }
      else {
        $open_records[$openIndex]->rank = $prevRank->rank;
      }
      $records[] = $open_records[$openIndex];
      $prevRank = $open_records[$openIndex];
      $openIndex++;
    }
    // Add remaining ones
    while ($lockedIndex < count($locked_records)) {
      $locked_records[$lockedIndex]->rank = $locked_records[$lockedIndex]->team->dt_rank;
      $records[] = $locked_records[$lockedIndex];
      $lockedIndex++;
    }
    while ($openIndex < count($open_records)) {
      if ($prevRank === null || $this->compare($prevRank, $open_records[$openIndex]) != 0) {
        $open_records[$openIndex]->rank = $min_rank + count($records);
      }
      else {
        $open_records[$openIndex]->rank = $prevRank->rank;
      }
      $records[] = $open_records[$openIndex];
      $prevRank = $open_records[$openIndex];
      $openIndex++;
    }
    return $records;
  }

  /**
   * Merge-sort implementation
   */
  private function order(Array $teams, $lower = 0, $upper = null) {
    if ($upper === null)
      $upper = count($teams);
    if (($upper - $lower) < 2)
      return array($teams[$lower]);
    $mid = floor(($upper + $lower) / 2);

    $left = $this->order($teams, $lower, $mid);
    $right = $this->order($teams, $mid, $upper);

    $union = array();
    $l = 0; $r = 0;

    $nextRank = null;
    while ($l < count($left) && $r < count($right)) {
      $res = $this->compare($left[$l], $right[$r]);
      if ($res < 0) {
	$nextRank = $left[$l];
	$l++;
      }
      elseif ($res > 0) {
	$nextRank = $right[$r];
	$r++;
      }
      else {
	$res = strcmp((string)$left[$l]->team, (string)$right[$r]->team);
	if ($res <= 0) {
	  $nextRank = $left[$l];
	  $l++;
	}
	else {
	  $nextRank = $right[$r];
	  $r++;
	}
      }
      $union[] = $nextRank;
    }
    // add the remainder of each list
    while ($l < count($left)) {
      $union[] = $left[$l];
      $l++;
    }
    while ($r < count($right)) {
      $union[] = $right[$r];
      $r++;
    }
    return $union;
  }

  /**
   * Compares first record with second.
   *
   * Comparison is done first by win percentage, then by total number
   * of wins, then by fewest number of losses.
   *
   * @param TeamRank $a the first team
   * @param TeamRank $b the second team
   * @return int < 0 if first team ranks higher, > 0 otherwise
   */
  public function compare(TeamRank $a, TeamRank $b) {
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
  }
}
?>