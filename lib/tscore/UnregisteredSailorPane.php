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
    $this->PAGE->addContent($p = new XPort("Add sailor to temporary list"));
    $p->add(new XP(array(), "Enter unregistered sailors using the table below, up to five at a time."));

    $p->add($form = $this->createForm());

    // Create set of schools
    $schools = array();
    foreach ($this->REGATTA->getTeams() as $team)
      $schools[$team->school->id] = $team->school->nick_name;
    asort($schools);

    $form->add($tab = new XQuickTable(array('class'=>'short'),
				      array("School", "First name", "Last name", "Year", "Gender")));
    $gender = XSelect::fromArray('gender[]', array('M'=>"Male", 'F'=>"Female"));
    $school = XSelect::fromArray('school[]', $schools);
    for ($i = 0; $i < 5; $i++) {
      $tab->addRow(array($school,
			 new XTextInput('first_name[]'),
			 new XTextInput('last_name[]'),
			 new XTextInput('year[]', "", array('maxlength'=>4, 'size'=>4, 'style'=>'max-width:5em;width:5em;min-width:5em')),
			 $gender));
    }
    $form->add(new XSubmitInput("addtemp", "Add sailors"));

    $this->PAGE->addContent($p = new XPort("Review current regatta list"));
    $p->add(new XP(array(), "Below is a list of all the temporary sailors added in this regatta. You are given the option to delete any sailor that is not currently present in any of the RP forms for this regatta. If you made a mistake about a sailor's identity, remove that sailor and add a new one instead."));
    $rp = $this->REGATTA->getRpManager();
    $temp = $rp->getAddedSailors();
    if (count($temp) == 0) {
      $p->add(new XP(array(), "There are no temporary sailors added yet.", array('class'=>'message')));
    }
    else {
      $p->add($tab = new XQuickTable(array(), array("School", "First name", "Last name", "Year", "Gender", "Action"));
      foreach ($temp as $t) {
	// is this sailor currently in the RP forms? Otherwise, offer
	// to delete him/her
	$form = "";
	if (!$rp->isParticipating($t)) {
	  $form = $this->createForm();
	  $form->add(new XHiddenInput('sailor', $t->id));
	  $form->add(new XSubmitInput('remove-temp', "Remove"));
	}
	$sch = Preferences::getSchool($t->school);
	$tab->addRow(array($sch->nick_name,
			   $t->first_name,
			   $t->last_name,
			   $t->year,
			   ($t->gender == Sailor::MALE) ? "Male" : "Female",
			   $form));
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