<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Controls the entry of unregistered sailor information
 *
 * 2011-03-22: Changed entry to use a table, with upo to 5 new entries
 * at a time
 *
 * @author Dayan Paez
 * @version 2010-01-23
 */
class UnregisteredSailorPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Unregistered sailors", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new Port("Add sailor to temporary list"));
    $p->addChild(new Para("Enter unregistered sailors using the table below, up to five at a time."));

    $p->addChild($form = $this->createForm());

    // Create set of schools
    $schools = array();
    foreach ($this->REGATTA->getTeams() as $team)
      $schools[$team->school->id] = $team->school->nick_name;
    asort($schools);

    $form->addChild($tab = new Table(array(), array('class'=>'short ')));
    $tab->addHeader(new Row(array(Cell::th("School"),
				  Cell::th("First name"),
				  Cell::th("Last name"),
				  Cell::th("Year"),
				  Cell::th("Gender"))));
    $gender = new FSelect('gender[]');
    $gender->addOptions(array('M'=>"Male", 'F'=>"Female"));
    $school = new FSelect('school[]');
    $school->addOptions($schools);
    for ($i = 0; $i < 5; $i++) {
      $tab->addRow(new Row(array(new Cell($school),
				 new Cell(new FText('first_name[]')),
				 new Cell(new FText('last_name[]')),
				 new Cell(new FText('year[]', "", array('maxlength'=>4, 'size'=>4, 'style'=>'max-width:5em;width:5em;min-width:5em'))),
				 new Cell($gender))));
    }
    $form->addChild(new FSubmit("addtemp", "Add sailors"));

    $this->PAGE->addContent($p = new Port("Review current regatta list"));
    $p->addChild(new Para("Below is a list of all the temporary sailors added in this regatta. You are given the option to delete any sailor that is not currently present in any of the RP forms for this regatta. If you made a mistake about a sailor's identity, remove that sailor and add a new one instead."));
    $rp = $this->REGATTA->getRpManager();
    $temp = $rp->getAddedSailors();
    if (count($temp) == 0) {
      $p->addChild(new Para("There are no temporary sailors added yet.", array('class'=>'message')));
    }
    else {
      $p->addChild($tab = new Table());
      $tab->addHeader(new Row(array(Cell::th("School"),
				    Cell::th("First name"),
				    Cell::th("Last name"),
				    Cell::th("Year"),
				    Cell::th("Gender"),
				    Cell::th("Action"))));
      foreach ($temp as $t) {
	$sch = Preferences::getSchool($t->school);
	$tab->addRow(new Row(array(new Cell($sch->nick_name),
				   new Cell($t->first_name),
				   new Cell($t->last_name),
				   new Cell($t->year),
				   new Cell(($t->gender == Sailor::MALE) ? "Male" : "Female"),
				   $d = new Cell())));
	// is this sailor currently in the RP forms? Otherwise, offer
	// to delete him/her
	if (!$rp->isParticipating($t)) {
	  $d->addChild($form = $this->createForm());
	  $form->addChild(new FHidden('sailor', $t->id));
	  $form->addChild(new FSubmit('remove-temp', "Remove"));
	}
      }
    }
  }

  
  public function process(Array $args) {

    // ------------------------------------------------------------
    // Add temporary sailor
    // ------------------------------------------------------------
    if (isset($args['addtemp'])) {
      // ------------------------------------------------------------
      // Realize that this process requires a 5-way map of arrays
      $cnt = null;
      foreach (array('school', 'first_name', 'last_name', 'year', 'gender') as $a) {
	if (!isset($args[$a]) || !is_array($args[$a])) {
	  $this->announce(new Announcement("Data format not valid.", Announcement::ERROR));
	  return $args;
	}
	if ($cnt === null)
	  $cnt = count($args[$a]);
	elseif ($cnt != count($args[$a])) {
	  $this->announce(new Announcement("Each data set must be of the same size.", Announcement::ERROR));
	  return $args;
	}
      }

      $rp = $this->REGATTA->getRpManager();
      $added = 0;
      while (count($args['school']) > 0) {
	$sch = array_shift($args['school']);
	$first_name = trim(array_shift($args['first_name']));
	$last_name  = trim(array_shift($args['last_name']));
	$year = trim(array_shift($args['year']));
	$gender = trim(array_shift($args['gender']));

	$sailor = new Sailor();
	if ($first_name != "" && $last_name != "") {
	  $school = Preferences::getSchool($sch);
	  if ($school === null) {
	    $this->announce(new Announcement(sprintf("School ID provided is invalid (%s).", $sch), Announcement::ERROR));
	  }
	  else {
	    $sailor->school = $school;
	    $sailor->registered = false;
	    $sailor->first_name = $first_name;
	    $sailor->last_name = $last_name;
	    $sailor->year = ($year == "") ? null : $year;
	    $sailor->gender = ($gender == 'F') ? 'F' : 'M';

	    $rp->addTempSailor($sailor);
	    $added++;
	  }
	}
      }
      if ($added > 0)
	$this->announce(new Announcement("Added $added temporary sailor(s)."));
    }

    // ------------------------------------------------------------
    // Remove temp sailor
    // ------------------------------------------------------------
    if (isset($args['remove-temp'])) {
      $rp = $this->REGATTA->getRpManager();
      if (!isset($args['sailor'])) {
	$this->announce(new Announcement("No sailor to delete given."));
	return $args;
      }
      try {
	$sailor = RpManager::getSailor((int)$args['sailor']);
	$rp->removeTempSailor($sailor);
	$this->announce(new Announcement("Removed temporary sailor."));
      }
      catch (Exception $e) {
	$this->announce(new Announcement("Invalid sailor ID provided."));
	return $args;
      }
    }
    return $args;
  }
}
?>