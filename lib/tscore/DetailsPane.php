<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package tscore
 */

require_once('conf.php');

/**
 * The "home" pane where the regatta's details are edited.
 *
 * 2010-02-24: Allowed scoring rules change
 *
 * @author Dayan Paez
 * @version 2009-09-27
 */
class DetailsPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Settings", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    // Regatta details
    $this->PAGE->addContent($p = new Port('Regatta details'));
    $p->addHelp("node9.html#SECTION00521000000000000000");

    $p->addChild($reg_form = $this->createForm());
    // Name
    $value = $this->REGATTA->get(Regatta::NAME);
    $reg_form->addChild(new FItem("Name:",
				  new FText("reg_name",
					    stripslashes($value),
					    array("maxlength"=>40,
						  "size"     =>20))));

    // Date
    $start_time = $this->REGATTA->get(Regatta::START_TIME);
    $date = date_format($start_time, 'm/d/Y');
    $reg_form->addChild(new FItem("Date:", 
				  new FText("sdate",
					    $date,
					    array("maxlength"=>30,
						  "size"     =>20,
						  "id"=>"datepicker"))));
    // Duration
    $value = $this->REGATTA->get(Regatta::DURATION);
    $reg_form->addChild(new FItem("Duration (days):",
				  new FText("duration",
					    $value,
					    array("maxlength"=>2,
						  "size"     =>2))));
    // On the water
    $value = date_format($start_time, "H:i");
    $reg_form->addChild(new FItem("On the water:",
				  new FText("stime", $value,
					    array("maxlength"=>8,
						  "size"     =>8))));

    // Venue
    $value = "";
    $venue = $this->REGATTA->get(Regatta::VENUE);
    if ($venue !== null)
      $value = $venue->id;
    $reg_form->addChild(new FItem("Venue:", $r_type = new FSelect("venue", array($value))));
    $r_type->addOptions(array("" => ""));
    $venues = array();
    foreach (Preferences::getVenues() as $venue)
      $venues[$venue->id] = $venue->name;
    $r_type->addOptions($venues);

    // Regatta type
    $value = $this->REGATTA->get(Regatta::TYPE);
    $reg_form->addChild($item = new FItem("Type:",
					  $f_sel = new FSelect("type",
							       array($value))));
    $types = Preferences::getRegattaTypeAssoc();
    unset($types['personal']);
    $f_sel->addOptionGroup("Public", $types);
    $f_sel->addOptionGroup("Not-published", array('personal' => "Personal"));
    $item->addChild(new Span(array(new Text("Choose \"Personal\" to un-publish")),
			     array('class'=>'message')));

    // Regatta participation
    $value = $this->REGATTA->get(Regatta::PARTICIPANT);
    $reg_form->addChild($item = new FItem("Participation:",
					  $f_sel = new FSelect("participant", array($value))));
    $f_sel->addOptions(Preferences::getRegattaParticipantAssoc());
    // will changing this value affect the RP information?
    if ($value == Regatta::PARTICIPANT_COED) {
      $rp = $this->REGATTA->getRpManager();
      if ($rp->hasGender(Sailor::MALE)) {
	$item->addChild(new Span(array(new Text("Changing this value will affect RP info")),
				 array('class'=>'message')));
      }
    }

    // Scoring rules
    $value = $this->REGATTA->get(Regatta::SCORING);
    $reg_form->addChild(new FItem("Scoring:", $f_sel = new FSelect("scoring", array($value))));
    $f_sel->addOptions(Preferences::getRegattaScoringAssoc());

    // Hosts: first add the current hosts, then the entire list of
    // schools in the affiliation ordered by conference
    $hosts = $this->REGATTA->getHosts();
    $reg_form->addChild(new FItem('Host(s):<br/><small>Hold down <kbd>Ctrl</kbd> to choose more than one</small>', $f_sel = new FSelect("host[]")));
    $f_sel->addChild($opt_group = new OptionGroup("Current"));
    $schools = array(); // track these so as not to include them later
    foreach ($hosts as $host) {
      $schools[$host->school->id] = $host->school;
      $opt_group->addChild(new Option($host->school->id, $host->school, array('selected' => 'selected')));
    }

    // go through each conference
    foreach (Preferences::getConferences() as $conf) {
      $opts = array();
      foreach ($this->USER->getSchools($conf) as $school) {
	if (!isset($schools[$school->id]))
	  $opts[$school->id] = $school;
      }
      if (count($opts) > 0)
	$f_sel->addOptionGroup($conf, $opts);
    }
    $f_sel->addAttr('multiple', 'multiple');
    $f_sel->addAttr('size', 10);

    // Update button
    $reg_form->addChild($reg_sub = new FSubmit("edit_reg", "Edit"));
    // If finalized, disable submit
    $finalized = $this->REGATTA->get(Regatta::FINALIZED);

    // -------------------- Finalize regatta -------------------- //
    if ($finalized === null) {
      if ($this->REGATTA->hasFinishes()) {
	$this->PAGE->addContent($p = new Port("Finalize regatta"));
	$p->addHelp("node9.html#SECTION00521100000000000000");

	$para = '
        Once <strong>finalized</strong>, all the information (including rp,
        and rotation) about unscored regattas will be removed from the
        database. No <strong>new</strong> information can be entered,
        although you may still be able to edit existing information.';
	$p->addChild(new Para($para));
  
	$p->addChild($form = $this->createForm());

	$form->addChild(new FItem(new FCheckbox("approve",
						"on",
						array("id"=>"approve")),
				  new Label("approve",
					    "I wish to finalize this regatta.",
					    array("class"=>"strong"))));

	$form->addChild(new FSubmit("finalize",
				    "Finalize!"));
      }
    }
    else {
      $para = sprintf("This regatta was finalized on %s.",
		      $finalized->format("l, F j Y"));
      $p->addChild(new Para($para, array("class"=>"strong")));
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
	  $this->announce(new Announcement("Unable to change the type of regatta. Either an invalid type was specified, or more likely you attempted to activate a regatta that is under the same name as another already-activated regatta for the current season. Before you can do that, please make sure that the other regatta with the same name as this one is removed or de-activated (made personal) before proceeding.", Announcement::WARNING));
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
	  Preferences::getVenue((int)$args['venue']))
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
	    $this->announce(new Announcement("Removed sailors from RP.", Announcement::WARNING));
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
	  $school = Preferences::getSchool($id);
	  if ($school !== null && isset($schools[$school->id]))
	    $hosts[] = $school;
	}
	if (count($hosts) == 0)
	  $this->announce(new Announcement("There must be at least one host for each regatta. Left as is.", Announcement::WARNING));
	else {
	  $this->REGATTA->resetHosts();
	  foreach ($hosts as $school)
	    $this->REGATTA->addHost($school);
	}
      }
      print_r($args);

      $this->announce(new Announcement("Edited regatta details."));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DETAILS);
    }

    // ------------------------------------------------------------
    // Finalize
    if (isset($args['finalize'])) {
      if (!$this->REGATTA->hasFinishes()) {
	$this->announce(new Announcement("You cannot finalize a project with no finishes. To delete the regatta, please mark it as \"personal\".", Announcement::ERROR));
      }
      elseif (isset($args['approve'])) {
	$this->REGATTA->set(Regatta::FINALIZED, new DateTime());
	$this->announce(new Announcement("Regatta has been finalized."));
	UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DETAILS);
      }
      else
	$this->announce(new Announcement("Please check the box to finalize.", Announcement::ERROR));
    }
  }
}
?>
