<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * (Re)Enters finishes
 *
 * 2010-02-25: Allow entering combined divisions. Of course, deal with
 * the team name entry as well as rotation
 *
 * @author Dayan Paez
 * @version 2010-01-24
 */
class EnterFinishPane extends AbstractPane {

  private $ACTIONS = array("ROT" => "Sail numbers from rotation",
			   "TMS" => "Team names");

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Enter finish", $user, $reg);
  }

  /**
   * Fills the page in the case of a combined division scoring
   *
   * @param Array $args the argument
   */
  private function fillCombined(Array $args) {
    $divisions = $this->REGATTA->getDivisions();

    // Determine race to display: either as requested or the next
    // unscored race, or the last race.
    $race = null;
    if (!empty($args['chosen_race'])) {
      try {
	$race = Race::parse($args['chosen_race']);
	$race = $this->REGATTA->getRace($race->division, $race->number);
      } catch (Exception $e) { $race = null; }
    }
    if ($race == null) {
      $races = $this->REGATTA->getUnscoredRaces();
      $race = array_shift($races);
    }
    if ($race == null) {
      $races = $this->REGATTA->getScoredRaces();
      $race = array_pop($races);
    }

    $rotation = $this->REGATTA->getRotation();

    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/finish.js'));
    $this->PAGE->addContent($p = new XPort("Choose race number"));

    // ------------------------------------------------------------
    // Choose race
    // ------------------------------------------------------------
    $p->add($form = $this->createForm());
    $form->set("id", "race_form");
    $form->add(new XP(array(), "This regatta is being scored with combined divisions. Please enter any race in any division to enter finishes for that race number across all divisions."));

    $form->add($fitem = new FItem("Race:", 
				  new XTextInput("chosen_race",
						 $race,
						 array("size"=>"4",
						       "maxlength"=>"3",
						       "id"=>"chosen_race",
						       "class"=>"narrow"))));

    // Table of possible races
    $race_nums = array();
    foreach ($this->REGATTA->getUnscoredRaces($divisions[0]) as $r)
      $race_nums[] = $r->number;
    $fitem->add($tab = new XQuickTable(array('class'=>'narrow'), array("#")));
    $cont = Utilities::makeRange($race_nums);
    if (empty($cont))
      $cont = "--";
    $tab->addRow($cont);

    // Using? If there is a rotation, use it by default
    
    $using = (isset($args['finish_using'])) ?
      $args['finish_using'] : "ROT";

    if (!$rotation->isAssigned($race)) {
      unset($this->ACTIONS["ROT"]);
      $using = "TMS";
    }
    
    $form->add(new FItem("Using:", XSelect::fromArray('finish_using', $this->ACTIONS, $using)));
    $form->add(new XSubmitInput("choose_race",
				"Change race"));

    // ------------------------------------------------------------
    // Enter finishes
    // ------------------------------------------------------------
    $races = array();
    $finishes = array();
    foreach ($divisions as $div) {
      $r = $this->REGATTA->getRace($div, $race->number);
      $races[] = $r;
      $f2 = $this->REGATTA->getFinishes($r);
      $finishes = array_merge($finishes, $f2);
    }
    usort($finishes, "Finish::compareEntered");

    $title = sprintf("Add/edit finish for race %s across all divisions", $race->number);
    $this->PAGE->addContent($p = new XPort($title));
    $p->add($form = $this->createForm());
    $form->set("id", "finish_form");

    $form->add(new XHiddenInput("race", $race));
    if ($using == "ROT") {
      // ------------------------------------------------------------
      // Rotation-based
      // ------------------------------------------------------------
      $form->add($fitem = new FItem("Enter sail numbers:",
				    $tab = new XQuickTable(array('id'=>'finish_table', 'class'=>'narrow'),
							   array("Sail", ">", "Finish"))));
      $fitem->set('title', 'Click on left column to push to right column');

      // - Fill possible sails
      $pos_sails = array();
      foreach ($races as $race)
	$pos_sails = array_merge($pos_sails, $rotation->getSails($race));
      sort($pos_sails);
      $pos_sails = array_unique($pos_sails);
      $row = array();
      foreach ($pos_sails as $i => $aPS) {
	$current_sail = (count($finishes) > 0) ?
	  $rotation->getSail($finishes[$i]->race, $finishes[$i]->team) : "";
	$tab->addRow(array(new XTD($aPS, array("name"=>"pos_sail", "class"=>"pos_sail",	"id"=>"pos_sail")),
			   new XImg("/inc/img/question.png", "Waiting for input", array("id"=>"check" . $i)),
			   new XTextInput("p" . $i, $current_sail,
					  array("id"=>"sail" . $i,
						"tabindex"=>($i+1),
						"onkeyup"=>"checkSails()",
						"class"=>"small",
						"size"=>"2"))));
      }

      // Submit buttom
      //$form->add(new XReset("reset_finish", "Reset"));
      $form->add(new XSubmitInput("f_places",
				  sprintf("Enter finish for race %s", $race->number),
				  array("id"=>"submitfinish", "tabindex"=>($i+1))));
    }
    else {
      // ------------------------------------------------------------
      // Team lists
      // ------------------------------------------------------------
      $form->add($fitem = new FItem("Enter teams:",
				    $tab = new XQuickTable(array('id'=>'finish_table', 'class'=>'narrow'),
							   array("Teams", ">", "Finish"))));
      $fitem->set('title', "Click on left column to push to right column");

      // - Fill possible teams and select
      $teams = $this->REGATTA->getTeams();
      $team_opts = array("" => "");
      foreach ($divisions as $div) {
	foreach ($teams as $team) {
	  $team_opts[sprintf("%s,%s", $div, $team->id)] = sprintf("%s: %s %s",
								  $div,
								  $team->school->nick_name,
								  $team->name);
	}
      }
      $attrs = array("name" =>"pos_team", "id" =>"pos_team", "class"=>"pos_sail");
      $i = 0;
      foreach ($divisions as $div) {
	foreach ($teams as $team) {
	  $name = sprintf("%s: %s %s",
			  $div,
			  $team->school->nick_name,
			  $team->name);
	  $attrs["value"] = sprintf("%s,%s", $div, $team->id);

	  $current_team = (count($finishes) > 0) ?
	    sprintf("%s,%s", $finishes[$i]->race->division, $finishes[$i]->team->id) : "";
	  $tab->addRow(array(new XTD($attrs, $name),
			     new XImg("/inc/img/question.png", "Waiting for input",  array("id"=>"check" . $i)),
			     $sel = XSelect::fromArray("p" . $i, $current_team, $team_opts)));
	  $sel->set('id', "team$i");
	  $sel->set('tabindex', $i + 1);
	  $sel->set('onchange', 'checkTeams()');
	  $i++;
	}
      }

      // Submit buttom
      $form->add(new XSubmitInput("f_teams",
				  sprintf("Enter finish for race %s", $race->number),
				  array("id"=>"submitfinish", "tabindex"=>($i+1))));
    }
  }


  protected function fillHTML(Array $args) {

    if ($this->REGATTA->get(Regatta::SCORING) == Regatta::SCORING_COMBINED) {
      $this->fillCombined($args);
      return;
    }

    $divisions = $this->REGATTA->getDivisions();

    // Determine race to display: either as requested or the next
    // unscored race, or the last race
    $race = null;
    if (!empty($args['chosen_race'])) {
      try {
	$race = Race::parse($args['chosen_race']);
	$race = $this->REGATTA->getRace($race->division, $race->number);
      } catch (Exception $e) { $race = null; }
    }
    if ($race == null) {
      $races = $this->REGATTA->getUnscoredRaces();
      $race = array_shift($races);
    }
    if ($race == null) {
      Session::pa(new PA("No new races to score.", PA::I));
      $this->redirect();
    }

    $rotation = $this->REGATTA->getRotation();

    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/finish.js'));
    $this->PAGE->addContent($p = new XPort("Choose race"));

    // ------------------------------------------------------------
    // Choose race
    // ------------------------------------------------------------
    $p->add($form = $this->createForm());
    $form->set("id", "race_form");

    $form->add($fitem = new FItem("Race:", 
				  new XTextInput("chosen_race",
						 $race,
						 array("size"=>"4",
						       "maxlength"=>"3",
						       "id"=>"chosen_race",
						       "class"=>"narrow"))));

    // Table of possible races
    $hrows = array(array());
    $brows = array(array());
    foreach ($divisions as $div) {
      $race_nums = array();
      foreach ($this->REGATTA->getUnscoredRaces($div) as $r)
	$race_nums[] = $r->number;
      $hrows[0][] = (string)$div;
      $brows[0][] = Utilities::makeRange($race_nums);
    }
    $fitem->add(XTable::fromArray($brows, $hrows, array('class'=>'narrow')));
    

    // Using?
    $using = (isset($args['finish_using'])) ?
      $args['finish_using'] : "ROT";

    if (!$rotation->isAssigned($race)) {
      unset($this->ACTIONS["ROT"]);
      $using = "TMS";
    }
    
    $form->add(new FItem("Using:", XSelect::fromArray('finish_using', $this->ACTIONS, $using)));
    $form->add(new XSubmitInput("choose_race",
				"Change race"));

    // ------------------------------------------------------------
    // Enter finishes
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Add/edit finish for " . $race));
    $p->add($form = $this->createForm());
    $form->set("id", "finish_form");

    $form->add(new XHiddenInput("race", $race));
    if ($using == "ROT") {
      // ------------------------------------------------------------
      // Rotation-based
      // ------------------------------------------------------------
      $form->add($fitem = new FItem("Enter sail numbers:",
				    $tab = new XQuickTable(array('class'=>'narrow', 'id'=>'finish_table'),
							   array("Sail", ">", "Finish"))));
      $fitem->add(new XMessage("Click on left column to push to right column"));

      // - Fill possible sails and input box
      $pos_sails = $rotation->getSails($race);
      $finishes = $this->REGATTA->getFinishes($race);
      usort($finishes, "Finish::compareEntered");
      foreach ($pos_sails as $i => $aPS) {
	$current_sail = (count($finishes) > 0) ?
	  $rotation->getSail($race, $finishes[$i]->team) : "";
	$tab->addRow(array(new XTD(array('name'=>'pos_sail', 'class'=>'pos_sail','id'=>'pos_sail'), $aPS),
			   new XImg("/inc/img/question.png", "Waiting for input", array("id"=>"check" . $i)),
			   new XTextInput("p" . $i, $current_sail,
					  array("id"=>"sail" . $i,
						"tabindex"=>($i+1),
						"onkeyup"=>"checkSails()",
						"class"=>"small",
						"size"=>"2"))));
      }

      // Submit buttom
      // $form->add(new XReset("reset_finish", "Reset"));
      $form->add(new XSubmitInput("f_places",
				  sprintf("Enter finish for %s", $race),
				  array("id"=>"submitfinish", "tabindex"=>($i+1))));
    }
    else {
      // ------------------------------------------------------------
      // Team lists
      // ------------------------------------------------------------
      $form->add($fitem = new FItem("Enter teams:",
				    $tab = new XQuickTable(array('class'=>'narrow', 'id'=>'finish_table'),
							   array("Team", ">", "Finish"))));
      $fitem->add(new XMessage("Click on left column to push to right column"));

      // - Fill possible teams and select
      $teams = $this->REGATTA->getTeams();
      $team_opts = array("" => "");
      foreach ($teams as $team)
	$team_opts[$team->id] = sprintf("%s %s", $team->school->nick_name, $team->name);
      
      $attrs = array("name"=>"pos_team", "class"=>"pos_sail", "id"=>"pos_team");
      $finishes = $this->REGATTA->getFinishes($race);
      usort($finishes, "Finish::compareEntered");
      foreach ($teams as $i => $team) {
	$name = sprintf("%s %s", $team->school->nick_name, $team->name);
	$attrs["value"] = $team->id;

	$current_team = (count($finishes) > 0) ? $finishes[$i]->team->id : "";
	$tab->addRow(array(new XTD($attrs, $name),
			   new XImg("/inc/img/question.png", "Waiting for input", array("id"=>"check" . $i)),
			   $sel = XSelect::fromArray("p" . $i, $current_team, $team_opts)));
	$sel->set('id', "$team$i");
	$sel->set('tabindex', $i + 1);
	$sel->set('onchange', 'checkTeams()');
      }

      // Submit buttom
      $form->add(new XSubmitInput("f_teams",
				  sprintf("Enter finish for %s", $race),
				  array("id"=>"submitfinish", "tabindex"=>($i+1))));
    }
  }

  /**
   * Helper method processes combined division finishes
   *
   * @param Array $args as usual, the arguments
   */
  private function processCombined(Array $args) {
    $divisions = $this->REGATTA->getDivisions();

    // ------------------------------------------------------------
    // Choose race, can be a number or a full race
    // ------------------------------------------------------------
    if (isset($args['chosen_race'])) {
      try {
	if (is_numeric($args['chosen_race'])) {
	  $therace = $this->REGATTA->getRace($divisions[0], (int)$args['chosen_race']);
	  $args['chosen_race'] = ($therace === null) ? null : (string)$therace;
	}
	else {
	  $race = Race::parse($args['chosen_race']);
	  $therace = $this->REGATTA->getRace($race->division, $race->number);
	  $args['chosen_race'] = ($therace === null) ? null : (string)$therace;
	}
      }
      catch (InvalidArgumentException $e) {
	$mes = sprintf("Invalid race (%s).", $args['chosen_race']);
	Session::pa(new PA($mes, PA::E));
	unset($args['chosen_race']);
      }

      if (!isset($args['finish_using']) ||
	  !in_array($args['finish_using'], array_keys($this->ACTIONS))) {
	$args['finish_using'] = "ROT";
      }

      return $args;
    }

    // ------------------------------------------------------------
    // Enter finish by rotation
    // ------------------------------------------------------------
    $rotation = $this->REGATTA->getRotation();
    if (isset($args['f_places'])) {
      try {
	$race = Race::parse($args['race']);
	$race = $this->REGATTA->getRace($race->division, $race->number);
	if ($race === null)
	  throw new Exception(sprintf("No such race (%s) in this regatta.", $race));
      }
      catch (Exception $e) {
	Session::pa(new PA($e->getMessage(), PA::E));
	unset($args['race']);
	return $args;
      }

      // Get all races and sails:
      // Ascertain that there are as many finishes as there are sails
      // participating in this regatta (every team has a finish). Make
      // associative array of sail numbers => teams
      $teams = $this->REGATTA->getTeams();
      $sails = array();     // alist: sail => Team
      $races = array();     // alist: sail => Race
      $race_ids = array();  // alist: race_id => Race
      $finishes = array();  // alist: race_id => array(Finish)
      foreach ($divisions as $div) {
	$r = $this->REGATTA->getRace($div, $race->number);
	$race_ids[$r->id] = $r;
	foreach ($teams as $t) {
	  $s = $rotation->getSail($r, $t);
	  $sails[$s] = $t;
	  $races[$s] = $r;
	}
      }

      $count = count($sails);
      $now_fmt = "now + %d seconds";
      for ($i = 0; $i < $count; $i++) {

	// Verify
	if (!isset($args["p$i"])) {
	  Session::pa(new PA("Missing team(s).", PA::E));
	  return $args;
	}
	$sail = $args["p$i"];
	// Possible sail
	if (!isset($sails[$sail])) {
	  $mes = sprintf('Sail not in this race (%s).', $sail);
	  Session::pa(new PA($mes, PA::E));
	  return $args;
	}
	$race = $races[$sail];

	$finish = $this->REGATTA->getFinish($race, $sails[$sail]);
	if ($finish === null)
	  $finish = $this->REGATTA->createFinish($race, $sails[$sail]);
	$finish->entered = new DateTime(sprintf($now_fmt, 3 * $i));

	if (!isset($finishes[$race->id]))
	  $finishes[$race->id] = array();
	$finishes[$race->id][] = $finish;
	unset($sails[$sail]);
      }

      // remember: any race from any division should do for combined scoring
      $this->REGATTA->runScore($race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE, $race);

      // Reset
      unset($args['chosen_race']);
      $mes = sprintf("Finishes entered for race %s.", $race->number);
      Session::pa(new PA($mes));
    }

    // ------------------------------------------------------------
    // Enter finish by team
    // ------------------------------------------------------------
    if (isset($args['f_teams'])) {
      try {
	$race = Race::parse($args['race']);
	$race = $this->REGATTA->getRace($race->division, $race->number);
	if ($race == null)
	  throw new Exception(sprintf("No such race in this regatta (%s).", $args['race']));
      }
      catch (Exception $e) {
	Session::pa(new PA($e->getMessage(), Announcemen::ERROR));
	unset($args['race']);
	return $args;
      }

      // Ascertain that each team has a stake in this finish. Make
      // a list of sail numbers => team, race for declaring finish
      // objects later on
      $teams = $this->REGATTA->getTeams();
      $races = array();    // alist: '3a,MIT' => Race
      $sails = array();    // alist: '3a,MIT' => Team
      $race_ids = array(); // alist: race_id  => Race
      $finishes = array(); // alist: race_id  => array(Finish)
      foreach ($divisions as $div) {
	$r = $this->REGATTA->getRace($div, $race->number);
	$race_ids[$r->id] = $r;
	foreach ($this->REGATTA->getTeams() as $team) {
	  $index = sprintf("%s,%s", $div, $team->id);
	  
	  $races[$index] = $r;
	  $sails[$index] = $team;
	}
      }
      
      $count = count($teams) * count($divisions);
      $now_fmt = "now + %d seconds";
      for ($i = 0; $i < $count; $i++) {

	// Verify
	if (!isset($args["p$i"])) {
	  Session::pa(new PA("Missing team(s).", PA::E));
	  return $args;
	}
	$team_id = $args["p$i"];

	// Possible team
	if (!isset($sails[$team_id])) {
	  $mes = sprintf('Invalid team ID (%s).', $team_id);
	  Session::pa(new PA($mes, PA::E));
	  return $args;
	}
	$race = $races[$team_id];
	$finish = $this->REGATTA->getFinish($race, $sails[$team_id]);
	if ($finish === null)
	  $finish = $this->REGATTA->createFinish($race, $sails[$team_id]);
	$finish->entered = new DateTime(sprintf($now_fmt, 3 * $i));

	if (!isset($finishes[$race->id]))
	  $finishes[$race->id] = array();
	$finishes[$race->id][] = $finish;
	unset($sails[$team_id]);
      }
      // remember: any race from any division should do for combined scoring
      $this->REGATTA->runScore($race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE, $race);

      // Reset
      unset($args['chosen_race']);
      $mes = sprintf("Finishes entered for race %s.", $race->number);
      Session::pa(new PA($mes));

      $args['finish_using'] = "TMS";
    }
    
    return $args;
  }


  public function process(Array $args) {

    if ($this->REGATTA->get(Regatta::SCORING) == Regatta::SCORING_COMBINED) {
      return $this->processCombined($args);
    }

    $divisions = $this->REGATTA->getDivisions();
    
    // ------------------------------------------------------------
    // Choose race
    // ------------------------------------------------------------
    if (isset($args['chosen_race'])) {
      try {
	$race = Race::parse($args['chosen_race']);
	$therace = $this->REGATTA->getRace($race->division, $race->number);
	$args['chosen_race'] = ($therace === null) ? null : (string)$therace;
      }
      catch (InvalidArgumentException $e) {
	$mes = sprintf("Invalid race (%s).", $args['chosen_race']);
	Session::pa(new PA($mes, PA::E));
	unset($args['chosen_race']);
      }

      if (!isset($args['finish_using']) ||
	  !in_array($args['finish_using'], array_keys($this->ACTIONS)))
	$args['finish_using'] = "ROT";
      return $args;
    }

    // ------------------------------------------------------------
    // Enter finish by rotation
    // ------------------------------------------------------------
    $rotation = $this->REGATTA->getRotation();
    if (isset($args['f_places'])) {
      try {
	$race = Race::parse($args['race']);
	$race = $this->REGATTA->getRace($race->division, $race->number);
	if ($race == null)
	  throw new Exception(sprintf("No such race in this regatta (%s).", $args['race']));

	$sails = $rotation->getSails($race);
	if (count($sails) == 0)
	  throw new Exception(sprintf("No rotation has been created for the chosen race (%s).", $race));
      }
      catch (Exception $e) {
	Session::pa(new PA($e->getMessage(), PA::E));
	unset($args['race']);
	return $args;
      }
      
      // Ascertain that there are as many finishes as there are sails
      // participating in this regatta (every team has a finish). Make
      // associative array of sail numbers => teams
      $teams = array(); // alist: sail => Team
      foreach ($sails as $sail)
	$teams[$sail] = $rotation->getTeam($race, $sail);

      $count = count($sails);
      $finishes = array();
      $now_fmt = "now + %d seconds";
      for ($i = 0; $i < $count; $i++) {

	// Verify
	if (!isset($args["p$i"])) {
	  Session::pa(new PA("Missing team(s).", PA::E));
	  return $args;
	}
	$sail = $args["p$i"];
	// Possible sail
	if (!isset($teams[$sail])) {
	  $mes = sprintf('Sail not in this race (%s).', $sail);
	  Session::pa(new PA($mes, PA::E));
	  return $args;
	}

	$finish = $this->REGATTA->getFinish($race, $teams[$sail]);
	if ($finish === null)
	  $finish = $this->REGATTA->createFinish($race, $teams[$sail]);
	$finish->entered = new DateTime(sprintf($now_fmt, 3 * $i));
	$finishes[] = $finish;
	unset($sails[$sail]);
      }

      $this->REGATTA->runScore($race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE, $race);

      // Reset
      unset($args['chosen_race']);
      $mes = sprintf("Finishes entered for race %s.", $race);
      Session::pa(new PA($mes));
    }

    // ------------------------------------------------------------
    // Enter finish by team
    // ------------------------------------------------------------
    if (isset($args['f_teams'])) {
      try {
	$race = Race::parse($args['race']);
	$race = $this->REGATTA->getRace($race->division, $race->number);
	if ($race == null)
	  throw new Exception(sprintf("No such race in this regatta (%s).", $args['race']));
      }
      catch (Exception $e) {
	Session::pa(new PA($e->getMessage(), Announcemen::ERROR));
	unset($args['race']);
	return $args;
      }

      // Ascertain that each team has a stake in this finish
      $teams = array();
      foreach ($this->REGATTA->getTeams() as $team)
	$teams[$team->id] = $team;
      
      $count = count($teams);
      $finishes = array();
      $now_fmt = "now + %d seconds";
      for ($i = 0; $i < $count; $i++) {

	// Verify
	if (!isset($args["p$i"])) {
	  Session::pa(new PA("Missing team(s).", PA::E));
	  return $args;
	}
	$team_id = $args["p$i"];
	// Possible team
	if (!isset($teams[$team_id])) {
	  $mes = sprintf('Invalid team ID (%s).', $team_id);
	  Session::pa(new PA($mes, PA::E));
	  return $args;
	}

	$finish = $this->REGATTA->getFinish($race, $teams[$team_id]);
	if ($finish === null)
	  $finish = $this->REGATTA->createFinish($race, $teams[$team_id]);
	$finish->entered = new DateTime(sprintf($now_fmt, 3 * $i));
	$finishes[] = $finish;
	unset($teams[$team_id]);
      }

      $this->REGATTA->runScore($race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE, $race);

      // Reset
      unset($args['chosen_race']);
      $mes = sprintf("Finishes entered for race %s.", $race);
      Session::pa(new PA($mes));

      $args['finish_using'] = "TMS";
    }
    
    return $args;
  }
}
?>
