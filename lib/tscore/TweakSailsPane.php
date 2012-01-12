<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('conf.php');

/**
 * Pane to tweak the rotations
 *
 * @author Dayan Paez
 * @version 2010-01-20
 */
class TweakSailsPane extends AbstractPane {

  private $ACTIONS = array("ADD"=>"Add or subtract value to sails",
			   "REP"=>"Replace sail with a different one");

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Tweak sails", $user, $reg);
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
      $this->PAGE->addContent($p = new XPort("Edit sail numbers"));
      $p->add(new XHeading("1. Choose action and division"));
      $p->add($form = $this->createForm());

      // Action
      $form->add(new FItem("Action:", XSelect::fromArray('edittype', $this->ACTIONS, $edittype)));
      $form->add(new FItem("Division(s):", XSelectM::fromArray('division[]',
							       array_combine($exist_div, $exist_div),
							       $chosen_div)));
      $form->add(new XSubmitInput("choose_act", "Next >>"));
    }
    else {

      // ------------------------------------------------------------
      // 2. Tweak details
      // ------------------------------------------------------------
      
      $edittype = $args['edittype'];

      $range_races = $this->REGATTA->getUnscoredRaceNumbers($chosen_div);

      $this->PAGE->addContent($p = new XPort(sprintf("2. %s for Division %s",
						     $this->ACTIONS[$edittype],
						     implode(", ", $chosen_div))));
      $p->add($form = $this->createForm());

      // Write in this form the options from above
      foreach ($chosen_div as $div) {
	$form->add(new XHiddenInput("division[]", $div));
      }

      $form->add($f_item = new FItem("Races:",
				     new XTextInput("races", DB::makeRange($range_races),
						    array("size"=>"12"))));
      $f_item->add(XTable::fromArray(array(array(DB::makeRange($range_races))),
				     array(array("Possible")),
				     array('class'=>'narrow')));

      if ( $edittype === "ADD" ) {
	$form->add(new FItem("Add/subtract:",
			     $f = new XTextInput("addamount", "", array("size"=>"3"))));
	$f->set("maxlength", "3");
	$form->add(new XSubmitInput("cancel", "<< Cancel"));
	$form->add(new XSubmitInput("addsails", "Edit sails"));
      }
      elseif ( $edittype == "REP" ) {
	// Get sails in chosen races
	$races = array();
	foreach ($chosen_div as $div)
	  foreach ($range_races as $num)
	  $races[] = $this->REGATTA->getRace($div, $num);
	$sails = $rotation->getCommonSails($races);

	$sails = array_combine($sails, $sails);
	$form->add($f_item = new FItem("Replace sail:", XSelect::fromArray('from_sail', $sails)));
	$f_item->add(" with ");
	$f_item->add(new XTextInput("to_sail", "",
				    array("size"=>"3")));
	$form->add(new XSubmitInput("cancel", "<< Cancel"));
	$form->add(new XSubmitInput("replacesails", "Replace"));
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
	  Session::pa(new PA($mes, PA::I));
	}
      }
    }
    else {
      $mes = "Missing divisions.";
      Session::pa(new PA($mes, PA::E));
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
	Session::pa(new PA($mes, PA::E));
	unset($args['edittype']);
      }
      return $args;
    }

    
    // ------------------------------------------------------------
    // 2. Tweak
    // ------------------------------------------------------------
    //   - get races and unique sails
    if (isset($args['races']) &&
	($races = DB::parseRange($args['races'])) !== null) {
      if (!sort($races)) {
	$mes = sprintf("Unable to understand/sort race range (%s).", $args['races']);
	Session::pa(new PA($mes, PA::E));
	return $args;
      }
      // Keep only races that are unscored
      $valid_races = $this->REGATTA->getUnscoredRaceNumbers($divisions);
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
		       DB::makeRange($ignored_races),
		       implode(", ", $divisions));
	Session::pa(new PA($mes, PA::I));
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
      Session::pa(new PA($mes, PA::E));
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
	  Session::pa(new PA($mes, PA::E));
	  return $args;
	}
      }
      else {
	$mes = "Missing or invalid amount to add to sails.";
	Session::pa(new PA($mes, PA::E));
	return $args;
      }

      foreach ($races as $race)
	$rotation->addAmount($race, $amount);

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA("Added value to sails."));
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
	Session::pa(new PA($mes, PA::E));
	return $args;
      }

      //  - Validate TO sail
      $tosail = null;
      if (isset($args['to_sail']) &&
	  is_numeric($args['to_sail'])) {
	$tosail = (int)$args['to_sail'];
	if (in_array($tosail, $sails)) {
	  $mes = "Duplicate value for sail $tosail.";
	  Session::pa(new PA($mes, PA::E));
	  return $args;
	}
      }
      else {
	$mes = sprintf("New sail number is invalid or missing (%s).", $args['to_sail']);
	Session::pa(new PA($mes, PA::E));
	return $args;
      }

      foreach ($races as $race)
	$rotation->replaceSail($race, $fromsail, $tosail);

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA("Sail replaced successfully."));
      unset($args['edittype']);
    }

    return $args;
  }
}
?>