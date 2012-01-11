<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package tscore
 */

require_once('tscore/AbstractPane.php');

/**
 * The "home" pane where the regatta's details are edited.
 *
 * 2010-02-24: Allowed scoring rules change
 *
 * @author Dayan Paez
 * @version 2009-09-27
 */
class DetailsPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Settings", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    // Regatta details
    $p = new XPort('Regatta details');
    $p->addHelp("node9.html#SECTION00521000000000000000");

    $p->add($reg_form = $this->createForm());
    // Name
    $value = $this->REGATTA->get(Regatta::NAME);
    $reg_form->add(new FItem("Name:",
			     new XTextInput("reg_name",
					    stripslashes($value),
					    array("maxlength"=>40,
						  "size"     =>20))));
    
    // Date
    $start_time = $this->REGATTA->get(Regatta::START_TIME);
    $date = date_format($start_time, 'm/d/Y');
    $reg_form->add(new FItem("Date:", 
			     new XTextInput("sdate",
					    $date,
					    array("maxlength"=>30,
						  "size"     =>20,
						  "id"=>"datepicker"))));
    // Duration
    $value = $this->REGATTA->get(Regatta::DURATION);
    $reg_form->add(new FItem("Duration (days):",
			     new XTextInput("duration",
					    $value,
					    array("maxlength"=>2,
						  "size"     =>2))));
    // On the water
    $value = date_format($start_time, "H:i");
    $reg_form->add(new FItem("On the water:",
			     new XTextInput("stime", $value,
					    array("maxlength"=>8,
						  "size"     =>8))));

    // Venue
    $venue = $this->REGATTA->get(Regatta::VENUE);
    $reg_form->add(new FItem("Venue:", $r_type = new XSelect("venue")));
    foreach (DB::getVenues() as $v) {
      $r_type->add($opt = new FOption($v->id, $v->name));
      if ($venue !== null && $venue->id == $v->id)
	$opt->set('selected', 'selected');
    }

    // Regatta type
    $value = $this->REGATTA->get(Regatta::TYPE);
    $types = Preferences::getRegattaTypeAssoc();
    unset($types['personal']);
    $reg_form->add($item = new FItem("Type:",
				     XSelect::fromArray('type',
							array("Public" => $types,
							      "Not-published" => array('personal'=>"Personal")),
							$value)));
    $item->add(new XMessage("Choose \"Personal\" to un-publish"));

    // Regatta participation
    $value = $this->REGATTA->get(Regatta::PARTICIPANT);
    $reg_form->add($item = new FItem("Participation:",
				     XSelect::fromArray('participant',
							Preferences::getRegattaParticipantAssoc(),
							$value)));
    // will changing this value affect the RP information?
    if ($value == Regatta::PARTICIPANT_COED)
      $item->add(new XMessage("Changing this value may affect RP info"));

    // Scoring rules
    $value = $this->REGATTA->get(Regatta::SCORING);
    $reg_form->add(new FItem("Scoring:",
			     XSelect::fromArray('scoring',
						Preferences::getRegattaParticipantAssoc(),
						$value)));

    // Hosts: first add the current hosts, then the entire list of
    // schools in the affiliation ordered by conference
    $hosts = $this->REGATTA->getHosts();
    $reg_form->add($f_item = new FItem('Host(s):', $f_sel = new XSelectM("host[]", array('size'=>10))));
    
    $f_sel->add($opt_group = new FOptionGroup("Current"));
    $schools = array(); // track these so as not to include them later
    foreach ($hosts as $host) {
      $schools[$host->account->school->id] = $host->account->school;
      $opt_group->add(new FOption($host->account->school->id, $host->account->school, array('selected' => 'selected')));
    }
    $f_item->add(new XMessage("Hold down Ctrl to choose more than one"));

    // go through each conference
    foreach (DB::getConferences() as $conf) {
      $opts = array();
      foreach ($this->USER->getSchools($conf) as $school) {
	if (!isset($schools[$school->id]))
	  $opts[] = new FOption($school->id, $school);
      }
      if (count($opts) > 0)
	$f_sel->add(new FOptionGroup($conf, $opts));
    }

    // Update button
    $reg_form->add(new XP(array(), new XSubmitInput("edit_reg", "Edit")));
    // If finalized, disable submit
    $finalized = $this->REGATTA->get(Regatta::FINALIZED);

    // -------------------- Finalize regatta -------------------- //
    $p2 = new XText("");
    if ($finalized === null) {
      if ($this->REGATTA->hasFinishes()) {
	$p2 = new XPort("Finalize regatta");
	$p2->set('id', 'finalize');
	$p2->addHelp("node9.html#SECTION00521100000000000000");
	$p2->add(new XP(array(),
			array("Once ", new XStrong("finalized"), ", all the information (including rp, and rotation) about unscored regattas will be removed from the database. No ", new XStrong("new"), " information can be entered, although you may still be able to edit existing information.")));
  
	$p2->add($form = $this->createForm());

	$form->add(new FItem(new XCheckboxInput("approve",
						"on",
						array("id"=>"approve")),
			     new XLabel("approve",
					"I wish to finalize this regatta.",
					array("class"=>"strong"))));

	$form->add(new XSubmitInput("finalize",
				    "Finalize!"));
      }
    }
    else {
      $p->add(new XP(array("class"=>"strong"),
		     sprintf("This regatta was finalized on %s.", $finalized->format("l, F j Y"))));
    }
    // If the regatta has already "ended", then the finalize port
    // should go first to urge the user to take action.
    if ($this->REGATTA->get(Regatta::END_DATE) < new DateTime()) {
      $this->PAGE->addContent($p2);
      $this->PAGE->addContent($p);
    }
    else {
      $this->PAGE->addContent($p);
      $this->PAGE->addContent($p2);
    }
  }

  /**
   * Process edits to the regatta
   */
  public function process(Array $args) {

    // ------------------------------------------------------------
    // Details
    if ( isset($args['edit_reg']) ) {

      // Type
      if (isset($args['type'])) {
	// this may throw an error for two reasons: illegal type or
	// invalid nick name
	try {
	  $this->REGATTA->set(Regatta::TYPE, $args['type']);
	}
	catch (InvalidArgumentException $e) {
	  Session::pa(new PA("Unable to change the type of regatta. Either an invalid type was specified, or more likely you attempted to activate a regatta that is under the same name as another already-activated regatta for the current season. Before you can do that, please make sure that the other regatta with the same name as this one is removed or de-activated (made personal) before proceeding.", PA::I));
	  return;
	}
      }

      // Name
      if (isset($args['reg_name']) && strlen(trim($args['reg_name'])) > 0 &&
	  ($args['reg_name'] != $this->REGATTA->get(Regatta::NAME))) {
	$this->REGATTA->set(Regatta::NAME, $args['reg_name']);
      }

      // Start time
      if (isset($args['sdate']) &&
	  isset($args['stime']) &&
	  $sdate = new DateTime($args['sdate'] . ' ' . $args['stime'])) {
	$this->REGATTA->set(Regatta::START_TIME, $sdate);
      }

      // Duration
      if (isset($args['duration']) &&
	  is_numeric($args['duration']) &&
	  $args['duration'] > 0) {
	$duration = (int)($args['duration']);
	$edate = new DateTime(sprintf("%s + %d days",
				      $args['sdate'],
				      $duration-1));
	$this->REGATTA->set(Regatta::END_DATE, $edate);
      }

      // Venue
      if (isset($args['venue']) && is_numeric($args['venue']) &&
	  DB::getVenue($args['venue']))
	$this->REGATTA->set(Regatta::VENUE, (int)$args['venue']);

      // Scoring
      if (isset($args['scoring']) &&
	  in_array($args['scoring'], array_keys(Preferences::getRegattaScoringAssoc()))) {
	$this->REGATTA->set(Regatta::SCORING, $args['scoring']);
      }

      // Participation
      if (isset($args['participant']) &&
	  in_array($args['participant'], array_keys(Preferences::getRegattaParticipantAssoc()))) {
	$this->REGATTA->set(Regatta::PARTICIPANT, $args['participant']);
	// affect RP accordingly
	if ($args['participant'] == Regatta::PARTICIPANT_WOMEN) {
	  $rp = $this->REGATTA->getRpManager();
	  if ($rp->hasGender(Sailor::MALE)) {
	    $rp->removeGender(Sailor::MALE);
	    Session::pa(new PA("Removed sailors from RP.", PA::I));
	  }
	}
      }

      // Host(s): go through the list, ascertaining the validity. Once
      // we know we have at least one valid host for the regatta,
      // reset the hosts, and add each school, one at a time
      if (isset($args['host']) && is_array($args['host'])) {
	$hosts = array();
	$schools = $this->USER->getSchools();
	foreach ($args['host'] as $id) {
	  $school = DB::getSchool($id);
	  if ($school !== null && isset($schools[$school->id]))
	    $hosts[] = $school;
	}
	if (count($hosts) == 0)
	  Session::pa(new PA("There must be at least one host for each regatta. Left as is.", PA::I));
	else {
	  $this->REGATTA->resetHosts();
	  foreach ($hosts as $school)
	    $this->REGATTA->addHost($school);
	}
      }
      print_r($args);

      Session::pa(new PA("Edited regatta details."));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DETAILS);
    }

    // ------------------------------------------------------------
    // Finalize
    if (isset($args['finalize'])) {
      if (!$this->REGATTA->hasFinishes()) {
	Session::pa(new PA("You cannot finalize a project with no finishes. To delete the regatta, please mark it as \"personal\".", PA::E));
      }
      elseif (isset($args['approve'])) {
	$this->REGATTA->set(Regatta::FINALIZED, new DateTime());
	Session::pa(new PA("Regatta has been finalized."));
	UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DETAILS);
      }
      else
	Session::pa(new PA("Please check the box to finalize.", PA::E));
    }
  }
}
?>
