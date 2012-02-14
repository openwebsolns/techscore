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

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Add penalty", $user, $reg);
  }

  protected function fillHTML(Array $args) {

    // Default is the last scored race
    $finished_races = $this->REGATTA->getScoredRaces();
    if (count($finished_races) == 0) {
      Session::pa(new PA("No finishes entered.",
				       PA::I));
      $this->redirect();
    }
    if (!DB::$V->hasRace($theRace, $args, 'p_race', $this->REGATTA))
      $theRace = $finished_races[count($finished_races)-1];

    $p_type = null;
    if (isset($args['p_type']))
      $p_type = $args['p_type'];

    $divisions = $this->REGATTA->getDivisions();

    if ($p_type == null) {

      // ------------------------------------------------------------
      // 1. Chosen race
      // ------------------------------------------------------------
      $this->PAGE->addContent($p = new XPort("1. Individual penalties and breakdowns"));
      $p->add($form = $this->createForm());

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
      $form->add(new FItem("Possible races:", XTable::fromArray($brows, $hrows, array('class'=>'narrow'))));
      $form->add(new FItem("Race:", new XTextInput("p_race", $theRace,
						   array("size"=>"4",
							 "maxlength"=>"4",
							 "id"=>"chosen_race",
							 "class"=>"narrow"))));

      // Penalty type
      $form->add(new FItem("Penalty type:", XSelect::fromArray('p_type', array("Penalties" => Penalty::getList(),
									       "Breakdowns" => Breakdown::getList()))));
      // Submit
      $form->add(new XSubmitInput("c_race", "Next >>"));
    }
    else {
      $rotation = $this->REGATTA->getRotation();

      // ------------------------------------------------------------
      // 2. Penalty details
      // ------------------------------------------------------------
      $title = sprintf("2. %s in race %s", $p_type, $theRace);
      $this->PAGE->addContent($p = new XPort($title));
      $p->add($form = $this->createForm());
      $form->add(new XHiddenInput("p_type", $p_type));
      $form->add(new FItem("Team:", $f_sel = new XSelectM("finish[]")));
      $num_finishes = 0;
      foreach ($this->REGATTA->getTeams() as $team) {
	$fin = $this->REGATTA->getFinish($theRace, $team);
	if ($fin->penalty === null) {
	  $sail = (string)$rotation->getSail($theRace, $team);
	  if (strlen($sail) > 0)
	    $sail = sprintf(" (%s)", $sail);
	  $f_sel->add(new FOption($fin->id, "$team$sail"));
	  $num_finishes++;
	}
      }
      /*
      if ($num_finishes == 0) {
	Session::pa(new PA("There are no finishes to which add a penalty.", PA::I));
	Session::s('p_race', null);
	unset($args['p_race']);
	$this->redirect('penalty');
      }
      */

      // - comments
      $form->add(new FItem("Comments:",
			   new XTextArea("p_comments", "",
					 array("rows"=>"2",
					       "cols"=>"50"))));
      // - Amount, or average, if necessary
      $b = Breakdown::getList();
      if (in_array($p_type, array_keys($b)))
	$average = "Use average within division";
      else
	$average = "Use standard scoring (FLEET + 1).";
      $new_score = new FItem("New score:",
			     $cb = new XCheckboxInput("average", "yes", array("id"=>"avg_box")));
      $cb->set("onclick", "document.getElementById('p_amount').disabled = this.checked;document.getElementById('displace_box').disabled = this.checked;");
      // $cb->set("checked", "checked");
      $new_score->add(new XLabel("avg_box", $average));
      $form->add($new_score);

      $new_score = new FItem("OR Assign score:",
			     new XTextInput("p_amount", "", array("size"=>"2", "id"=>"p_amount")));
      $new_score->add(new XCheckboxInput("displace", "yes", array("id"=>"displace_box")));
      $new_score->add(new XLabel('displace_box', 'Displace finishes'));
      $form->add($new_score);

      // script to turn off the two by default
      $form->add(new XScript('text/javascript', null,
			     "document.getElementById('p_amount').disabled = true;".
			     "document.getElementById('displace_box').disabled = true;".
			     "document.getElementById('avg_box').checked   = true;"));
      // Submit
      $form->add(new XSubmitInput("p_cancel", "Cancel"));
      $form->add(new XSubmitInput("p_submit", "Enter $p_type"));

      // FAQ's
      $this->PAGE->addContent($p = new XPort("FAQ"));
      $fname = sprintf("%s/faq/penalty.html", dirname(__FILE__));
      $p->add(new XRawText(file_get_contents($fname)));
    }
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // 0. Cancel
    // ------------------------------------------------------------
    if (isset($args['p_cancel'])) {
      unset($args['p_type']);
      return $args;
    }

    // ------------------------------------------------------------
    // 1. Choose race
    // ------------------------------------------------------------
    // Change of race request
    if (isset($args['c_race'])) {
      if (!DB::$V->hasRace($race, $args, 'p_race', $this->REGATTA) ||
	  count($this->REGATTA->getFinishes($race)) == 0) {
	unset($args['p_race']);
	unset($args['p_type']);
	throw new SoterException("Invalid or missing race for penalty.");
      }
      $args['p_race'] = (string)$race;

      // - validate penalty type
      if (!DB::$V->hasKey($type, $args, 'p_type', Penalty::getList()) &&
	  !DB::$V->hasKey($type, $args, 'p_type', Breakdown::getList())) {
	unset($args['p_type']);
	throw new SoterException("Invalid or missing penalty/breakdown.");
      }
      $args['p_type'] = $type;
      return $args;
    }

    // ------------------------------------------------------------
    // 2. Enter penalty
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

      $thePen  = $args['p_type'];
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
      $breakdowns = Breakdown::getList();
      $races = array();
      foreach ($finishes as $theFinish) {
	$races[$theFinish->race->id] = $theFinish->race;
	if (isset($breakdowns[$thePen])) {
	  if ($theFinish->score !== null && $theAmount >= $theFinish->score) {
	    Session::pa(new PA("The assigned score is no better than the actual score; ignoring.", PA::I));
	    $args['p_race'] = $race;
	    return $args;
	  }
	  $theFinish->setModifier(new Breakdown($thePen, $theAmount, $theComm, $theDisplace));
	}
	else {
	  if ($theFinish->score !== null &&
	      $theAmount > 0 &&
	      $theAmount <= $theFinish->score) {
	    Session::pa(new PA("The assigned penalty score is no worse than their actual score; ignoring.", PA::I));
	    return $args;
	  }
	  elseif ($theAmount > ($fleet = $this->REGATTA->getFleetSize() + 1)) {
	    Session::pa(new PA(sprintf("The assigned penalty is greater than the maximum penalty of FLEET + 1 (%d); ignoring.", $fleet),
			       PA::I));
	    return $args;
	  }
	  $theFinish->setModifier(new Penalty($thePen, $theAmount, $theComm, $theDisplace));
	}
      }
      $this->REGATTA->commitFinishes($finishes);
      foreach ($races as $race)
	$this->REGATTA->runScore($race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
      
      $mes = sprintf("Added %s for %s.", $thePen, implode(', ', $teams));
      Session::pa(new PA($mes));
      unset($args['p_type']);
    }

    return $args;
  }
}