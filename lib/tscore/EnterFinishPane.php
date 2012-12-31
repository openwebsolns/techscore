<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

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

  /**
   * @var Map penalty options available when entering finishes
   */
  private $pen_opts = array("" => "", Penalty::DNF => Penalty::DNF, Penalty::DNS => Penalty::DNS, Breakdown::BYE => Breakdown::BYE);

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Enter finish", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    // Determine race to display: either as requested or the next
    // unscored race, or the last race
    $race = DB::$V->incRace($args, 'race', $this->REGATTA, null);
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

    $form->add(new FItem("Race:", 
                         new XTextInput('race', $race,
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

    $this->fillFinishesPort($race, ($using == self::ROTATION) ? $rotation : null);
  }

  /**
   * Helper method centralizes display of boat/team selection for
   * entering finishes.
   *
   */
  protected function fillFinishesPort(Race $race, Rotation $rotation = null) {

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

    $form->add(new XP(array(), "Click on left column to push to right column. You may specify DNS/DNF/BYE when entering finishes, or later as a penalty/breakdown using the \"Add Penalty\" menu item."));
    if ($rotation !== null) {
      // ------------------------------------------------------------
      // Rotation-based
      // ------------------------------------------------------------
      $form->add(new FItem("Enter sail numbers:",
                           $tab = new XQuickTable(array('class'=>'narrow', 'id'=>'finish_table'),
                                                  array("Sail", "→", "Finish", "Pen."))));

      // - Fill possible sails and input box
      $pos_sails = ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) ?
        $rotation->getSails($race) :
        $rotation->getCombinedSails($race);
      
      foreach ($pos_sails as $i => $aPS) {
	$current_sail = "";
	$current_pen = null;
	if (count($finishes) > 0) {
          $current_sail = $rotation->getSail($finishes[$i]->race, $finishes[$i]->team);
	  $current_pen = $finishes[$i]->penalty;
	}
        $tab->addRow(array(new XTD(array('name'=>'pos_sail', 'class'=>'pos_sail','id'=>'pos_sail'), $aPS),
                           new XImg("/inc/img/question.png", "Waiting for input", array("id"=>"check" . $i)),
                           new XTextInput("p" . $i, $current_sail,
                                          array("id"=>"sail" . $i,
                                                "tabindex"=>($i+1),
                                                "onkeyup"=>"checkSails()",
                                                "class"=>"small",
                                                "size"=>"2")),
			   XSelect::fromArray('m' . $i, $this->pen_opts, $current_pen)));
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
      $form->add(new FItem("Enter teams:",
                           $tab = new XQuickTable(array('class'=>'narrow', 'id'=>'finish_table'),
                                                  array("Team", "→", "Finish", "Pen."))));
      $i = $this->fillFinishesTable($tab, $race, $finishes);
      $form->add(new XSubmitP('f_teams',
                              sprintf("Enter finish for race %s", $race),
                              array('id'=>'submitfinish', 'tabindex'=>($i+1))));
    }

    // ------------------------------------------------------------
    // Drop finish
    // ------------------------------------------------------------
    if (count($finishes) > 0) {
      $this->PAGE->addContent($p = new XPort("Drop finishes"));
      $p->add(new XP(array(), "To drop the finishes for this race, click the button below. Note that this action is not undoable. All information associated with the finishes will be lost, including penalties and breakdowns that may have been entered."));
      $p->add($f = $this->createForm());
      $f->add($p = new XSubmitP('delete-finishes', "Delete"));
      $p->add(new XHiddenInput('race', $race));
    }
  }

  public function process(Array $args) {
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
      $races = array();
      if (isset($args['f_teams'])) {
        $t = ($this->REGATTA->scoring == Regatta::SCORING_TEAM) ?
          $this->REGATTA->getRaceTeams($race) :
          $this->REGATTA->getTeams();

        $args['finish_using'] = self::TEAMS;
        $opts = array();
        $this->fillTeamOpts($opts, $teams, $races, $t, $race);
      }
      else {
        $sails = ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) ?
          $rotation->getSails($race) :
          $rotation->getCombinedSails($race);
        if (count($sails) == 0)
          throw new SoterException(sprintf("No rotation has been created for the chosen race (%s).", $race));

        foreach ($sails as $sail) {
          $teams[(string)$sail] = $sail->team;
          $races[(string)$sail] = $sail->race;
        }
        unset($sails);
      }

      $count = count($teams);
      $finishes = array();
      $time = new DateTime();
      $intv = new DateInterval('P0DT3S');
      for ($i = 0; $i < $count; $i++) {
        $id = DB::$V->reqKey($args, "p$i", $teams, "Missing team in position " . ($i + 1) . ".");
	$pen = DB::$V->incKey($args, "m$i", $this->pen_opts, "");
	$mod = null;
	if ($pen == Penalty::DNS || $pen == Penalty::DNF)
	  $mod = new Penalty($pen);
	elseif ($pen == Breakdown::BYE)
	  $mod = new Breakdown($pen);

        $finish = $this->REGATTA->getFinish($races[$id], $teams[$id]);
        if ($finish === null)
          $finish = $this->REGATTA->createFinish($races[$id], $teams[$id]);
        $finish->setModifier($mod); // reset score
        $finish->entered = clone($time);
        $finishes[] = $finish;
        unset($teams[$id]);
        $time->add($intv);
      }

      $this->REGATTA->commitFinishes($finishes);
      $this->REGATTA->runScore($race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE, $race);

      // Update races's scored_day as needed
      $start = $this->REGATTA->start_time;
      $start->setTime(0, 0);
      $now = new DateTime();
      $now->setTime(0, 0);
      $duration = $now->diff($start)->days + 1;
      foreach ($races as $race) {
        if ($race->scored_day === null) {
          $race->scored_day = $duration;
          DB::set($race);
        }
      }

      // Reset
      $mes = sprintf("Finishes entered for race %s.", $race);
      Session::pa(new PA($mes));
      $this->redirect('finishes');
    }

    // ------------------------------------------------------------
    // Delete finishes
    // ------------------------------------------------------------
    if (isset($args['delete-finishes'])) {
      $race = DB::$V->reqRace($args, 'race', $this->REGATTA, "Invalid or missing race to drop.");
      $this->REGATTA->dropFinishes($race);
      Session::pa(new PA(sprintf("Removed finishes for race %s.", $race)));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
    }

    return array();
  }

  /**
   * Helper method will fill the table with the selects, using the
   * list of finishes provided.
   *
   * @param Array:Finish the current set of finishes
   * @return int the total number of options added
   */
  private function fillFinishesTable(XQuickTable $tab, Race $race, $finishes) {
    $teams = $this->REGATTA->getTeams();
    $team_opts = array("" => "");
    $attrs = array("name"=>"pos_team", "class"=>"pos_sail left", "id"=>"pos_team");
    if ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) {
      $t = $r = array();
      $this->fillTeamOpts($team_opts, $t, $r, $teams, $race);

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
      // Combined and team scoring
      $divisions = $this->REGATTA->getDivisions();
      if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
        $teams = $this->REGATTA->getRaceTeams($race);

      $t = $r = array();
      $this->fillTeamOpts($team_opts, $t, $r, $teams, $race, $divisions);

      $i = 0;
      foreach ($divisions as $div) {
        foreach ($teams as $team) {
          $attrs['value'] = sprintf('%s,%s', $div, $team->id);
          $name = $team_opts[$attrs['value']];

	  $current_team = "";
	  $current_pen = null;
	  if (count($finishes) > 0) {
	    $current_team = sprintf("%s,%s", $finishes[$i]->race->division, $finishes[$i]->team->id);
	    $current_pen = $finishes[$i]->penalty;
	  }

          $tab->addRow(array(new XTD($attrs, $name),
                             new XImg("/inc/img/question.png", "Waiting for input",  array("id"=>"check" . $i)),
                             $sel = XSelect::fromArray("p" . $i, $team_opts, $current_team),
			     XSelect::fromArray('m' . $i, $this->pen_opts, $current_pen)));
          $sel->set('id', "team$i");
          $sel->set('tabindex', $i + 1);
          $sel->set('onchange', 'checkTeams()');
          $i++;
        }
      }
      return $i;
    }
  }

  /**
   * Helper method: fills assoc array of options, suitable for
   * XSelect elements.
   *
   * This method takes into account the regatta scoring type
   *
   * @param Array $team_opts the map to fill with team elements
   * @param Array $tms the map of teams to fill in
   * @param Array $rac the map of races to fill in
   * @param Array $teams the list of teams whose options to fill in
   * @param Race $race the template race to use
   * @param Array $divisions the list of divisions (required for
   * non-standard scoring). If missing or empty, query the $REGATTA
   * for its list of divisions.
   */
  private function fillTeamOpts(Array &$team_opts, Array &$tms, Array &$rac, $teams, Race $race, Array $divisions = array()) {
    if ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) {
      foreach ($teams as $team) {
        $team_opts[$team->id] = sprintf("%s %s", $team->school->nick_name, $team->name);
        $tms[$team->id] = $team;
        $rac[$team->id] = $race;
      }
      return;
    }
    if (count($divisions) == 0)
      $divisions = $this->REGATTA->getDivisions();

    // echo "<pre>"; print_r($race); "</pre>"; exit;

    foreach ($divisions as $div) {
      foreach ($teams as $team) {
        $id = sprintf("%s,%s", $div, $team->id);
        $label = ($this->REGATTA->scoring == Regatta::SCORING_TEAM) ? $div->getLevel() : $div;
        $team_opts[$id] = sprintf("%s: %s %s", $label, $team->school->nick_name, $team->name);

        $tms[$id] = $team;
        $rac[$id] = $this->REGATTA->getRace($div, $race->number);
      }
    }
  }
}
?>
