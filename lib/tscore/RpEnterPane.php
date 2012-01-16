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

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Enter RP", $user, $reg);
  }

  protected function fillHTML(Array $args) {

    $teams = $this->REGATTA->getTeams();

    if (count($teams) == 0) {
      $this->PAGE->addContent($p = new XPort("No teams registered"));
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
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/rp.js'));
    $this->PAGE->addContent($p = new XPort("Choose a team"));
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
    $form->add(new FItem("Team:", $f_sel = new XSelect("chosen_team", array("onchange"=>"submit(this)"))));
    $team_opts = array();
    foreach ($teams as $team) {
      $f_sel->add($opt = new FOption($team->id, sprintf("%s %s", $team->school->nick_name, $team->name)));
      if ($team->id == $chosen_team->id)
	$opt->set('selected', 'selected');
    }
    $form->add(new XSubmitAccessible("change_team", "Get form"));

    // ------------------------------------------------------------
    // RP Form
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort(sprintf("Fill out form for %s", $chosen_team)));
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
    $coaches = $chosen_team->school->getCoaches($active);
    $sailors = $chosen_team->school->getSailors($gender, $active);
    $un_slrs = $chosen_team->school->getUnregisteredSailors($gender);

    $sailor_options = array("Coaches" => array(), "Sailors" => array(), "Non-ICSA" => array());
    foreach ($coaches as $s)
      $sailor_options["Coaches"][$s->id] = (string)$s;
    foreach ($sailors as $s)
      $sailor_options["Sailors"][$s->id] = (string)$s;
    foreach ($un_slrs as $s)
      $sailor_options["Non-ICSA"][$s->id] = (string)$s;
    
    // Representative
    $rep = $rpManager->getRepresentative($chosen_team);
    $rep_id = ($rep === null) ? "" : $rep->id;
    $p->add($form = $this->createForm());
    $form->add(new XHiddenInput("chosen_team", $chosen_team->id));
    $form->add(new FItem("Representative:", XSelect::fromArray('rep', $sailor_options, $rep_id)));

    // Remove coaches from list
    unset($sailor_options["Coaches"]);

    // ------------------------------------------------------------
    // - Fill out form
    foreach ($divisions as $div) {
      // Get races and its occupants
      $occ = $this->getOccupantsRaces($div);

      // Fetch current rp's
      $cur_sk = $rpManager->getRP($chosen_team, $div, RP2::SKIPPER);
      $cur_cr = $rpManager->getRP($chosen_team, $div, RP2::CREW);

      $form->add(new XHeading("Division $div"));
      $form->add($tab_races = new XQuickTable(array(), array("Races", "Crews")));
      $form->add($tab_skip = new XQuickTable(array('class'=>'narrow'), array("Skippers", "Races sailed", "")));

      // Create races table
      foreach ($occ as $crews => $races) {
	$tab_races->addRow(array(new XTD(array("name"=>"races" . $div), DB::makeRange($races)),
				 new XTD(array("name"=>"occ" . $div),   ((int)$crews) - 1)));
      }

      // ------------------------------------------------------------
      // - Create skipper table
      // Write already filled-in spots + 2 more
      for ($spot = 0; $spot < count($cur_sk) + 2; $spot++) {
	$value = ""; // value for "races sailed"
	if ($spot < count($cur_sk))
	  $value = DB::makeRange($cur_sk[$spot]->races_nums);

	$cur_sk_id = (isset($cur_sk[$spot])) ? $cur_sk[$spot]->sailor->id : "";
	$select_cell = XSelect::fromArray("sk$div$spot", $sailor_options, $cur_sk_id, array('onchange'=>'check()'));
	$tab_skip->addRow(array($select_cell,
				new XTextInput("rsk$div$spot", $value,
					       array("size"=>"8",
						     "class"=>"race_text",
						     "onchange"=>
						     "check()")),
				new XTD(array('id'=>"csk$div$spot"),
					new XImg("/inc/img/question.png", "Waiting to verify"))),
			  array("class"=>"skipper"));
      }

      $num_crews = max(array_keys($occ));
      // Print table only if there is room in the boat for crews
      if ( $num_crews > 1 ) {
	// update crew table
	$form->add($tab_crew = new XQuickTable(array('class'=>'narrow'), array("Crews", "Races sailed", "")));
    
	//    write already filled-in spots + 2 more
	for ($spot = 0; $spot < count($cur_cr) + 2; $spot++) {
	  $value = ""; // value for "races sailed"
	  if ($spot < count($cur_cr))
	    $value = DB::makeRange($cur_cr[$spot]->races_nums);

	  $cur_cr_id = (isset($cur_cr[$spot])) ? $cur_cr[$spot]->sailor->id : "";
	  $select_cell = XSelect::fromArray("cr$div$spot", $sailor_options, $cur_cr_id, array('onchange'=>'check()'));
	  $tab_crew->addRow(array($select_cell,
				  new XTextInput("rcr$div$spot", $value,
						 array("size"=>"8",
						       "class"=>"race_text",
						       "onchange"=>
						       "check()")),
				  new XTD(array('id'=>"ccr$div$spot"),
					  new XImg("/inc/img/question.png", "Waiting to verify"))));
	}
      } // end if
    }

    // ------------------------------------------------------------
    // - Add submit
    $form->add(new XP(array(),
		      array(new XReset("reset", "Reset"),
			    new XSubmitInput("rpform", "Submit form",
					     array("id"=>"rpsubmit")))));
    $p->add(new XScript('text/javascript', null, "check()"));
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
	Session::pa(new PA($mes, PA::E));
	unset($args['chosen_team']);
	return $args;
      }
    }
    else {
      $mes = sprintf("Missing team choice.");
      Session::pa(new PA($mes, PA::E));
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
      $sailors = array_merge($team->school->getCoaches($active),
			     $team->school->getSailors($gender, $active),
			     $team->school->getUnregisteredSailors($gender));

      // Insert representative
      if (!empty($args['rep']) ) {
	$rep = Preferences::getObjectWithProperty($sailors, "id", $args['rep']);
	if ($rep !== null)
	  $rpManager->setRepresentative($team, $rep);
	else {
	  Session::pa(new PA(sprintf("Invalid representative ID (%s).", $args['rep']), PA::I));
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
      $rps = array(); // list of RPEntries
      foreach ($args as $s => $s_value) {
	if (preg_match('/^(cr|sk)[ABCD][0-9]+/', $s) > 0) {
	  // We have a sailor request upon us
	  $s_role = (substr($s, 0, 2) == "sk") ? RP2::SKIPPER : RP2::CREW;
	  $s_div  = substr($s,2,1);
	  $s_race = DB::parseRange($args["r" . $s]);
	  $s_obj  = Preferences::getObjectWithProperty($sailors, "id", $s_value);

	  if (!in_array($s_div, $divisions))
	    $errors[] = "Invalid division requested: $s_div.";
	  elseif ($s_race !== null && $s_obj  !== null) {
	    
	    // Eliminate those races from $s_race for which there is
	    // no space for a crew
	    $s_race_copy = $s_race;
	    if ($s_role == RP2::CREW) {
	      foreach ($s_race as $i => $num) {
		if ($occupants[$s_div][$num] <= 1) {
		  unset($s_race_copy[$i]);
		}
		else
		  $occupants[$s_div][$num]--;
	      }
	    }
	    // Create the objects
	    // @TODO
	    $div = new Division($s_div);
	    foreach ($s_race_copy as $num) {
	      $rp = new RPEntry();
	      $rp->team = $team;
	      $rp->race = $this->REGATTA->getRace($div, $num);
	      $rp->boat_role  = $s_role;
	      $rp->sailor     = $s_obj;
	      $rps[] = $rp;
	    }
	  }
	}
      }
      // insert all!
      $rpManager->setRP($rps);
      $rpManager->updateLog();
      
      // Announce
      if (count($errors) > 0) {
	$mes = "Encountered these errors: " . implode(' ', $errors);;
	Session::pa(new PA($mes, PA::I));
      }
      else {
	Session::pa(new PA("RP info updated."));
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