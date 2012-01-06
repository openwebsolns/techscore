<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('conf.php');

/**
 * Pane to manually specify rotations
 *
 * @author Dayan Paez
 * @version 2010-01-20
 */
class ManualTweakPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Manual setup", $user, $reg);
  }

  protected function fillHTML(Array $args) {

    $rotation  = $this->REGATTA->getRotation();
    $exist_div = $rotation->getDivisions();

    // Chosen division
    $chosen_div = null;
    if (isset($args['division']) &&
	in_array($args['division'], $exist_div))
      $chosen_div = new Division($args['division']);
    else
      $chosen_div = $exist_div[0];

    // OUTPUT
    $this->PAGE->addContent($p = new XPort("Tweak current rotation"));
    $p->add($form = $this->createForm());

    $form->add(new FItem("Pick a division:", $f_sel = XSelect::fromArray('division',
									 array_combine($exist_div, $exist_div),
									 $chosen_div)));
    $f_sel->set("onchange", "submit()");
    $form->add(new XSubmitAccessible("boatupdate", "Update"));

    $p->add(new XHeading("Replace sail numbers"));
    $p->add($form = $this->createForm());
    
    $races = $this->REGATTA->getRaces($chosen_div);
    $row = array("Division $chosen_div");
    foreach ($races as $race)
      $row[] = $race->number;
    $form->add(new FItem("Edit on a boat-by-boat basis.", $tab = new XQuickTable(array('class'=>'narrow'), $row)));

    // Get teams
    $attrs = array("size"=>"3", "maxlength"=>"3", "class"=>"small");
    foreach($this->REGATTA->getTeams() as $team) {
      $row = array($team);
      foreach ($races as $race) {
	$sail = $rotation->getSail($race, $team);
	$row[] = new XTextInput(sprintf("%s,%s", $race->id, $team->id), ($sail !== null) ? $sail : "", $attrs));
    }
    $tab->addRow($row);
  }
  $form->add(new XReset("reset", "Reset"));
  $form->add(new XSubmitInput("editboat", "Edit boat(s)"));
}

public function process(Array $args) {

  $rotation = $this->REGATTA->getRotation();

  // ------------------------------------------------------------
  // Edit division
  // ------------------------------------------------------------
  if (isset($args['division'])) {
    if (!in_array($args['division'], $rotation->getDivisions())) {
      $mes = sprintf("Invalid division (%s).", $args['division']);
      Session::pa(new PA($mes, PA::E));
      unset($args['division']);
    }
    return $args;
  }

  // ------------------------------------------------------------
  // Boat by boat
  // ------------------------------------------------------------
  $races = $this->REGATTA->getRaces();
  $teams = $this->REGATTA->getTeams();
    
  if (isset($args['editboat'])) {
    unset($args['editboat']);
    $sail = new Sail();
    foreach ($args as $rAndt => $value) {
      if ( !empty($value) && is_numeric($value) ) {
	$rAndt = explode(",", $rAndt);
	$r     = Preferences::getObjectWithProperty($races, "id", $rAndt[0]);
	$t     = Preferences::getObjectWithProperty($teams, "id", $rAndt[1]);
	if ($r != null && $t != null) {
	  $sail->race = $r;
	  $sail->team = $t;
	  $sail->sail = $value;
	  $rotation->setSail($sail);
	}
      }
    }
    UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
    Session::pa(new PA('Sails updated.'));
  }

  return $args;
}
}
?>