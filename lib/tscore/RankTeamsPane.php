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
 * 2013-01-10: Provide interface for ignoring races from record
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

  protected function fillTeam(Team $team) {
    require_once('regatta/Rank.php');

    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/team-rank.js')));
    $this->PAGE->addContent($p = new XPort("Race record for " . $team));
    $p->add($f = $this->createForm());

    $races = $this->REGATTA->getRacesForTeam(Division::A(), $team);
    $f->add(new XP(array(), "Use this pane to specify which races should be accounted for when creating the overall win-loss record for " . $team . ". Greyed-out races are currently being ignored."));

    $rows = array(); // the row WITHIN the table
    $records = array(); // the TeamRank object for the given round
    $recTDs = array(); // the record cell for each table
    foreach ($races as $race) {
      $finishes = $this->REGATTA->getFinishes($race);
      if (count($finishes) == 0)
	continue;

      if (!isset($rows[$race->round->id])) {
	$records[$race->round->id] = new TeamRank($team);
	$recTDs[$race->round->id] = new XTD(array('class'=>'rank-record'), "");
	$rows[$race->round->id] = new XTR(array(), array($recTDs[$race->round->id], new XTH(array(), $team)));

	$f->add(new XH3("Round: " . $race->round));
	$f->add(new XTable(array('class'=>'rank-table'), array($rows[$race->round->id])));
      }

      $row = $rows[$race->round->id];
      $record = $records[$race->round->id];

      $myScore = 0;
      $theirScore = 0;
      foreach ($finishes as $finish) {
	if ($finish->team->id == $team->id)
	  $myScore += $finish->score;
	else
	  $theirScore += $finish->score;
      }
      if ($myScore < $theirScore) {
	$className = 'rank-win';
	$display = 'W';
	if ($race->tr_ignore === null)
	  $record->wins++;
      }
      elseif ($myScore > $theirScore) {
	$className = 'rank-lose';
	$display = 'L';
	if ($race->tr_ignore === null)
	  $record->losses++;
      }
      else {
	$className = 'rank-tie';
	$display = 'T';
	if ($race->tr_ignore === null)
	  $record->ties++;
      }
      $other_team = $race->tr_team1;
      if ($other_team->id == $team->id)
	$other_team = $race->tr_team2;
      $id = sprintf('r-%s', $race->id);
      $row->add(new XTD(array(),
			array($chk = new XCheckboxInput('race[]', $race->id, array('id'=>$id, 'class'=>$className)),
			      $label = new XLabel($id, $display . " vs. "))));
      $label->add(new XBr());
      $label->add($other_team);
      $label->set('class', $className);
      if ($race->tr_ignore === null)
	$chk->set('checked', 'checked');
    }

    // update all recTDs
    foreach ($records as $id => $record)
      $recTDs[$id]->add($record->getRecord());

    $f->add($p = new XSubmitP('set-records', "Set race records"));
    $p->add(new XHiddenInput('team', $team->id));
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific team?
    // ------------------------------------------------------------
    if (isset($args['team'])) {
      if (($team = $this->REGATTA->getTeam($args['team'])) === null) {
	Session::pa(new PA("Invalid team chosen.", PA::E));
	$this->redirect('rank');
      }
      $this->fillTeam($team);
      return;
    }

    // ------------------------------------------------------------
    // All ranks
    // ------------------------------------------------------------
    $this->PAGE->addContent(new XP(array(), "Use this pane to set the rank for the teams in the regatta. By default, teams are ranked by the system according to win percentage, but tie breaks must be broken manually."));
    $this->PAGE->addContent(new XP(array(), "To edit a particular team's record by setting which races count towards their record, click on the win-loss record for that team. Remember to click \"Set ranks\" to save the order before editing a team's record."));

    $this->PAGE->addContent($f = $this->createForm());
    $f->add($tab = new XQuickTable(array('id'=>'rank-table'), array("#", "Explanation", "Record", "Team")));
    foreach ($this->REGATTA->getRanker()->rank($this->REGATTA) as $i => $rank) {
      $tab->addRow(array(new XTextInput('rank[]', ($i + 1), array('size'=>2)),
			 new XTextInput('explanation[]', $rank->explanation),
			 new XA($this->link('rank', array('team' => $rank->team->id)), $rank->getRecord()),
			 $rank->team));
    }
    $f->add(new XSubmitP('set-ranks', "Set ranks"));
  }

  public function process(Array $args) {
    if (isset($args['set-records'])) {
      $team = DB::$V->reqTeam($args, 'team', $this->REGATTA, "Invalid team whose records to set.");
      $ids = DB::$V->reqList($args, 'race', null, "No list of races provided.");

      $affected = 0;
      foreach ($this->REGATTA->getRacesForTeam(Division::A(), $team) as $race) {
	if (count($this->REGATTA->getFinishes($race)) == 0)
	  continue;

	$ignore = (in_array($race->id, $ids)) ? null : 1;
	if ($ignore != $race->tr_ignore) {
	  $race->tr_ignore = $ignore;
	  DB::set($race);
	  $affected++;
	}
      }

      // @TODO: update request
      if ($affected == 0)
	Session::PA(new PA("No races affected.", PA::I));
      else
	Session::pa(new PA(sprintf("Updated %d races.", $affected)));
    }
  }
}
?>