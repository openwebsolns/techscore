<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package regatta
 */

require_once('regatta/ICSARanker.php');
require_once('regatta/Rank.php');

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
   * Respect the locked ranks
   */
  public function rank(FullRegatta $reg, $races = null) {
    if ($races === null)
      $races = $reg->getScoredRaces(Division::A());

    $divisions = $reg->getDivisions();
    // keep separate maps for locked ranks
    $locked_records = array();
    $open_records = array();
    foreach ($races as $race) {
      // initialize TeamRank objects
      $myList =& $open_records;
      $theirList =& $open_records;
      if ($race->tr_team1->lock_rank !== null)
        $myList =& $locked_records;
      if ($race->tr_team2->lock_rank !== null)
        $theirList =& $locked_records;

      if (!isset($myList[$race->tr_team1->id]))
	$myList[$race->tr_team1->id] = new TeamRank($race->tr_team1);
      if (!isset($theirList[$race->tr_team2->id]))
	$theirList[$race->tr_team2->id] = new TeamRank($race->tr_team2);

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
	    $myList[$race->tr_team1->id]->wins++;
	  elseif ($myScore > $theirScore)
	    $myList[$race->tr_team1->id]->losses++;
	  else
	    $myList[$race->tr_team1->id]->ties++;
	}
	if ($race->tr_ignore2 === null) {
	  if ($myScore < $theirScore)
	    $theirList[$race->tr_team2->id]->losses++;
	  elseif ($myScore > $theirScore)
	    $theirList[$race->tr_team2->id]->wins++;
	  else
	    $theirList[$race->tr_team2->id]->ties++;
	}
      }
    }

    // Add other teams not in list of races
    foreach ($reg->getTeams() as $team) {
      $list =& $open_records;
      if ($team->lock_rank !== null)
        $list =& $locked_records;
      if (!isset($list[$team->id]))
        $list[$team->id] = new TeamRank($team);
    }

    $open_records = $this->order(array_values($open_records));
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
      if (($prevRank === null && $locked_records[$lockedIndex]->team->dt_rank == 1) ||
          ($prevRank !== null && $locked_records[$lockedIndex]->team->dt_rank <= $prevRank->rank + 1)) {

        $locked_records[$lockedIndex]->rank = $locked_records[$lockedIndex]->team->dt_rank;
        $records[] = $locked_records[$lockedIndex];
        $prevRank = $locked_records[$lockedIndex];
        $lockedIndex++;
        continue;
      }
      if ($prevRank === null || $this->compare($prevRank, $open_records[$openIndex]) != 0) {
        $open_records[$openIndex]->rank = count($records) + 1;
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
        $open_records[$openIndex]->rank = count($records) + 1;
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