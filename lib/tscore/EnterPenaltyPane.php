<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Add, edit, and display individual penalties
 *
 * 2011-02-09: Allow for multiple penalty entry at a time
 *
 * @author Dayan Paez
 * @version 2010-01-25
 */
class EnterPenaltyPane extends AbstractPane {

  /**
   * @var Array allowed penalties/breakdowns
   */
  protected $penalties;
  protected $breakdowns;

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Add penalty", $user, $reg);
    $this->penalties = Penalty::getList();
    $this->breakdowns = Breakdown::getList();
  }

  protected function fillHTML(Array $args) {

    $finished_races = $this->REGATTA->getScoredRaces();
    if (count($finished_races) == 0) {
      $this->PAGE->addContent($p = new XPort("No finishes entered"));
      $p->add(new XP(array('class'=>'message'), "There are no finishes for the current regatta to which assign penalties."));
      return;
    }

    // Check for race by ID
    if (DB::$V->hasID($theRace, $args, 'race_id', DB::$RACE)) {
      if ($theRace->regatta != $this->REGATTA || count($this->REGATTA->getFinishes($theRace)) == 0) {
        $this->PAGE->addContent(new XP(array('class'=>'warning'), "Invalid race ID provided. Using latest scored race instead."));
        $theRace = null;
      }
    }
    elseif (DB::$V->hasRace($theRace, $args, 'race', $this->REGATTA)) {
      if (count($this->REGATTA->getFinishes($theRace)) == 0) {
        $this->PAGE->addContent(new XP(array('class'=>'warning'), "Invalid race chosen ($theRace). Using latest scored race instead."));
        $theRace = null;
      }
    }
    if ($theRace === null)
      $theRace = $finished_races[count($finished_races) - 1];

    $finishes = ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) ?
      $this->REGATTA->getFinishes($theRace) :
      $this->REGATTA->getCombinedFinishes($theRace);

    $type = DB::$V->incKey($args, 'type', $this->penalties, null);
    $type = DB::$V->incKey($args, 'type', $this->breakdowns, $type);
    if (isset($args['type']) && $type === null)
      $this->PAGE->addContent(new XP(array('class'=>'warning'), "Invalid type chosen. Please choose again."));
    if ($type == null) {

      // ------------------------------------------------------------
      // 1. Chosen race
      // ------------------------------------------------------------
      $this->PAGE->addContent($p = new XPort("1. Individual penalties and breakdowns"));
      $p->add($form = $this->createForm(XForm::GET));

      $form->add(new FItem("Possible races:", $this->getRaceTable()));
      $form->add(new FReqItem("Race:", $this->newRaceInput('race', $theRace)));

      $this->fillAlternateRaceSelection($form);

      // Penalty type
      $form->add(new FReqItem("Penalty type:", XSelect::fromArray('type', array("Penalties" => $this->penalties,
                                                                                "Breakdowns" => $this->breakdowns))));
      // Submit
      $form->add(new XSubmitP('c_race', "Next →"));
    }
    else {
      $rotation = $this->REGATTA->getRotation();

      // ------------------------------------------------------------
      // 2. Penalty details
      // ------------------------------------------------------------
      $title = sprintf("2. %s in race %s", $type, $theRace);
      $this->PAGE->addContent($p = new XPort($title));
      $p->add($form = $this->createForm());
      $form->add(new FReqItem("Team:", $f_sel = new XSelectM("finish[]", array('size'=>10))));

      $bkds = Breakdown::getList();
      foreach ($finishes as $fin) {
        if ($this->canHaveModifier($fin, $type)) {
          $sail = (string)$rotation->getSail($fin->race, $fin->team);
          if (strlen($sail) > 0)
            $sail = sprintf("(Sail: %4s) ", $sail);
          $team = $fin->team;
          if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED)
            $team = sprintf('%s: %s', $fin->race->division, $fin->team);
          elseif ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
            $team = sprintf('%s: %s', $fin->race->division->getLevel(), $fin->team);
          $f_sel->add(new FOption($fin->id, $sail . $team));
        }
      }

      // - comments
      $form->add(new FItem("Comments:",
                           new XTextArea("p_comments", "",
                                         array("rows"=>"2",
                                               "cols"=>"50"))));
      $this->fillPenaltyScheme($form, $type);

      // Submit
      $form->add(new XP(array('class'=>'p-submit'),
                        array(new XA($this->link('penalty'), "← Start over"), " ",
                              new XHiddenInput('type', $type),
                              new XSubmitInput('p_submit', "Enter $type"))));

      // FAQ's
      $this->PAGE->addContent($p = new XPort("FAQ"));
      $fname = sprintf("%s/faq/penalty.html", dirname(__FILE__));
      $p->add(new XRawText(file_get_contents($fname)));
    }
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Enter penalty
    // ------------------------------------------------------------
    if (isset($args['p_submit']) ) {
      // Validate input
      $fins = DB::$V->reqList($args, 'finish', null, "Must submit a list of finishes.");
      $finishes = array();
      $teams = array();
      foreach ($fins as $i => $f) {
        $finish = DB::$V->reqID($fins, $i, DB::$FINISH, "Invalid finish provided.");
        $finishes[] = $finish;
        $teams[] = $finish->team;
      }
      if (count($finishes) == 0)
        throw new SoterException("No finishes for penalty/breakdown.");

      $thePen  = $args['type'];
      if (!isset($this->penalties[$thePen]) && !isset($this->breakdowns[$thePen]))
        throw new SoterException("Invalid penalty/breakdown type.");

      $theComm = DB::$V->incString($args, 'p_comments', 1, 16000, null);

      // Get amount, checkbox has preference
      $theAmount = -1;
      if (!isset($args['average']))
        $theAmount = DB::$V->reqInt($args, 'p_amount', 1, 256, "Invalid penalty/breakdown amount.");

      // Based on the amount, honor the displace option
      $theDisplace = 0;
      if ($theAmount > 0 && isset($args['displace'])) {
        $theDisplace = 1;
      }

      // give the users the flexibility to do things wrong, if they so choose
      $breakdowns = $this->breakdowns;
      $races = array();
      foreach ($finishes as $theFinish) {
        if (!$this->canHaveModifier($theFinish, $thePen)) {
          Session::pa(new PA(sprintf("Ignored finish modifier for team %s.", $theFinish->team), PA::I));
          continue;
        }
        $races[$theFinish->race->id] = $theFinish->race;
        if (isset($breakdowns[$thePen])) {
          if ($theFinish->score !== null && $theAmount >= $theFinish->score) {
            Session::pa(new PA("The assigned score is no better than the actual score; ignoring.", PA::I));
            return array();
          }
          if ($this->REGATTA->scoring != Regatta::SCORING_TEAM)
            $theFinish->setModifier();
          $theFinish->addModifier(new Breakdown($thePen, $theAmount, $theComm, $theDisplace));
        }
        else {
	  $modifier = new Penalty($thePen, $theAmount, $theComm, $theDisplace);
          $other_mods = $theFinish->getModifiers();
          $other_mods[] = $modifier;
	  $score = $this->REGATTA->scorer->getPenaltiesScore($theFinish, $other_mods);
          if ($theFinish->score !== null && $theAmount > 0 && $score->score <= $theFinish->score)
	    throw new SoterException("The assigned penalty score is no worse than their actual score; ignoring.");
	  // Allow assigned penalties beyond FLEET + 1
          if ($this->REGATTA->scoring != Regatta::SCORING_TEAM)
            $theFinish->setModifier();
          $theFinish->addModifier($modifier);
        }
      }

      $this->REGATTA->commitFinishes($finishes);
      foreach ($races as $race)
        $this->REGATTA->runScore($race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);

      $mes = sprintf("Added %s for %s.", $thePen, implode(', ', $teams));
      Session::pa(new PA($mes));
    }

    return array();
  }

  protected function getRaceTable() {
    $divisions = $this->REGATTA->getDivisions();

    // Table of finished races
    $hrows = array(array());
    $brows = array(array());
    foreach ($divisions as $div) {
      $hrows[0][] = (string)$div;
      $nums = array();
      foreach ($this->REGATTA->getScoredRaces($div) as $race)
	$nums[] = $race->number;
      $brows[0][] = DB::makeRange($nums);
    }
    return XTable::fromArray($brows, $hrows, array('class'=>'narrow'));
  }

  /**
   * Helper method to add the visual aspects of adding penalty
   *
   * @param XForm $form the form to fill
   * @param FinishModifier::Const the penalty type
   */
  protected function fillPenaltyScheme(XForm $form, $type) {
    // - Amount, or average, if necessary
    if (isset($this->breakdowns[$type]))
      $average = "Use average within division";
    else
      $average = "Use standard scoring (FLEET + 1).";
    $new_score = new FItem("New score:", new FCheckbox('average', 'yes', $average, false, array('id'=>'avg_box', 'onclick'=>'document.getElementById("p_amount").disabled = this.checked;document.getElementById("displace_box").disabled = this.checked;')));
    $form->add($new_score);

    $new_score = new FItem("OR Assign score:", new XNumberInput('p_amount', "", 1, null, 1, array('size'=>'2', 'id'=>'p_amount')));
    $new_score->add(new XCheckboxInput('displace', 'yes', array('id'=>'displace_box')));
    $new_score->add(new XLabel('displace_box', 'Displace finishes'));
    $form->add($new_score);

    // script to turn off the two by default
    $form->add(new XScript('text/javascript', null,
			   "document.getElementById('p_amount').disabled = true;".
			   "document.getElementById('displace_box').disabled = true;".
			   "document.getElementById('avg_box').checked   = true;"));
  }

  protected function canHaveModifier(Finish $fin, $type) {
    $mods = $fin->getModifiers();
    return (count($mods) == 0);
  }

  /**
   * Allows subclasses to specify additional ways of selecting races
   *
   */
  protected function fillAlternateRaceSelection(XForm $form) {}
}