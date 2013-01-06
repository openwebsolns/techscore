<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Provide visual organization of teams to rank, applicable to team
 * racing only.
 *
 * @author Dayan Paez
 * @version 2013-01-05
 */
class RankTeamsPane extends AbstractPane {
  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Rank teams", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new SoterException("Pane only available for team racing regattas.");
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent(new XP(array(), "Use this pane to specify which races should be accounted for when creating the overall win-loss record for each team."));

    $tables = array();
    $records = array();
    foreach ($this->REGATTA->getTeams() as $team) {
      $tables[] = new XTable(array('class'=>'rank-table'),
			     array($row = new XTR(array(),
						  array($recTD = new XTD(array('class'=>'rank-record'), ""), new XTH(array(), $team)))));
      $wins = 0;
      $loss = 0;
      $ties = 0;
      foreach ($this->REGATTA->getRacesForTeam(Division::A(), $team) as $race) {
	$finishes = $this->REGATTA->getFinishes($race);
	if (count($finishes) > 0) {
	  $myScore = 0;
	  $theirScore = 0;
	  foreach ($finishes as $finish) {
	    if ($finish->team->id == $team->id)
	      $myScore += $finish->score;
	    else
	      $theirScore += $finish->score;
	  }
	  if ($myScore < $theirScore) {
	    $className = 'tr-win';
	    $display = 'W';
	    $wins++;
	  }
	  elseif ($myScore > $theirScore) {
	    $className = 'tr-lose';
	    $display = 'L';
	    $loss++;
	  }
	  else {
	    $className = 'tr-tie';
	    $display = 'T';
	    $ties++;
	  }
	  $other_team = $race->tr_team1;
	  if ($other_team->id == $team->id)
	    $other_team = $race->tr_team2;
	  $id = sprintf('r-%s-%s', $race->id, $team->id);
	  $row->add(new XTD(array('class'=>$className),
			    array(new XCheckboxInput('race[]', $race->id, array('checked'=>'checked', 'id'=>$id)),
				  $label = new XLabel($id, $display . " vs. "))));
	  $label->add(new XBr());
	  $label->add($other_team);
	}
      }
      $cont = sprintf("%s-%s", $wins, $loss);
      if ($ties > 0)
	$cont .= '-' . $ties;
      $recTD->add($cont);
      $record = $wins;
      if ($loss > 0)
	$record = $record / $loss;
      $records[] = $record;
    }

    array_multisort($records, SORT_NUMERIC | SORT_DESC, $tables);
    foreach ($tables as $table)
      $this->PAGE->addContent($table);
  }

  public function process(Array $args) {
    throw new SoterException("No POST processed.");
  }
}
?>