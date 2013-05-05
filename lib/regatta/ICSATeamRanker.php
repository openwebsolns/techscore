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
   */
  public function rank(FullRegatta $reg, $races = null) {
    if ($races === null)
      $races = $reg->getScoredRaces(Division::A());

    $divisions = $reg->getDivisions();
    $records = array();
    foreach ($races as $race) {
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

    // Add other teams not in list of races
    foreach ($reg->getTeams() as $team) {
      if (!isset($records[$team->id]))
	$records[$team->id] = new TeamRank($team);
    }
    return $this->order(array_values($records));
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
    $prevRank = null;
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
      if ($prevRank == null || $this->compare($prevRank, $nextRank) != 0)
	$nextRank->rank = count($union) + 1;
      else
	$nextRank->rank = $prevRank->rank;

      $union[] = $nextRank;
      $prevRank = $nextRank;
    }
    // add the remainder of each list
    while ($l < count($left)) {
      $union[] = $left[$l];
      $left[$l]->rank = count($union);
      $l++;
    }
    while ($r < count($right)) {
      $union[] = $right[$r];
      $right[$r]->rank = count($union);
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
    /*
    if ($a->team->dt_rank !== null) {
      if ($b->team->dt_rank === null)
        return -1;
      return $a->team->dt_rank - $b->team->dt_rank;        
    }
    elseif ($b->team->dt_rank !== null)
      return 1;
    */

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