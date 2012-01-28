<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('conf.php');

/**
 * Page for editing races in a regatta and their boats.
 *
 * @version 2010-07-31: Starting with this version, only the number of
 * races and division is chosen, with appropriate warning messages if
 * there are already rotations or finishes in place.
 *
 * @TODO For the boats in the race, provide a mechanism similar to rotation
 * creation, where the user can choose a range of races in each
 * division to assign a boat at a time. Provide an extra box for every
 * new division.
 */
class RacesPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Edit Races", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $divisions = $this->REGATTA->getDivisions();
    $boats     = DB::getBoats();
    $boatOptions = array('' => "[Use table]");
    foreach ($boats as $boat) {
      $boatOptions[$boat->id] = $boat->name;
    }

    //------------------------------------------------------------
    // Number of races and divisions
    // ------------------------------------------------------------
    $final = $this->REGATTA->finalized;
    $this->PAGE->addContent($p = new XPort("Races and divisions"));
    $p->add($form = $this->createForm());
    $form->add(new FItem("Number of divisions:", $f_div = new XSelect('num_divisions')));
    $form->add(new FItem("Number of races:",
			 $f_rac = new XTextInput("num_races",
						 count($this->REGATTA->getRaces(Division::A())))));
    if ($final) {
      $f_div->set("disabled", "disabled");
      $f_rac->set("disabled", "disabled");
    }
    elseif (count($this->REGATTA->getRotation()->getRaces()) > 0 ||
	    count($this->REGATTA->getScoredRaces()) > 0) {
      $form->add(new XP(array(),
			array(new XStrong("Warning:"),
			      " Adding races or divisions to this regatta will require that you also edit the rotations (if any). Removing races or divisions will also remove the finishes and rotations (if any) for the removed races!")));
    }
    $form->add(new XP(array(),
		      array(new XStrong("Note:"),
			    " Extra races are automatically removed when the regatta is finalized.")));
    $attrs = ($final) ? array('disabled'=>'disabled') : array();
    $form->add(new XSubmitP("set-races", "Set races", $attrs));

    // Fill the select boxes
    for ($i = 1; $i <= 4; $i++)
      $f_div->add(new FOption($i, $i));

    //------------------------------------------------------------
    // Edit existing boats
    
    $this->PAGE->addContent($p = new XPort("Boat assignments"));
    $p->add(new XP(array(), "Edit the boat associated with each race. This is necessary at the time of entering RP information. Races that are not sailed are automatically removed when finalizing the regatta."));
    $p->add($form = $this->createForm());

    // Add input elements
    $form->add(new XSubmitP("editboats", "Edit boats"));

    // Table of races: columns are divisions; rows are race numbers
    $head = array("#");
    $races = array();
    $max   = 0; // the maximum number of races in any given division
    foreach ($divisions as $div) {
      $head[] = "Division $div";
      $races[(string)$div] = $this->REGATTA->getRaces($div);
      $max = max($max, count($races[(string)$div]));
    }
    $form->add($tab = new XQuickTable(array('class'=>'narrow'), $head));

    //  - Global boat
    $row = array("All");
    foreach ($divisions as $div) {
      $c = new XTD();
      $c->add(XSelect::fromArray($div, $boatOptions));
      $row[] = $c;
    }
    $tab->addRow($row);

    //  - Table content
    array_shift($boatOptions);
    for ($i = 0; $i < $max; $i++) {
      // Add row
      $row = array(new XTH(array(), $i + 1));

      // For each division
      foreach ($divisions as $div) {
	$c = "";
	if (isset($races[(string)$div][$i])) {
	  $race = $races[(string)$div][$i];
	  $c = XSelect::fromArray($race, $boatOptions, $race->boat->id);
	}
	$row[] = $c;
      }
      $tab->addRow($row);
    }
  }

  /**
   * Process edits to races for the regatta
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Set races
    // ------------------------------------------------------------
    if (isset($args['set-races']) && !$this->REGATTA->finalized) {
      // ------------------------------------------------------------
      // Add new divisions
      //   1. Get host's preferred boat
      $hosts = $this->REGATTA->getHosts();
      $host = $hosts[0];
      $boat = DB::getPreferredBoat($host);

      $cur_divisions = $this->REGATTA->getDivisions();
      $cur_races     = count($this->REGATTA->getRaces(Division::A()));
      if (isset($args['num_divisions'])) {
	$pos_divisions = Division::getAssoc();
	$num_divisions = (int)$args['num_divisions'];
	if ($num_divisions < 1 || $num_divisions > count($pos_divisions)) {
	  Session::pa(new PA("Invalid number of divisions.", PA::E));
	  return $args;
	}
	$pos_divisions_list = array_values($pos_divisions);
	for ($i = count($cur_divisions); $i < $num_divisions; $i++) {
	  $div = $pos_divisions_list[$i];
	  for ($j = 0; $j < $cur_races; $j++) {
	    $race = new Race();
	    $race->division = $div;
	    $race->boat = $boat;
	    $race->number = ($j + 1);
	    $this->REGATTA->setRace($race);
	  }
	}

	// ------------------------------------------------------------
	// Subtract extra divisions
	for ($i = count($cur_divisions); $i > $num_divisions; $i--) {
	  $this->REGATTA->removeDivision($pos_divisions_list[$i - 1]);
	}
      }

      if (isset($args['num_races'])) {
	$num_races = (int)$args['num_races'];
	if ($num_races < 1 || $num_races > 99) {
	  Session::pa(new PA("Invalid number of races.", PA::E));
	  return $args;
	}
	// Add
	for ($i = $cur_races; $i < $num_races; $i++) {
	  foreach ($cur_divisions as $div) {
	    $race = new Race();
	    $race->division = $div;
	    $race->boat = $boat;
	    $race->number = ($i + 1);
	    $this->REGATTA->setRace($race);
	  }
	}

	// Remove (from the end, of course!)
	for ($i = $cur_races; $i > $num_races; $i--) {
	  foreach ($cur_divisions as $div) {
	    $race = new Race();
	    $race->division = $div;
	    $race->number = $i;
	    $this->REGATTA->removeRace($race);
	  }
	}
      }

      Session::pa(new PA("Set number of races."));
    }

    // ------------------------------------------------------------
    // Update boats

    $remaining_divisions = $this->REGATTA->getDivisions();
    $copy = $remaining_divisions;
    if (isset($args['editboats'])) {
      unset($args['editboats']);

      // Is there an assignment for all the races in the division?
      foreach ($copy as $div) {
	if (($val = DB::$V->incID($args, (string)$div, DB::$BOAT, null)) !== null) {
	  unset($args[(string)$div]);
	  unset($remaining_divisions[(string)$div]);
	  foreach ($this->REGATTA->getRaces($div) as $race) {
	    $race->boat = $val;
	    DB::set($race);
	  }
	}
      }
      
      // Let the database decide whether the values are valid to begin
      // with. Just keep track of whether there were errors.
      foreach ($remaining_divisions as $div) {
	foreach ($this->REGATTA->getRaces($div) as $race) {
	  if (($val = DB::$V->incID($args, (string)$race, DB::$BOAT, null)) !== null &&
	      $val != $race->boat) {
	    $race->boat = $val;
	    DB::set($race);
	  }
	}
      }
      Session::pa(new PA("Updated races."));
    }
    return array();
  }
}
?>