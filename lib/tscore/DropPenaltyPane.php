<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Drop individual penalties
 *
 * @author Dayan Paez
 * @version 2010-01-25
 */
class DropPenaltyPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Drop penalty", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $penalties = array();
    $handicaps = array();
    foreach ($this->REGATTA->getPenalizedFinishes() as $finish) {
      if ($finish->penalty instanceof Penalty)
	$penalties[] = $finish;
      elseif ($finish->penalty instanceof Breakdown)
	$handicaps[] = $finish;
    }

    // ------------------------------------------------------------
    // Existing penalties
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Penalties"));
    
    if (count($penalties) == 0) {
      $p->add(new XP(array(), "There are currently no penalties."));
    }
    else {
      $p->add($tab = new XQuickTable(array('class'=>'narrow'), array("Race", "Team", "Penalty", "Action")));
      foreach ($penalties as $finish) {
	$tab->addRow(array($finish->race,
			   $finish->team,
			   $finish->penalty->type,
			   $form = $this->createForm()));

	$form->add(new XHiddenInput("r_finish", $finish->id));
	$form->add($sub = new XSubmitInput("p_remove", "Drop/Reinstate", array("class"=>"thin")));
      }
    }

    // ------------------------------------------------------------
    // Existing breakdowns
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Breakdowns"));
    
    if (count($handicaps) == 0) {
      $p->add(new XP(array(), "There are currently no breakdowns."));
    }
    else {
      $p->add($tab = new XQuickTable(array('class'=>'narrow'), array("Race", "Team", "Breakdown", "Action")));
      foreach ($handicaps as $finish) {
	$tab->addRow(array($finish->race,
			   $finish->team,
			   $finish->penalty->type,
			   $form = $this->createForm()));

	$form->add(new XHiddenInput("r_finish", $finish->id));
	$form->add($sub = new XSubmitInput("p_remove", "Drop/Reinstate",
					   array("class"=>"thin")));
      }
    }
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Drop penalty/breakdown
    // ------------------------------------------------------------
    if (isset($args['p_remove'])) {

      // - validate finish id
      $finishes = $this->REGATTA->getPenalizedFinishes();
      $theFinish = Preferences::getObjectWithProperty($finishes,
						      "id",
						      $args['r_finish']);
      if ($theFinish == null) {
	$mes = sprintf("Invalid or missing finish ID (%s).", $args['r_finish']);
	Session::pa(new PA($mes, PA::E));
	return $args;
      }
      $theFinish->penalty = null;
      $this->REGATTA->commitFinishes(array($theFinish));
      $this->REGATTA->runScore($theFinish->race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);

      // Announce
      $mes = sprintf("Dropped penalty for %s in race %s.",
		     $theFinish->team, $theFinish->race);
      Session::pa(new PA($mes));
    }
    return $args;
  }
}