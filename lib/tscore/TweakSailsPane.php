<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */
require_once('conf.php');

/**
 * Pane to tweak the rotations
 *
 * @author Dayan Paez
 * @created 2010-01-20
 */
class TweakSailsPane extends AbstractPane {

  private $ACTIONS = array("ADD"=>"Add or subtract value to sails",
			   "REP"=>"Replace sail with a different one");

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Tweak sails", $user, $reg);
    $this->urls[] = "tweak";
  }

  protected function fillHTML(Array $args) {

    $rotation  = $this->REGATTA->getRotation();
    $divisions = $this->REGATTA->getDivisions();
    $exist_div = $rotation->getDivisions();

    // Chosen divisions
    $chosen_div = null;
    if (isset($args['division']) &&
	is_array($args['division'])) {
      foreach ($args['division'] as $div) {
	if (in_array($div, $exist_div))
	  $chosen_div[] = new Division($div);
      }
    }
    else {
      $chosen_div = $exist_div;
    }

    // edittype dictates which step of the editing process we're
    // currently in. When null, we're filling out the first step of
    // the tweaking process
    if (!isset($args['edittype']) ||
	!in_array($args['edittype'], array_keys($this->ACTIONS))) {

      $edittype = "ADD";
      // ------------------------------------------------------------
      // 1. Select edit type
      // ------------------------------------------------------------
      $this->PAGE->addContent($p = new Port("Edit sail numbers"));
      $p->addChild(new Heading("1. Choose action and division"));
      $p->addChild($form = $this->createForm());

      // Action
      $form->addChild(new FItem("Action:",
				$f_sel = new FSelect("edittype", array($edittype))));
      $f_sel->addOptions($this->ACTIONS);

      $form->addChild(new FItem("Division(s):",
				$f_sel = new FSelect("division[]",
						     $chosen_div,
						     array("multiple"=>"multiple"))));
      $f_sel->addOptions(array_combine($exist_div, $exist_div));
      $form->addChild(new FSubmit("choose_act", "Next >>"));
    }
    else {

      // ------------------------------------------------------------
      // 2. Tweak details
      // ------------------------------------------------------------
      
      $edittype = $args['edittype'];

      $range_races = Utilities::getUnscoredRaceNumbers($this->REGATTA, $chosen_div);

      $this->PAGE->addContent($p = new Port(sprintf("2. %s for Division %s",
					      $this->ACTIONS[$edittype],
					      implode(", ", $chosen_div))));
      $p->addChild($form = $this->createForm());

      // Write in this form the options from above
      foreach ($chosen_div as $div) {
	$form->addChild(new FHidden("division[]", $div));
      }

      $form->addChild($f_item = new FItem("Races:",
					  new FText("races", Utilities::makeRange($range_races),
						    array("size"=>"12"))));
      $f_item->addChild($tab = new Table());
      $tab->addAttr("class", "narrow");
      $tab->addHeader(new Row(array(Cell::th("Possible"))));
      $tab->addRow(new Row(array(new Cell(Utilities::makeRange($range_races)))));

      if ( $edittype === "ADD" ) {
	$form->addChild(new FItem("Add/subtract:",
				  $f = new FText("addamount", "", array("size"=>"3"))));
	$f->addAttr("maxlength", "3");
	$form->addChild(new FSubmit("cancel", "<< Cancel"));
	$form->addChild(new FSubmit("addsails", "Edit sails"));
      }
      elseif ( $edittype == "REP" ) {
	// Get sails in chosen races
	$races = array();
	foreach ($chosen_div as $div)
	  foreach ($range_races as $num)
	    $races[] = $this->REGATTA->getRace($div, $num);
	$sails = $rotation->getCommonSails($races);

	$sails = array_combine($sails, $sails);
	$form->addChild($f_item = new FItem("Replace sail:",
					    $f_sel = new FSelect("from_sail",
								 array())));
	$f_sel->addOptions($sails);
	$f_item->addChild(new FSpan("with"));
	$f_item->addChild(new FText("to_sail", "",
				    array("size"=>"3")));
	$form->addChild(new FSubmit("cancel", "<< Cancel"));
	$form->addChild(new FSubmit("replacesails", "Replace"));
      }

    }
  }

  public function process(Array $args) {
    $rotation = $this->REGATTA->getRotation();

    // ------------------------------------------------------------
    // 0. Reset/cancel
    // ------------------------------------------------------------
    if (isset($args['cancel'])) {
      unset($args['edittype']);
      return $args;
    }

    // ------------------------------------------------------------
    // 0.A Validate divisions
    // ------------------------------------------------------------
    $divisions = null;
    if (isset($args['division']) &&
	is_array($args['division'])) {
      foreach ($args['division'] as $div) {
	try {
	  $divisions[] = new Division($div);
	}
	catch (Exception $e) {
	  $mes = sprintf("Ignored invalid division (%s).", $div);
	  $this->announce(new Announcement($mes, Announcement::WARNING));
	}
      }
    }
    else {
      $mes = "Missing divisions.";
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return $args;
    }

    // ------------------------------------------------------------
    // 1. Choose type of tweak
    // ------------------------------------------------------------
    if (isset($args['choose_act'])) {
      // Check action
      if (!isset($args['edittype']) ||
	  !in_array($args['edittype'], array_keys($this->ACTIONS))) {
	$mes = sprintf("Invalid tweak type (%s).", $args['edittype']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	unset($args['edittype']);
      }
      return $args;
    }

    
    // ------------------------------------------------------------
    // 2. Tweak
    // ------------------------------------------------------------
    //   - get races and unique sails
    if (isset($args['races']) &&
	($races = Utilities::parseRange($args['races'])) !== null) {
      if (!sort($races)) {
	$mes = sprintf("Unable to understand/sort race range (%s).", $args['races']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }
      // Keep only races that are unscored
      $valid_races = Utilities::getUnscoredRaceNumbers($this->REGATTA, $divisions);
      $ignored_races = array();
      $actual_races  = array();
      foreach ($races as $r) {
	if (!in_array($r, $valid_races))
	  $ignored_races[] = $r;
	else
	  $actual_races[]  = $r;
      }
      if (count($ignored_races) > 0) {
	$mes = sprintf('Ignored races %s in divisions %s',
		       Utilities::makeRange($ignored_races),
		       implode(", ", $divisions));
	$this->announce(new Announcement($mes, Announcement::WARNING));
      }

      // Get sail numbers for all the races
      $races = array();
      foreach ($divisions as $div)
	foreach ($actual_races as $num)
	  $races[] = $this->REGATTA->getRace($div, $num);
      $sails = $rotation->getCommonSails($races);
    }
    else {
      $mes = sprintf("Invalid range for races (%s).", $args['races']);
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return $args;
    }

    // ------------------------------------------------------------
    // 2a. Add to existing sails
    // ------------------------------------------------------------
    if (isset($args['addsails'])) {

      // Validate amount
      $amount = null;
      if (isset($args['addamount']) &&
	  is_numeric($args['addamount'])) {
	$amount = (int)$args['addamount'];
	if ($amount + min($sails) <= 0) {
	  $mes = "Sail numbers must be positive.";
	  $this->announce(new Announcement($mes, Announcement::ERROR));
	  return $args;
	}
      }
      else {
	$mes = "Missing or invalid amount to add to sails.";
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }

      foreach ($races as $race)
	$rotation->addAmount($race, $amount);

      $rotation->commit();
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      $this->announce(new Announcement("Added value to sails."));
      unset($args['edittype']);
    }

    // ------------------------------------------------------------
    // 2b. Replace existing sails
    // ------------------------------------------------------------
    if (isset($args['replacesails'])) {

      //   - Validate FROM sail
      $fromsail = null;
      if (isset($args['from_sail']) &&
	  in_array($args['from_sail'], $sails)) {
	$fromsail = $args['from_sail'];
      }
      else {
	$mes = sprintf("Invalid sail number to replace (%s).", $args['from_sail']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }

      //  - Validate TO sail
      $tosail = null;
      if (isset($args['to_sail']) &&
	  is_numeric($args['to_sail'])) {
	$tosail = (int)$args['to_sail'];
	if (in_array($tosail, $sails)) {
	  $mes = "Duplicate value for sail $tosail.";
	  $this->announce(new Announcement($mes, Announcement::ERROR));
	  return $args;
	}
      }
      else {
	$mes = sprintf("New sail number is invalid or missing (%s).", $args['to_sail']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }

      foreach ($races as $race)
	$rotation->replaceSail($race, $fromsail, $tosail);

      $rotation->commit();
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      $this->announce(new Announcement("Sail replaced successfully."));
      unset($args['edittype']);
    }

    return $args;
  }

  public function isActive($posting = false) {
    $rot = $this->REGATTA->getRotation();
    return count($rot->getSails()) > 0;
  }
}
?>