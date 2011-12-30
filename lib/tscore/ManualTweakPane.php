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
    $this->PAGE->addContent($p = new Port("Tweak current rotation"));
    $p->addChild($form = $this->createForm());

    $form->addChild(new FItem("Pick a division:",
			      $f_sel = new FSelect("division", array($chosen_div))));
    $f_sel->addOptions(array_combine($exist_div, $exist_div));
    $f_sel->addAttr("onchange", "submit()");
    $form->addChild(new FSubmitAccessible("boatupdate", "Update"));

    $p->addChild(new XHeading("Replace sail numbers"));
    $p->addChild($form = $this->createForm());
    
    $races = $this->REGATTA->getRaces($chosen_div);
    $form->addChild(new FItem("Edit on a boat-by-boat basis.",
			      $tab = new Table()));
    $tab->addAttr("class", "narrow");
    $row = array(Cell::th("Division " . $chosen_div));
    foreach ($races as $race)
      $row[] = Cell::th($race->number);
    $tab->addHeader(new Row($row));

    // Get teams
    foreach($this->REGATTA->getTeams() as $team) {
      $row = array(Cell::th($team));
      foreach ($races as $race) {
	$sail = $rotation->getSail($race, $team);
	$row[] = new Cell(new FText(sprintf("%s,%s", $race->id, $team->id),
				    ($sail !== null) ? $sail : "",
				    array("size"=>"3", "maxlength"=>"3", "class"=>"small")));
      }
      $tab->addRow(new Row($row));
    }
    $form->addChild(new FReset("reset", "Reset"));
    $form->addChild(new FSubmit("editboat", "Edit boat(s)"));
  }

  public function process(Array $args) {

    $rotation = $this->REGATTA->getRotation();

    // ------------------------------------------------------------
    // Edit division
    // ------------------------------------------------------------
    if (isset($args['division'])) {
      if (!in_array($args['division'], $rotation->getDivisions())) {
	$mes = sprintf("Invalid division (%s).", $args['division']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
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
      $this->announce(new Announcement('Sails updated.'));
    }

    return $args;
  }
}
?>