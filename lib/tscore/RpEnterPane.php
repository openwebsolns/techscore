<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Controls the entry of RP information
 *
 * @author Dayan Paez
 * @version 2010-01-21
 */
class RpEnterPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Enter RP", $user, $reg);
  }

  protected function fillHTML(Array $args) {

    $teams = $this->REGATTA->getTeams();

    if (count($teams) == 0) {
      $this->PAGE->addContent($p = new Port("No teams registered"));
      $p->add(new XP(array(),
		     array("In order to register sailors, you will need to ",
			   new XA(sprintf("score/%s/team", $this->REGATTA->id()), "register teams"),
			   " first.")));
      return;
    }
    
    if (!isset($args['chosen_team']) ||
	($chosen_team = Preferences::getObjectWithProperty($teams, "id", $args['chosen_team'])) === null) {
      $chosen_team = $teams[0];
    }

    $rpManager = $this->REGATTA->getRpManager();
    $divisions = $this->REGATTA->getDivisions();
    // Output
    $this->PAGE->head->add(new GenericElement("script",
					      array(new XText()),
					      array("type"=>"text/javascript",
						    "src"=>"/inc/js/rp.js")));
    
    $this->PAGE->addContent($p = new Port("Choose a team",
					  array(),
					  array("class"=>"nonprint")));

    $p->add(new XP(array(),
		   array("Use the form below to enter RP information. If a sailor does not appear in the selection box, it means they are not in the ICSA database, and they have to be manually added to a temporary list in the ",
			 new XA(sprintf('/%s/temp', $this->REGATTA->id()), "Unregistered form"),
			 ".")));
    $p->add(new XP(array(),
		   array(new XStrong("Note:"),
			 " You may only submit up to two sailors in the same role in the same division at a time. To add a third or more skipper or crew in a given division, submit the form multiple times.")));

    // ------------------------------------------------------------
    // Change team
    // ------------------------------------------------------------
    $p->add($form = $this->createForm());
    $form->add(new FItem("Team:",
			 $f_sel = new FSelect("chosen_team",
					      array($chosen_team->id),
					      array("onchange"=>
						    "submit(this)"))));
    $team_opts = array();
    foreach ($teams as $team)
      $team_opts[$team->id] = sprintf("%s %s",
				      $team->school->nick_name,
				      $team->name);
    $f_sel->addOptions($team_opts);
    $form->add(new XSubmitAccessible("change_team", "Get form"));

    // ------------------------------------------------------------
    // RP Form
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new Port(sprintf("Fill out form for %s",
						  $chosen_team),
					  array(),
					  array("class"=>"nonprint")));
    // Representative
    $rep = $rpManager->getRepresentative($chosen_team);
    $rep_id = ($rep === null) ? "" : $rep->id;
    $p->add($form = $this->createForm());
    $form->add(new XHiddenInput("chosen_team", $chosen_team->id));
    $form->add(new FItem("Representative:",
			 $f_sel = new FSelect("rep", array($rep_id))));

    // ------------------------------------------------------------
    // - Create option lists
    //   If the regatta is in the current season, then only choose
    //   from 'active' sailors
    $active = 'all';
    $cur_season = new Season(new DateTime());
    if ((string)$cur_season ==  (string)$this->REGATTA->get(Regatta::SEASON))
      $active = true;
    $gender = ($this->REGATTA->get(Regatta::PARTICIPANT) == Regatta::PARTICIPANT_WOMEN) ?
      Sailor::FEMALE : null;
    $coaches = RpManager::getCoaches($chosen_team->school, $active);
    $sailors = RpManager::getSailors($chosen_team->school, $gender, $active);
    $un_slrs = RpManager::getUnregisteredSailors($chosen_team->school, $gender);

    $coach_optgroup = array();
    foreach ($coaches as $s)
      $coach_optgroup[] = new Option($s->id, $s);
    $coach_optgroup = new OptionGroup("Coaches", $coach_optgroup);

    $sailor_optgroup = array();
    foreach ($sailors as $s)
      $sailor_optgroup[] = new Option($s->id, $s);
    $sailor_optgroup = new OptionGroup("Sailors", $sailor_optgroup);

    $u_sailor_optgroup = array();
    foreach ($un_slrs as $s)
      $u_sailor_optgroup[] = new Option($s->id, $s);
    $u_sailor_optgroup = new OptionGroup("Non-ICSA", $u_sailor_optgroup);

    $sailor_options = array(new Option(),
			    $coach_optgroup,
			    $sailor_optgroup,
			    $u_sailor_optgroup);

    foreach ($sailor_options as $option)
      $f_sel->add(clone($option));

    // ------------------------------------------------------------
    // - Fill out form
    foreach ($divisions as $div) {
      // Get races and its occupants
      $occ = $this->getOccupantsRaces($div);

      // Fetch current rp's
      $cur_sk = $rpManager->getRP($chosen_team, $div, RP::SKIPPER);
      $cur_cr = $rpManager->getRP($chosen_team, $div, RP::CREW);

      $form->add(new XHeading("Division $div"));
      $form->add($tab_races = new Table());
      $form->add($tab_skip = new Table());

      // Create races table
      $tab_races->addHeader(new Row(array(Cell::th("Races"),
					  Cell::th("Crews"))));
      foreach ($occ as $crews => $races) {
	$tab_races->addRow(new Row(array(new Cell(Utilities::makeRange($races),
						  array("name"=>"races" . $div)),
					 new Cell(((int)$crews) - 1,
						  array("name"=>"occ" . $div)))));
      }

      // ------------------------------------------------------------
      // - Create skipper table
      $tab_skip->set("class", "narrow");
      $tab_skip->addHeader(new Row(array(Cell::th("Skippers"),
					 Cell::th("Races sailed"),
					 new Cell("", 
						  array("title"=>"Verify"),
						  1))));
      // Write already filled-in spots + 2 more
      for ($spot = 0; $spot < count($cur_sk) + 2; $spot++) {
	$value = ""; // value for "races sailed"
	if ($spot < count($cur_sk))
	  $value = Utilities::makeRange($cur_sk[$spot]->races_nums);

	$cur_sk_id = (isset($cur_sk[$spot])) ? $cur_sk[$spot]->sailor->id : "";
	$select_cell = new Cell($f_sel = new FSelect("sk$div$spot",
						     array($cur_sk_id),
						     array("onchange"=>"check()")));
	
	$tab_skip->addRow(new Row(array($select_cell,
					new Cell(new XTextInput("rsk$div$spot",
								$value,
								array("size"=>"8",
								      "class"=>"race_text",
								      "onchange"=>
								      "check()"))),
					new Cell(new XImg("/img/question.png", "Waiting to verify"),
						 array("id"=>"csk" . $div . $spot))),
				  array("class"=>"skipper")));

	// Add roster to select element
	foreach ($sailor_options as $option)
	  $f_sel->add(clone($option));
      }

      $num_crews = max(array_keys($occ));
      // Print table only if there is room in the boat for crews
      if ( $num_crews > 1 ) {
	// update crew table
	$form->add($tab_crew = new Table());
	$tab_crew->set("class", "narrow");
	$tab_crew->addHeader(new Row(array(Cell::th("Crews"),
					   Cell::th("Races sailed"),
					   new Cell("",
						    array("title"=>"verify"),
						    1))));
    
	//    write already filled-in spots + 2 more
	for ($spot = 0; $spot < count($cur_cr) + 2; $spot++) {
	  $value = ""; // value for "races sailed"
	  if ($spot < count($cur_cr))
	    $value = Utilities::makeRange($cur_cr[$spot]->races_nums);

	  $cur_cr_id = (isset($cur_cr[$spot])) ? $cur_cr[$spot]->sailor->id : "";
	  $select_cell = new Cell($f_sel = new FSelect("cr" .
						       $div .
						       $spot,
						       array($cur_cr_id),
						       array("onchange"=>"check()")));
	  $tab_crew->addRow(new Row(array($select_cell,
					  new Cell(new XTextInput("rcr" . 
								  $div .
								  $spot,
								  $value,
								  array("size"=>"8",
									"class"=>"race_text",
									"onchange"=>
									"check()"))),
					  new Cell(new XImg("/img/question.png", "Waiting to verify"),
						   array("id"=>"ccr" . $div . $spot)))));
	  
	  // Add options to $f_sel
	  foreach ($sailor_options as $option)
	    $f_sel->add(clone($option));
	}
      } // end if
    }

    // ------------------------------------------------------------
    // - Add submit
    $form->add(new XP(array(),
		      array(new XReset("reset", "Reset"),
			    new XSubmitInput("rpform", "Submit form",
					     array("id"=>"rpsubmit")))));
    $p->add(new GenericElement("script",
			       array(new XText("check()")),
			       array("type"=>"text/javascript")));
  }

  
  public function process(Array $args) {

    // ------------------------------------------------------------
    // Change teams
    // ------------------------------------------------------------
    $team = null;
    if (isset($args['chosen_team'])) {
      $team = $this->REGATTA->getTeam($args['chosen_team']);
      if ($team == null) {
	$mes = sprintf("Invalid team choice (%s).", $args['chosen_team']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	unset($args['chosen_team']);
	return $args;
      }
    }
    else {
      $mes = sprintf("Missing team choice.");
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return $args;
    }

    // ------------------------------------------------------------
    // RP data
    // ------------------------------------------------------------
    if (isset($_POST['rpform'])) {

      $rpManager = $this->REGATTA->getRpManager();
      $rpManager->reset($team);

      $cur_season = new Season(new DateTime());
      if ((string)$cur_season ==  (string)$this->REGATTA->get(Regatta::SEASON))
	$active = true;
      $gender = ($this->REGATTA->get(Regatta::PARTICIPANT) == Regatta::PARTICIPANT_WOMEN) ?
	Sailor::FEMALE : null;
      $sailors = array_merge(RpManager::getCoaches($team->school, $active),
			     RpManager::getSailors($team->school, $gender, $active),
			     RpManager::getUnregisteredSailors($team->school, $gender));

      // Insert representative
      if (!empty($args['rep']) ) {
	$rep = Preferences::getObjectWithProperty($sailors, "id", $args['rep']);
	if ($rep !== null)
	  $rpManager->setRepresentative($team, $rep);
	else {
	  $mes = sprintf("Invalid representative ID (%s).", $args['rep']);
	  $this->announce(new Announcement($mes, Announcement::ERROR));
	}
      }

      // To enter RP information, keep track of the number of crews
      // available in each race. To do this, keep a stacked
      // associative array with the following structure:
      //
      //  $rot[DIVISION][NUM][# of OCCUPANTS],
      //
      // that is, for each race number (sorted by divisions), keep
      // track of the number of occupants available
      $divisions = $this->REGATTA->getDivisions();
      $occupants = array();
      foreach ($divisions as $division) {
	$div = (string)$division;
	$occupants[$div] = array();
	$list = $this->getOccupantsRaces($division);
	foreach ($list as $occ => $race_nums) {
	  foreach ($race_nums as $race_num)
	    $occupants[$div][$race_num] = $occ;
	}
      }

      // Process each input, which is of the form:
      // ttDp, where tt = sk/cr, D=A/B/C/D (division) and p is position
      $errors = array();
      $rp = new RP();
      $rp->team = $team;
      foreach ($args as $s => $s_value) {
	if (preg_match('/^(cr|sk)[ABCD][0-9]+/', $s) > 0) {
	  // We have a sailor request upon us
	  $s_role = (substr($s, 0, 2) == "sk") ? RP::SKIPPER : RP::CREW;
	  $s_div  = substr($s,2,1);
	  $s_race = Utilities::parseRange($args["r" . $s]);
	  $s_obj  = Preferences::getObjectWithProperty($sailors, "id", $s_value);

	  if (!in_array($s_div, $divisions))
	    $errors[] = "Invalid division requested: $s_div.";
	  elseif ($s_race !== null && $s_obj  !== null) {
	    
	    // Eliminate those races from $s_race for which there is
	    // no space for a crew
	    $s_race_copy = $s_race;
	    if ($s_role == RP::CREW) {
	      foreach ($s_race as $i => $num) {
		if ($occupants[$s_div][$num] <= 1) {
		  unset($s_race_copy[$i]);
		}
		else
		  $occupants[$s_div][$num]--;
	      }
	    }
	    $rp->division   = new Division($s_div);
	    $rp->boat_role  = $s_role;
	    $rp->races_nums = $s_race_copy;
	    $rp->sailor     = $s_obj;
	    $rpManager->setRP($rp);
	  }
	}
      }
      $rpManager->updateLog();
      
      // Announce
      if (count($errors) > 0) {
	$mes = "Encountered these errors: " . implode(' ', $errors);;
	$this->announce(new Announcement($mes, Announcement::WARNING));
      }
      else {
	$this->announce(new Announcement("RP info updated."));
      }
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_RP, $team->school->id);
    }

    return $args;
  }

  /**
   * Return the number of the races in this division organized by
   * number of occupants in the boats. The result associative array
   * has keys which are the number of occupants and values which are a
   * comma separated list of the race numbers in the division with
   * that many occupants
   *
   * @param Division $div the division
   * @return Array<int, Array<int>> a set of race number lists
   */
  public function getOccupantsRaces(Division $div) {
    $races = $this->REGATTA->getRaces($div);
    $list = array();
    foreach ($races as $race) {
      $occ = $race->boat->occupants;
      if (!isset($list[$occ]))
	$list[$occ] = array();
      $list[$occ][] = $race->number;
    }
    return $list;
  }
}
?>