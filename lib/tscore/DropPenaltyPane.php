<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Drop individual penalties
 *
 * @author Dayan Paez
 * @created 2010-01-25
 */
class DropPenaltyPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Current penalties and breakdowns", $user, $reg);
    $this->title = "Drop penalty";
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
    $this->PAGE->addContent($p = new Portlet("Penalties"));
    
    if (count($penalties) == 0) {
      $p->addChild(new Para("There are currently no penalties."));
    }
    else {
      $p->addChild($tab = new Table());
      $tab->addAttr("class", "narrow");

      $tab->addHeader(new Row(array(Cell::th("Race"),
				    Cell::th("Team"),
				    Cell::th("Penalty"),
				    Cell::th("Action"))));

      foreach ($penalties as $finish) {
	$tab->addRow(new Row(array(new Cell($finish->race),
				   new Cell($finish->team,
					    array("class"=>"strong")),
				   new Cell($finish->penalty->type),
				   new Cell($form = $this->createForm()))));

	$form->addChild(new FHidden("r_finish", $finish->id));
	$form->addChild($sub = new FSubmit("p_remove", "Drop/Reinstate",
					   array("class"=>"thin")));
      }
    }

    // ------------------------------------------------------------
    // Existing breakdowns
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new Portlet("Breakdowns"));
    
    if (count($handicaps) == 0) {
      $p->addChild(new Para("There are currently no breakdowns."));
    }
    else {
      $p->addChild($tab = new Table());
      $tab->addAttr("class", "narrow");

      $tab->addHeader(new Row(array(Cell::th("Race"),
				    Cell::th("Team"),
				    Cell::th("Breakdown"),
				    Cell::th("Action"))));

      foreach ($handicaps as $finish) {
	$tab->addRow(new Row(array(new Cell($finish->race),
				   new Cell($finish->team,
					    array("class"=>"strong")),
				   new Cell($finish->penalty->type),
				   new Cell($form = $this->createForm()))));

	$form->addChild(new FHidden("r_finish", $finish->id));
	$form->addChild($sub = new FSubmit("p_remove", "Drop/Reinstate",
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
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }
      $theFinish->penalty = null;
      $this->REGATTA->runScore($theFinish->race);
      $this->REGATTA->setFinishes($theFinish->race);

      // Announce
      $mes = sprintf("Dropped penalty for %s in race %s.",
		     $theFinish->team, $theFinish->race);
      $this->announce(new Announcement($mes));
    }
    return $args;
  }

  public function isActive() {
    return $this->REGATTA->hasFinishes();
  }
}