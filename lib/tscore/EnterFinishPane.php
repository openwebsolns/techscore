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

  /**
   * @const String the action to use for entering finishes
   */
  const ROTATION = 'ROT';
  const TEAMS = 'TMS';

  private $ACTIONS = array(self::ROTATION => "Sail numbers from rotation",
			   self::TEAMS => "Team names");

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Enter finish", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    // Determine race to display: either as requested or the next
    // unscored race, or the last race
    $race = DB::$V->incRace($args, 'chosen_race', $this->REGATTA, null);
    if ($race == null) {
      $races = $this->REGATTA->getUnscoredRaces();
      if (count($races) > 0)
	$race = $races[0];
    }
    if ($race == null) {
      $race = $this->REGATTA->getLastScoredRace();
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
    $p->add($form = $this->createForm(XForm::GET));
    $form->set("id", "race_form");

    $form->add($fitem = new FItem("Race:", 
				  new XTextInput("chosen_race",
						 $race,
						 array("size"=>"4",
						       "maxlength"=>"3",
						       "id"=>"chosen_race",
						       "class"=>"narrow"))));
    // Using?
    $using = (isset($args['finish_using'])) ?
      $args['finish_using'] : self::ROTATION;

    if (!$rotation->isAssigned($race)) {
      unset($this->ACTIONS[self::ROTATION]);
      $using = self::TEAMS;
    }
    
    $form->add(new FItem("Using:", XSelect::fromArray('finish_using', $this->ACTIONS, $using)));
    $form->add(new XSubmitP("choose_race", "Change race"));

    // ------------------------------------------------------------
    // Enter finishes
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Add/edit finish for race " . $race));
    $p->add($form = $this->createForm());
    $form->set("id", "finish_form");

    $form->add(new XHiddenInput("race", $race));
    $finishes = ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) ?
      $this->REGATTA->getFinishes($race) :
      $this->REGATTA->getCombinedFinishes($race);

    if ($using == self::ROTATION) {
      // ------------------------------------------------------------
      // Rotation-based
      // ------------------------------------------------------------
      $form->add(new XP(array(), "Click on left column to push to right column"));
      $form->add(new FItem("Enter sail numbers:",
			   $tab = new XQuickTable(array('class'=>'narrow', 'id'=>'finish_table'),
						  array("Sail", "→", "Finish"))));

      // - Fill possible sails and input box
      $pos_sails = $rotation->getSails($race);
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
      $form->add(new XSubmitP("f_places",
			      sprintf("Enter finish for race %s", $race),
			      array("id"=>"submitfinish", "tabindex"=>($i+1))));
    }
    else {
      // ------------------------------------------------------------
      // Team lists
      // ------------------------------------------------------------
      $form->add(new XP(array(), "Click on left column to push to right column"));
      $form->add(new FItem("Enter teams:",
			   $tab = new XQuickTable(array('class'=>'narrow', 'id'=>'finish_table'),
						  array("Team", "→", "Finish"))));
      $i = $this->fillFinishesTable($tab, $finishes);
      $form->add(new XSubmitP('f_teams',
			      sprintf("Enter finish for race %s", $race),
			      array('id'=>'submitfinish', 'tabindex'=>($i+1))));
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
      if (DB::$V->hasInt($num, $args, 'chosen_race', 1, 101, "Invalid race number provided.")) {
	if (($therace = $this->REGATTA->getRace($divisions[0], $num)) === null)
	  throw new SoterException("Invalid race chosen.");
      }
      else
	$therace = DB::$V->reqRace($args, 'chosen_race', $this->REGATTA, "Invalid race provided.");
      $args['chosen_race'] = (string)$therace;
      $args['finish_using'] = DB::$V->incKey($args, 'finish_using', $this->ACTIONS, self::ROTATION);
      return $args;
    }

    // ------------------------------------------------------------
    // Enter finish by rotation/teams
    // ------------------------------------------------------------
    $rotation = $this->REGATTA->getRotation();
    if (isset($args['f_places']) || isset($args['f_teams'])) {
      $therace = DB::$V->reqRace($args, 'race', $this->REGATTA, "No such race in this regatta.");

      // Ascertain that there are as many finishes as there are sails
      // participating in this regatta (every team has a finish). Make
      // associative array of sail numbers => teams
      $races = array();
      $teams = array();
      if (isset($args['f_teams'])) {
	$args['finish_using'] = self::TEAMS;
	foreach ($this->REGATTA->getDivisions() as $div) {
	  if (($race = $this->REGATTA->getRace($div, $therace->number)) === null)
	    throw new SoterException("This regatta is not correctly setup for combined division scoring.");
	  foreach ($this->REGATTA->getTeams() as $team) {
	    $index = sprintf('%s,%s', $div, $team->id);
	    $races[$index] = $race;
	    $teams[$index] = $team;
	  }
	}
      }
      else {
	foreach ($this->REGATTA->getDivisions() as $div) {
	  if (($race = $this->REGATTA->getRace($div, $therace->number)) === null)
	    throw new SoterException("This regatta is not correctly setup for combined division scoring.");
	  foreach ($rotation->getSails($race) as $sail) {
	    $races[(string)$sail] = $race;
	    $teams[(string)$sail] = $sail->team;
	  }
	}
      }

      $count = count($teams);
      $finishes = array();
      $time = new DateTime();
      $intv = new DateInterval('P0DT3S');
      for ($i = 0; $i < $count; $i++) {
	$id = DB::$V->reqKey($args, "p$i", $teams, "Missing at least one team.");
	$finish = $this->REGATTA->getFinish($races[$id], $teams[$id]);
	if ($finish === null)
	  $finish = $this->REGATTA->createFinish($races[$id], $teams[$id]);
	$finish->entered = clone($time);
	$finishes[] = $finish;
	unset($teams[$id]);
	$time->add($intv);
      }

      $this->REGATTA->commitFinishes($finishes);
      $this->REGATTA->runScore($therace);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE, $race);

      // Reset
      unset($args['chosen_race']);
      Session::pa(new PA(sprintf("Finishes entered for race %s.", $race->number)));
    }
    return $args;
  }


  public function process(Array $args) {
    if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED)
      return $this->processCombined($args);

    $divisions = $this->REGATTA->getDivisions();
    
    // ------------------------------------------------------------
    // Enter finish by rotation/teams
    // ------------------------------------------------------------
    $rotation = $this->REGATTA->getRotation();
    if (isset($args['f_places']) || isset($args['f_teams'])) {
      $race = DB::$V->reqRace($args, 'race', $this->REGATTA, "No such race in this regatta.");

      // Ascertain that there are as many finishes as there are sails
      // participating in this regatta (every team has a finish). Make
      // associative array of sail numbers => teams
      $teams = array();
      if (isset($args['f_teams'])) {
	$args['finish_using'] = self::TEAMS;
	foreach ($this->REGATTA->getTeams() as $team)
	  $teams[$team->id] = $team;
      }
      else {
	$sails = $rotation->getSails($race);
	if (count($sails) == 0)
	  throw new SoterException(sprintf("No rotation has been created for the chosen race (%s).", $race));
      
	foreach ($sails as $sail)
	  $teams[(string)$sail] = $sail->team;
	unset($sails);
      }

      $count = count($teams);
      $finishes = array();
      $time = new DateTime();
      $intv = new DateInterval('P0DT3S');
      for ($i = 0; $i < $count; $i++) {
	$id = DB::$V->reqKey($args, "p$i", $teams, "Missing at least one team.");
	$finish = $this->REGATTA->getFinish($race, $teams[$id]);
	if ($finish === null)
	  $finish = $this->REGATTA->createFinish($race, $teams[$id]);
	$finish->entered = clone($time);
	$finishes[] = $finish;
	unset($teams[$id]);
	$time->add($intv);
      }
      $this->REGATTA->commitFinishes($finishes);
      $this->REGATTA->runScore($race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE, $race);

      // Reset
      unset($args['chosen_race']);
      $mes = sprintf("Finishes entered for race %s.", $race);
      Session::pa(new PA($mes));
    }
    
    return $args;
  }

  /**
   * Helper method will fill the table with the selects, using the
   * list of finishes provided.
   *
   * @param Array:Finish the current set of finishes
   * @return int the total number of options added
   */
  private function fillFinishesTable(XQuickTable $tab, $finishes) {
    $teams = $this->REGATTA->getTeams();
    $team_opts = array("" => "");
    $attrs = array("name"=>"pos_team", "class"=>"pos_sail", "id"=>"pos_team");
    if ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) {
      foreach ($teams as $team)
	$team_opts[$team->id] = sprintf("%s %s", $team->school->nick_name, $team->name);

      foreach ($teams as $i => $team) {
	$attrs['value'] = $team->id;

	$current_team = (count($finishes) > 0) ? $finishes[$i]->team->id : "";
	$tab->addRow(array(new XTD($attrs, $team_opts[$team->id]),
			   new XImg("/inc/img/question.png", "Waiting for input", array("id"=>"check" . $i)),
			   $sel = XSelect::fromArray("p" . $i, $team_opts, $current_team)));
	$sel->set('id', "team$i");
	$sel->set('tabindex', $i + 1);
	$sel->set('onchange', 'checkTeams()');
      }
      return $i;
    }
    else {
      // Combined scoring
      $divisions = $this->REGATTA->getDivisions();
      foreach ($divisions as $div) {
	foreach ($teams as $team) {
	  $team_opts[sprintf("%s,%s", $div, $team->id)] = sprintf("%s: %s %s",
								  $div,
								  $team->school->nick_name,
								  $team->name);
	}
      }
      $i = 0;
      foreach ($divisions as $div) {
	foreach ($teams as $team) {
	  $attrs['value'] = sprintf('%s,%s', $div, $team->id);
	  $name = $team_opts[$attrs['value']];

	  $current_team = (count($finishes) > 0) ?
	    sprintf("%s,%s", $finishes[$i]->race->division, $finishes[$i]->team->id) : "";
	  $tab->addRow(array(new XTD($attrs, $name),
			     new XImg("/inc/img/question.png", "Waiting for input",  array("id"=>"check" . $i)),
			     $sel = XSelect::fromArray("p" . $i, $team_opts, $current_team)));
	  $sel->set('id', "team$i");
	  $sel->set('tabindex', $i + 1);
	  $sel->set('onchange', 'checkTeams()');
	  $i++;
	}
      }
      return $i;
    }
  }
}
?>
