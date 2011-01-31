<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Add, edit, and display individual penalties
 *
 * @author Dayan Paez
 * @created 2010-01-25
 */
class EnterPenaltyPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Individual penalties and breakdowns", $user, $reg);
    $this->title = "Add penalty";
    array_unshift($this->urls, 'penalties');
  }

  protected function fillHTML(Array $args) {

    // Default is the last scored race
    $finished_races = $this->REGATTA->getScoredRaces();
    if (count($finished_races) == 0) {
      $this->announce(new Announcement("No finishes entered.",
				       Announcement::WARNING));
      $this->redirect();
    }
    $theRace = (isset($args['p_race'])) ?
      $args['p_race'] :
      $finished_races[count($finished_races)-1];

    $p_type = null;
    if (isset($args['p_type']))
      $p_type = $args['p_type'];

    $divisions = $this->REGATTA->getDivisions();

    if ($p_type == null) {

      // ------------------------------------------------------------
      // 1. Chosen race
      // ------------------------------------------------------------
      $this->PAGE->addContent($p = new Port("1. Individual penalties and breakdowns"));
      $p->addChild($form = $this->createForm());
      $form->addChild(new FItem("Possible races:",
				$tab = new Table()));

      // Table of finished races
      $tab->addAttr("class", "narrow");
      $row = array();
      foreach ($divisions as $div)
	$row[] = Cell::th($div);
      $tab->addHeader(new Row($row));
      $row = array();
      foreach ($divisions as $div) {
	// Get races with finishes
	$nums = array();
	foreach ($this->REGATTA->getScoredRaces($div) as $race)
	  $nums[] = $race->number;
	$row[] = new Cell(Utilities::makeRange($nums));
      }
      $tab->addRow(new Row($row));
      $form->addChild($fitem = new FItem("Race:", 
					 new FText("p_race",
						   $theRace,
						   array("size"=>"4",
							 "maxlength"=>"4",
							 "id"=>"chosen_race",
							 "class"=>"narrow"))));

      // Penalty type
      $form->addChild(new FItem("Penalty type:",
				$f_sel = new FSelect("p_type",
						     array($p_type))));
      // Penalties and breakdown options
      $f_sel->addOptionGroup("Penalties",  Penalty::getList());
      $f_sel->addOptionGroup("Breakdowns", Breakdown::getList());

      // Submit
      $form->addChild(new FSubmit("c_race", "Next >>"));
    }
    else {
      $rotation = $this->REGATTA->getRotation();

      // ------------------------------------------------------------
      // 2. Penalty details
      // ------------------------------------------------------------
      $title = sprintf("2. %s in race %s", $p_type, $theRace);
      $this->PAGE->addContent($p = new Port($title));
      $p->addChild($form = $this->createForm());
      $form->addChild(new FHidden("p_type", $p_type));
      $form->addChild(new FItem("Team:",
				$f_sel = new FSelect("finish", array(""))));
      $options = array();
      foreach ($this->REGATTA->getTeams() as $team) {
	$finish = $this->REGATTA->getFinish($theRace, $team);
	$options[$finish->id] = sprintf("%s (%s)",
				      $team,
				      $rotation->getSail($theRace, $team));
      }
      $f_sel->addOptions($options);

      // - comments
      $form->addChild(new FItem("Comments:",
				new FTextarea("p_comments", "",
					      array("rows"=>"2",
						    "cols"=>"50"))));
      // - Amount, or average, if necessary
      if ( $p_type == "RDG" || $p_type == "BKD") {
	$new_score = new FItem("New score:",
			       new FText("p_amount", "",
					 array("size"=>"2", "id"=>"p_amount")));
	$form->addChild($new_score);
	$new_score->addChild($cb = new FCheckbox("average", "yes", array("id"=>"avg_box")));
	$new_score->addChild(new Label("avg_box", "Use average within division"));
	$cb->addAttr("onclick", "document.getElementById('p_amount').disabled = this.checked;");
	$new_score->addChild($sc = new GenericElement("script"));
	$sc->addAttr("type", "text/javascript");
	$sc->addChild(new Text("document.getElementById('p_amount').disabled = true;"));
	$sc->addChild(new Text("document.getElementById('avg_box').checked   = true;"));
      }
    
      // Submit
      $form->addChild(new FSubmit("p_cancel", "Cancel"));
      $form->addChild(new FSubmit("p_submit", "Enter $p_type"));
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
      // - validate race
      $races = $this->REGATTA->getScoredRaces();
      try {
	$race = Race::parse($args['p_race']);
	$race = $this->REGATTA->getRace($race->division, $race->number);
	$theRace = Preferences::getObjectWithProperty($races, "id", $race->id);
	if ($theRace == null) {
	  $mes = sprintf("No finish recorded for race %s.", $theRace);
	  $this->announce(new Announcement($mes, Announcement::WARNING));
	  unset($args['p_race']);
	  unset($args['p_type']);
	  return $args;
	}
	$args['p_race'] = $theRace;
      }
      catch (InvalidArgumentException $e) {
	$mes = sprintf("Invalid race (%s).", $args['chosen_race']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	unset($args['p_race']);
	unset($args['p_type']);
	return $args;
      }

      // - validate penalty type
      if (!isset($args['p_type']) ||
	  (!in_array($args['p_type'], array_keys(Penalty::getList())) &&
	   !in_array($args['p_type'], array_keys(Breakdown::getList())))) {
	$mes = sprintf("Invalid or missing penalty (%s).", $args['p_type']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
      }
      return $args;
    }

    // ------------------------------------------------------------
    // 2. Enter penalty
    // ------------------------------------------------------------
    if (isset($args['p_submit']) ) {
      // Validate input
      $finishes = $this->REGATTA->getFinishes();
      $theFinish = Preferences::getObjectWithProperty($finishes,
						      "id",
						      $args['finish']);
      $thePen  = $args['p_type'];
      $theComm = addslashes(htmlspecialchars($args['p_comments']));

      if ($theFinish != null &&
	  in_array($thePen, array_keys(Penalty::getList()))) {
	$theFinish->penalty = new Penalty($thePen, -1, $theComm);
      }
      else if ($thePen == Breakdown::BYE) {
	// Average scoring
	$theFinish->penalty = new Breakdown(Breakdown::BYE, -1, $theComm);
      }
      else if ($thePen == Breakdown::RDG ||
	       $thePen == Breakdown::BKD) {
	// Get amount, checkbox has preference
	if (isset($args['average'])) {
	  $h_amount = -1;
	}
	else if (is_numeric($args['p_amount']) &&
		 (int)($args['p_amount']) > 0) {
	  $h_amount = (int)($args['p_amount']);
	}
	else {
	  $mes = sprintf("Invalid breakdown amount (%s).", $args['p_amount']);
	  $this->announce(new Announcement($mes, Announcement::ERROR));
	  return $args;
	}
	$theFinish->penalty = new Breakdown($thePen, $h_amount, $theComm);
      }
      else {
	$mes = sprintf("Illegal penalty/breakdown type (%s).", $thePen);
	$this->announce(new Announcement($mes, Announcement::ERROR));
      }
      $mes = sprintf("Added %s for %s.", $thePen, $theFinish->team);
      $this->announce(new Announcement($mes));
      unset($args['p_type']);
    }

    return $args;
  }

  public function isActive() {
    return count($this->REGATTA->getFinishes()) > 0;
  }
}