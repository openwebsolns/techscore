<?php
use \metrics\TSMetric;
use \ui\EnterFinishesWidget;

/**
 * (Re)Enters finishes
 *
 * 2010-02-25: Allow entering combined divisions. Of course, deal with
 * the team name entry as well as rotation
 *
 * 2013-07-15: Split into a two-step process: one for choosing the
 * race, the other for entering the race.
 *
 * 2015-11-02: Simplify the input by expecting the following structure:
 *
 *   - finishes : [
 *       {
 *         entry: <entry>
 *         modifier: <BYE>
 *       }
 *     ]
 *
 *  where <entry> is <RACE_ID>,<TEAM_ID>.
 *
 * This applies regardless of the type of regatta.
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

  const SUBMIT_FINISHES = 'commit-finishes';
  const SUBMIT_DELETE = 'delete-finishes';

  protected $ACTIONS = array(
    self::ROTATION => "Sail numbers from rotation",
    self::TEAMS => "Team names"
  );

  private static $METRICS = array(
    self::ROTATION => 'enter_finish_using_rotation',
    self::TEAMS => 'enter_finish_using_teams',
  );

  /**
   * @var Map penalty options available when entering finishes
   */
  protected $pen_opts = array("" => "", Penalty::DNF => Penalty::DNF, Penalty::DNS => Penalty::DNS, Breakdown::BYE => Breakdown::BYE);

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Enter finish", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    // Determine race to display as requested
    $race = null;
    if (array_key_exists('race', $args)) {
      $race = DB::$V->incRace($args, 'race', $this->REGATTA, null);
      if ($race === null) {
        Session::warn("Invalid race requested. Please try again.");
      }
      elseif (!$this->REGATTA->isRaceScorable($race)) {
        Session::warn(sprintf("Race %d cannot be scored until every team in the race is known.", $race->number));
        $race = null;
      }
    }

    // ------------------------------------------------------------
    // 1. Choose race
    // ------------------------------------------------------------
    if ($race == null) {
      $this->fillChooseRace($args);
      return;
    }

    // ------------------------------------------------------------
    // 2. Enter finishes
    // ------------------------------------------------------------
    $using = DB::$V->incKey($args, 'finish_using', $this->ACTIONS, self::ROTATION);
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/finish-inputs.js?v=2', null, array('defer'=>'defer', 'async'=>'async')));

    $this->fillFinishesPort($race, ($using == self::ROTATION) ? $this->REGATTA->getRotationManager() : null);
  }

  protected function fillChooseRace(Array $args) {
    $using = DB::$V->incKey($args, 'finish_using', $this->ACTIONS, self::ROTATION);

    $this->PAGE->addContent($p = new XPort("Choose race"));
    $p->add($form = $this->createForm(XForm::GET));
    $form->set("id", "race_form");

    $form->add(new FReqItem("Race:", $sel = new XSelect('race')));
    $scored = array();
    $unscored = array();
    $races = ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) ?
      $this->REGATTA->getRaces() :
      $this->REGATTA->getRaces(Division::A());
    foreach ($races as $race) {
      $option = new XOption($race, array(), $race);
      if ($this->REGATTA->hasFinishes($race)) {
        $scored[] = $option;
      }
      else {
        $unscored[] = $option;
      }
    }
    $sel->add(new XOption("", array(), ""));
    if (count($unscored) > 0) {
      $sel->add(new XOptionGroup("Unscored races", array(), $unscored));
    }
    if (count($scored) > 0) {
      $sel->add(new XOptionGroup("Scored races", array('class'=>'has-raced'), $scored));
    }

    $rotationManager = $this->REGATTA->getRotationManager();
    if ($rotationManager->isAssigned()) {
      $form->add(new FReqItem("Using:", XSelect::fromArray('finish_using', $this->ACTIONS, $using)));
    }
    $form->add(new XSubmitP("go", "Enter finishes →"));
  }

  /**
   * Helper method centralizes display of boat/team selection for
   * entering finishes.
   *
   */
  protected function fillFinishesPort(Race $race, RotationManager $rotation = null) {

    // ------------------------------------------------------------
    // Enter finishes
    // ------------------------------------------------------------
    $title = sprintf("Add/edit finish for race %s", $race);
    $teams = $this->REGATTA->getRaceTeams($race);
    if (count($teams) == 2) {
      $title .= sprintf(" (%s vs. %s)", $teams[0], $teams[1]);
    }
    $this->PAGE->addContent($p = new XPort($title));
    $p->add($form = $this->createForm());

    $finishes = ($this->REGATTA->getEffectiveDivisionCount() == 1) ?
      $this->REGATTA->getCombinedFinishes($race) :
      $this->REGATTA->getFinishes($race);

    $form->add(new XP(array(), "Enter teams in the order they crossed the finish line. You may specify DNS/DNF/BYE when entering finishes now, or later as a penalty/breakdown using the \"Add Penalty\" menu item."));

    if ($rotation !== null && $rotation->isAssigned($race)) {
      $options = $this->getRotationBasedOptions($race, $rotation);
      $label = "Sails";
      $metric = self::$METRICS[self::ROTATION];
    }
    else {
      $options = $this->getTeamBasedOptions($race);
      $label = "Teams";
      $metric = self::$METRICS[self::TEAMS];
    }

    $widget = new EnterFinishesWidget($label, $options);
    for ($i = 0; $i < count($options); $i++) {
      $chosenOption = null;
      $chosenType = null;
      if ($i < count($finishes)) {
        $chosenOption = $this->getTeamOptionKey(
          $finishes[$i]->race,
          $finishes[$i]->team
        );
        $chosenType = (string) $finishes[$i]->getModifier();
      }
      $widget->addPlace($chosenOption, $chosenType);
    }

    $widget->set('id', 'finishes-widget');
    $form->add($widget);

    // Submit button
    $xp = new XSubmitP(
      self::SUBMIT_FINISHES,
      sprintf("Enter finish for race %s", $race),
      array("id"=>"submitfinish", "tabindex" => count($options))
    );
    $xp->add(" ");
    $xp->add(new XA($this->link('finishes'), "Cancel"));
    $form->add($xp);
    $this->fillRaceObservation($form, $race);
    TSMetric::publish($metric);

    // ------------------------------------------------------------
    // Drop finish
    // ------------------------------------------------------------
    if (count($finishes) > 0) {
      $this->PAGE->addContent($p = new XPort("Drop finishes"));
      $p->add(new XP(array(), "To drop the finishes for this race, click the button below. Note that this action is not undoable. All information associated with the finishes will be lost, including penalties and breakdowns that may have been entered."));
      $p->add($f = $this->createForm());
      $f->add(new XSubmitP(self::SUBMIT_DELETE, "Delete", array(), true));
      $f->add(new XHiddenInput('race', $race));
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Enter finish by rotation/teams
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_FINISHES, $args)) {

      // Loop through the list of 'finishes' creating a list of races
      // involved, and an ordered list of (valid) Finish objects.
      $races = array();
      $allFinishes = array();
      $finishesByRace = array();
      $finishInputs = DB::$V->reqList($args, 'finishes', null, "No finishes provided.");
      $time = new DateTime();
      $interval = new DateInterval('P0DT3S');
      foreach ($finishInputs as $i => $finishInput) {
        $entry = DB::$V->reqString($finishInput, 'entry', 1, 100, "Missing entry for place: " . ($i + 1));
        $parts = explode(',', $entry);
        if (count($parts) != 2) {
          throw new SoterException("Entry is missing either race or team for place: " . ($i + 1));
        }

        $race = $this->REGATTA->getRaceById($parts[0]);
        if ($race === null) {
          throw new SoterException("Invalid race provided for place: " . ($i + 1));
        }
        $team = $this->REGATTA->getTeam($parts[1]);
        if ($team === null) {
          throw new SoterException("Invalid team provided for place: " . ($i + 1));
        }

        $raceKey = (string) $race;
        $races[$raceKey] = $race;
        if (!array_key_exists($raceKey, $finishesByRace)) {
          $finishesByRace[$raceKey] = array();
        }

        $finishKey = $this->getTeamOptionKey($race, $team);
        if (array_key_exists($finishKey, $finishesByRace[$raceKey])) {
          throw new SoterException(sprintf("Duplicate finish provided for team %s.", $team));
        }

        $finish = $this->createFinishFromArgs($race, $team, $finishInput);
        $finish->entered = clone($time);
        $time->add($interval);
        $finishesByRace[$raceKey][$finishKey] = $finish;
        $allFinishes[] = $finish;
      }

      // Verify that every race provided is complete
      foreach ($finishesByRace as $raceKey => $finishes) {
        $options = $this->getTeamBasedOptions($races[$raceKey]);
        if (count($options) != count($finishes)) {
          throw new SoterException(sprintf("Incomplete list of finishes provided for race %s.", $raceKey));
        }
        foreach ($options as $key => $value) {
          if (!array_key_exists($key, $finishes)) {
            throw new SoterException(sprintf("Missing finish for team %s in race %s.", $value, $raceKey));
          }
        }
      }

      // Commit
      $this->REGATTA->commitFinishes($allFinishes);
      if (count($races) == 1) {
        foreach ($races as $race) {
          $this->REGATTA->runScore($race);
        }
      }
      else {
        $this->REGATTA->doScore();
      }

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

        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE, $race);
        Session::info($this->getSessionMessage($race));
      }

      // Observation?
      $obs = DB::$V->incString($args, 'observation', 1, 16000, null);
      if ($obs !== null) {
        foreach ($races as $race) {
          $note = new Note();
          $note->noted_at = DB::T(DB::NOW);
          $note->observation = $obs;
          $note->observer = DB::$V->incString($args, 'observer', 1, 51, null);
          $note->race = $race;
          DB::set($note);
          Session::info(array("Added note for race $race. ", new XA($this->link('notes'), "Edit notes"), "."));
        }
      }

      $this->redirect('finishes');
    }

    // ------------------------------------------------------------
    // Delete finishes
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_DELETE, $args)) {
      $race = DB::$V->reqRace($args, 'race', $this->REGATTA, "Invalid or missing race to drop.");
      $this->REGATTA->dropFinishes($race);
      Session::pa(new PA(sprintf("Removed finishes for race %s.", $race)));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
    }
  }

  /**
   * Create an assoc. array of teams that are participating in given race.
   *
   * Key = "<DIV>,<TEAM_ID>". Value = "Team Name".
   *
   * @param Race $race the race in question.
   * @return Array taking regatta scoring type into consideration.
   */
  private function getTeamBasedOptions(Race $race) {
    $teams = $this->sortTeamsByDisplayName($this->REGATTA->getRaceTeams($race));
    $options = array();
    $divisions = array($race->division);
    if ($this->REGATTA->getEffectiveDivisionCount() == 1) {
      $divisions = $this->REGATTA->getDivisions();
    }

    $isTeam = $this->REGATTA->scoring == Regatta::SCORING_TEAM;
    $isCombined = $this->REGATTA->scoring == Regatta::SCORING_COMBINED;
    foreach ($divisions as $division) {
      $r = $this->REGATTA->getRace($division, $race->number);
      foreach ($teams as $team) {
        $id = $this->getTeamOptionKey($r, $team);

        $label = $team;
        if ($isTeam) {
          $label = sprintf('%s: %s', $division->getLevel(), $label);
        }
        elseif ($isCombined) {
          $label = sprintf('%s: %s', $division, $label);
        }

        $options[$id] = $label;
      }
    }

    return $options;
  }

  private function sortTeamsByDisplayName($teams) {
    $sorted = array();
    foreach ($teams as $team) {
      $sorted[] = $team;
    }
    usort($sorted, function($t1, $t2) {
        return strcmp((string) $t1, (string) $t2);
      });
    return $sorted;
  }

  /**
   * Create an assoc. array of sails participating in given race.
   *
   * Key = "<sail #>". Value = "<sail #>".
   *
   * @param Race $race the race in question.
   * @param RotationManager $rotation the rotation object to use.
   * @return Array taking regatta scoring type into consideration.
   */
  private function getRotationBasedOptions(Race $race, RotationManager $rotation) {
    $pos_sails = ($this->REGATTA->getEffectiveDivisionCount() == 1) ?
      $rotation->getCombinedSails($race) :
      $rotation->getSails($race);

    $options = array();
    foreach ($pos_sails as $i => $sail) {
      $key = $this->getTeamOptionKey($sail->race, $sail->team);
      $options[$key] = $sail->sail;
    }
    return $options;
  }

  private function getTeamOptionKey(Race $race, Team $team) {
    return sprintf('%s,%s', $race->id, $team->id);
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
   *    non-standard scoring). If missing or empty, query the $REGATTA
   *    for its list of divisions.
   */
  private function fillTeamOpts(Array &$team_opts, Array &$tms, Array &$rac, $teams, Race $race, Array $divisions = array()) {
    if ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) {
      foreach ($teams as $team) {
        $team_opts[$team->id] = (string)$team;
        $tms[$team->id] = $team;
        $rac[$team->id] = $race;
      }
      return;
    }

    if (count($divisions) == 0) {
      $divisions = $this->REGATTA->getDivisions();
    }

    foreach ($divisions as $div) {
      foreach ($teams as $team) {
        $id = sprintf("%s,%s", $div, $team->id);
        $label = ($this->REGATTA->scoring == Regatta::SCORING_TEAM) ? $div->getLevel() : $div;
        $team_opts[$id] = sprintf("%s: %s", $label, $team);

        $tms[$id] = $team;
        $rac[$id] = $this->REGATTA->getRace($div, $race->number);
      }
    }
  }

  private function fillRaceObservation(XForm $form, Race $race) {
    if ($this->REGATTA->scoring == Regatta::SCORING_TEAM) {
      $form->add(new FItem("Notes:", new XTextArea('observation', "")));
      $form->add(new FItem("Observer:", new XTextInput('observer', "")));
    }
  }

  private function createFinishFromArgs(Race $race, Team $team, Array $args) {
    $modifier = null;
    $modifierString = DB::$V->incString($args, 'modifier', 1);
    if ($modifierString == Penalty::DNS || $modifierString == Penalty::DNF) {
      $modifier = new Penalty($modifierString);
    }
    elseif ($modifierString == Breakdown::BYE) {
      $modifier = new Breakdown($modifierString);
    }
    elseif ($modifierString !== null) {
      throw new SoterException(sprintf("Unknown modifier \"%s\" for team %s.", $modifierString, $team));
    }

    $finish = $this->REGATTA->getFinish($race, $team);
    if ($finish === null) {
      $finish = $this->REGATTA->createFinish($race, $team);
    }
    $finish->setModifier($modifier); // reset score
    $finish->team = $team;
    $finish->race = $race;
    return $finish;
  }

  protected function getSessionMessage(Race $race) {
    return sprintf("Finishes entered for race %s.", $race);
  }
}
