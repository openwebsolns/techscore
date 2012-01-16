<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Enter and drop team penalties
 *
 * @author Dayan Paez
 * @version 2010-01-25
 */
class TeamPenaltyPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Team penalty", $user, $reg);
  }

  protected function fillHTML(Array $args) {

    $divisions = array();
    foreach ($this->REGATTA->getDivisions() as $div)
      $divisions[(string)$div] = $div;
    $teams = array();
    foreach ($this->REGATTA->getTeams() as $team)
      $teams[$team->id] = $team;
    
    $this->PAGE->addContent($p = new XPort("Team penalties per division"));
    $p->add(new XP(array(),
		   array("These penalties will be added to the final " .
			 "team score after all race finishes have been " .
			 "totaled. The penalty is ",
			 new XStrong("+20 points per division"), ".")));

    if (count($teams) == 0) {
      $p->add(new XHeading("No teams have been registered."));
      return;
    }

    $p->add($form = $this->createForm());
    $form->add(new FItem("Team:", XSelect::fromArray('team', $teams)));
    $form->add($fi = new FItem("Division(s):", XSelectM::fromArray('division[]', $divisions)));
    $fi->add(new XMessage("Hold down Ctrl to select multiple"));

    // Penalty type
    $opts = array_merge(array(""=>""), TeamPenalty::getList());
    $form->add(new FItem("Penalty type:", XSelect::fromArray('penalty', $opts)));

    $form->add(new FItem("Comments:",
			 new XTextArea("comments", "",
				       array("rows"=>"2",
					     "cols"=>"15"))));

    $form->add(new XSubmitInput("t_submit", "Enter team penalty"));

    
    // ------------------------------------------------------------
    // Existing penalties
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Team penalties"));
    $penalties = $this->REGATTA->getTeamPenalties();

    if (count($penalties) == 0)
      $p->add(new XP(array(), "There are no team penalties."));
    else {
      $p->add($tab = new XQuickTable(array('class'=>'narrow'), array("Team name", "Division", "Penalty", "Comments", "Action")));
      foreach ($penalties as $p) {
	$tab->addRow(array($p->team,
			   $p->division,
			   $p->type,
			   new XTD(array('style'=>'text-align:left;width:10em;'), $p->comments),
			   $form = $this->createForm()));

	$form->add(new XP(array(),
			  array(new XHiddenInput("r_team", $p->team->id),
				new XHiddenInput("r_div",  $p->division),
				new XSubmitInput("t_remove", "Drop", array("class"=>"thin")))));
      }
    }
  }

  
  public function process(Array $args) {

    // ------------------------------------------------------------
    // Add penalty
    // ------------------------------------------------------------
    if (isset($args['t_submit'])) {
      $team = $this->REGATTA->getTeam($args['team']);
      // - validate team
      if ($team == null) {
	$mes = sprintf("Invalid or missing team (%s).", $args['team']);
	Session::pa(new PA($mes, PA::E));
	return $args;
      }

      // - validate penalty
      $pnty = $args['penalty'];
      if (!in_array($pnty, array_keys(TeamPenalty::getList()))) {
	$mes = sprintf("Invalid or missing penalty (%s).", $args['penalty']);
	Session::pa(new PA($mes, PA::E));
	return $args;
      }

      // - validate division
      $comm = trim($args['comments']);
      if (isset($args['division']) &&
	  is_array($args['division']) &&
	  count($args['division']) > 0) {

	$penalty = new TeamPenalty();
	$penalty->team = $team;
	$penalty->type = $pnty;
	$penalty->comments = $comm;
	
	$divisions = $this->REGATTA->getDivisions();
	foreach ($args['division'] as $div) {
	  if (in_array($div, $divisions)) {
	    $penalty->division = new Division($div);
	    $this->REGATTA->setTeamPenalty($penalty);
	  }
	}
	Session::pa(new PA("Added team penalty."));
	UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
      }
      else {
	$mes = "Invalid or missing division(s).";
	Session::pa(new PA($mes, PA::E));
      }
    }

    
    // ------------------------------------------------------------
    // Drop penalty
    // ------------------------------------------------------------
    if (isset($args['t_remove'])) {

      // - validate team
      $team = $this->REGATTA->getTeam($args['r_team']);

      // - validate division
      $divisions = $this->REGATTA->getDivisions();
      if ($team != null && in_array($args['r_div'], $divisions)) {

	$this->REGATTA->dropTeamPenalty($team, new Division($args['r_div']));

	$mes = sprintf("Dropped team penalty for %s in %s.", $team, $div);
	Session::pa(new PA($mes));
	UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
      }
      else {
	$mes = sprintf("Invalid or missing team (%s) or division (%s).",
		       $args['r_team'], $args['r_div']);
	Session::pa(new PA($mes, PA::E));
      }
    }

    return $args;
  }
}