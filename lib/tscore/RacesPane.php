<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @created 2009-10-04
 * @package tscore
 */

require_once('conf.php');

/**
 * Page for editing races in a regatta and their boats.
 *
 */
class RacesPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Edit Races", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $divisions = $this->REGATTA->getDivisions();
    $boats     = Preferences::getBoats();
    $boatOptions = array();
    foreach ($boats as $boat) {
      $boatOptions[$boat->id] = $boat->name;
    }

    //------------------------------------------------------------
    // Add races
    
    if (!$this->REGATTA->get(Regatta::FINALIZED)) {
      $this->PAGE->addContent($p = new Port("Races and divisions"));
      $p->addChild($form = $this->createForm());
      $form->addChild(new FItem("Divisions:",
				$f_sel = new FSelect("division")));
      $f_sel->addOptions(array("A"=>"A",
			       "B"=>"B",
			       "C"=>"C",
			       "D"=>"D"));

      $form->addChild(new FItem("Amount:",
				new FText("races", 
					  "18",
					  array("size"=>"3"))));

      $form->addChild(new FItem("Boat:",
				$f_sel = new FSelect("boat",
						     array())));
      $f_sel->addOptions($boatOptions);

      $form->addChild(new FSubmit("addraces", "Add races"));
    }

    //------------------------------------------------------------
    // Delete empty divisions
    if (!$this->REGATTA->get(Regatta::FINALIZED)) {

      $p->addChild(new Heading("Remove a division"));

      if (count($divisions) == 0)
	$p->addChild(new Para("There are no divisions to drop."));
      foreach ($divisions as $div) {
	if (count($this->REGATTA->getScoredRaces($div)) == 0) {
	  $p->addChild($form = $this->createForm());
	  $form->addChild(new FHidden("division", $div));
	  $form->addChild(new FItem(new FSubmit("delete_div", "Drop", array("class"=>"thin")),
				    new FSpan("Division " . $div)));

	}
	else {
	  $p->addChild(new Para("Cannot drop Division $div because at least " .
				"one of its races has been scored."));
	}
      }
    }

    //------------------------------------------------------------
    // Edit existing races
    
    $p = new Port("Current races");
    $p->addChild(new Para("Here you can edit the boat associated with each race. " .
			  "Races that are not sailed are automatically removed " .
			  "when finalizing the regatta."));
    $p->addChild($form = $this->createForm());

    // Table of races: columns are divisions; rows are race numbers
    $form->addChild($tab = new Table());
    $tab->addAttr("class", "narrow");
    $head = array(Cell::th("#"));
    $races = array();
    $max   = 0; // the maximum number of races in any given division
    foreach ($divisions as $div) {
      $head[] = Cell::th("Division " . $div);
      $races[(string)$div] = $this->REGATTA->getRaces($div);
      $max = max($max, count($races[(string)$div]));
    }
    $tab->addHeader(new Row($head));

    //  - Table content
    for ($i = 0; $i < $max; $i++) {
      // Add row
      $row = array(Cell::th($i + 1));

      // For each division
      foreach ($divisions as $div) {
	$c = new Cell();

	if (isset($races[(string)$div][$i])) {
	  $race = $races[(string)$div][$i];
	  $c->addChild($f_sel = new FSelect($race, array($race->boat->id)));
	  $f_sel->addOptions($boatOptions);
	}

	$row[] = $c;
      }
      $tab->addRow(new Row($row));
    }

    // Add input elements
    $form->addChild(new FReset("", "Reset boats"));
    $form->addChild(new FSubmit("editboats", "Edit boats"));

    if (count($this->REGATTA->getRaces()) > 0) {
      $this->PAGE->addContent($p);
    }
  }

  /**
   * Process edits to races for the regatta
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Add races
    if (isset($args['addraces'])) {

      // Validate divisions
      if (isset($args['division']) &&
	  in_array($args['division'], array("A", "B", "C", "D"))) {
	$division = $args['division'];
      }
      else {
	$this->announce(new Announcement('Missing or invalid division.'), Announcement::ERROR);
	return;
      }
      
      // Validate amount
      if (isset($args['races']) &&
	  is_numeric($args['races']) &&
	  $args['races'] > 0)
	$amount = $args['races'];
      else {
	$mes = sprintf('Invalid number of races (%s)', $args['races']);
	$this->announce(new Announcement($mes), Announcement::ERROR);
	return;
      }

      // Validate boat
      if (!isset($args['boat']) ||
	  !($boat = Preferences::getObjectWithProperty(Preferences::getBoats(),
						       "id",
						       $args['boat']))) {
	$mes = 'Missing boat type.';
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return;
      }

      // Insert races
      for ($i = 0; $i < $amount; $i++) {
	$race = new Race();
	$race->division = $division;
	$race->boat = $boat;
	$this->REGATTA->addRace($race);
      }
      $this->announce(new Announcement("Added $amount races for division $division."));
    }

    
    // ------------------------------------------------------------
    // Drop division

    $divisions = $this->REGATTA->getDivisions();
    if (isset($args['delete_div']) &&
	isset($args['division']) &&
	in_array($args['division'], $divisions)) {
      $div = new Division($args['division']);
      $this->REGATTA->removeDivision($div);
      $this->announce(new Announcement(sprintf("Removed division %s.", $div)));
    }

    
    // ------------------------------------------------------------
    // Update races

    if (isset($args['editboats'])) {
      unset($args['editboats']);
      
      // Let the database decide whether the values are valid to begin
      // with. Just keep track of whether there were errors.
      $errors = false;
      foreach ($args as $key => $value) {
	try {
	  $race = Race::parse($key);
	  $race = $this->REGATTA->getRace($race->division, $race->number);
	  $boat = Preferences::getObjectWithProperty(Preferences::getBoats(),
						     "id", $value);
	  $race->boat = $boat;
	}
	catch (Exception $e) {
	  $errors = true;
	}
      }

      if ($errors) {
	$mes = "Not all races updated.";
	$this->announce(new Announcement($mes, Announcement::WARNING));
      }
      else
	$this->announce(new Announcement("Updated races."));
    }
  }

  public function isActive() { return true; }
}
?>