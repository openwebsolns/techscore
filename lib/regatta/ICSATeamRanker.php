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
	if ($race->tr_ignore === null) {
	  if ($myScore < $theirScore) {
	    $records[$race->tr_team1->id]->wins++;
	    $records[$race->tr_team2->id]->losses++;
	  }
	  elseif ($myScore > $theirScore) {
	    $records[$race->tr_team2->id]->wins++;
	    $records[$race->tr_team1->id]->losses++;
	  }
	  else {
	    $records[$race->tr_team1->id]->ties++;
	    $records[$race->tr_team2->id]->ties++;
	  }
	}
      }
    }

    // Add other teams not in list of races
    foreach ($reg->getTeams() as $team) {
      if (!isset($records[$team->id]))
	$records[$team->id] = new TeamRank($team);
    }
    usort($records, array($this, 'compare'));
    return $records;
  }

  /**
   * Compares first record with second.
   *
   * Comparison is done first by win percentage, then by total number
   * of wins, then by fewest number of losses. Finally, teams are
   * ranked "alphabetically".
   *
   * @param TeamRank $a the first team
   * @param TeamRank $b the second team
   * @return int < 0 if first team ranks higher, > 0 otherwise
   */
  public function compare(TeamRank $a, TeamRank $b) {
    if ($a->team->dt_rank !== null) {
      if ($b->team->dt_rank === null)
        return -1;
      return $a->team->dt_rank - $b->team->dt_rank;        
    }
    elseif ($b->team->dt_rank !== null)
      return 1;

    $perA = $a->getWinPercentage();
    $perB = $b->getWinPercentage();
    if ($perA == $perB) {
      if ($a->wins == $b->wins) {
	if ($a->losses == $b->losses) {
	  $a->explanation = "Alphabetical";
	  $b->explanation = "Alphabetical";
	  return strcmp((string)$a->team, (string)$b->team);
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