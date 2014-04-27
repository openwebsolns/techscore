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
      $this->PAGE->addContent($p = new XPort("1. Choose action and division"));
      $p->add($form = $this->createForm(XForm::GET));

      // Action
      require_once('xml5/XMultipleSelect.php');
      $form->add(new FReqItem("Action:", XSelect::fromArray('edittype', $this->ACTIONS, $edittype)));
      $form->add(new FReqItem("Division(s):", new XMultipleSelect('division[]',
                                                                  array_combine($exist_div, $exist_div),
                                                                  array(),
                                                                  $chosen_div)));
      $form->add(new XSubmitP("choose_act", "Next →"));
      return;
    }

    // ------------------------------------------------------------
    // 2. Tweak details
    // ------------------------------------------------------------

    $edittype = $args['edittype'];
    $this->PAGE->addContent($p = new XPort(sprintf("2. %s for Division %s",
                                                   $this->ACTIONS[$edittype],
                                                   implode(", ", $chosen_div))));
    $p->add($form = $this->createForm());

    // Write in this form the options from above
    foreach ($chosen_div as $div) {
      $form->add(new XHiddenInput("division[]", $div));
    }

    $range_races = sprintf('1-%d', count($this->REGATTA->getRaces(Division::A())));
    $form->add(new FReqItem("Races:", new XRangeInput('races', $range_races)));

    if ( $edittype === "ADD") {
      $form->add(new FReqItem("Add amount (±):",
                              new XNumberInput('addamount', "", null, null, 1, array('size'=>'3'))));

      $form->add(new XP(array('class'=>'p-submit'),
                        array(new XA($this->link('tweak-sails'), "← Start over"), " ",
                              new XSubmitInput('addsails', "Edit sails"))));
    }
    elseif ( $edittype == "REP" ) {
      // Get sails in all races
      $sails = array();
      foreach ($rotation->getCommonSails($rotation->getRaces()) as $sail)
        $sails[$sail->sail] = $sail;

      $sails = array_combine($sails, $sails);
      $form->add($f_item = new FReqItem("Replace sail:", XSelect::fromArray('from_sail', $sails)));
      $f_item->add(" with ");
      $f_item->add(new XTextInput('to_sail', '', array('size'=>'3', 'required'=>'required')));

      $form->add(new XP(array('class'=>'p-submit'),
                        array(new XA($this->link('tweak-sails'), "← Start over"), " ",
                              new XSubmitInput('replacesails', "Replace"))));
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
    $divisions = DB::$V->reqDivisions($args, 'division', $rotation->getDivisions(), 1, "Invalid/missing divisions.");

    // ------------------------------------------------------------
    // 1. Choose type of tweak
    // ------------------------------------------------------------
    if (isset($args['choose_act'])) {
      $args['edittype'] = DB::$V->reqKey($args, 'edittype', $this->ACTIONS, "Invalid or missing tweak type.");
      return $args;
    }

    // ------------------------------------------------------------
    // 2. Tweak
    // ------------------------------------------------------------
    //   - get races and unique sails
    $args['races'] = DB::parseRange(DB::$V->reqString($args, 'races', 1, 100, "Missing list of races."));
    $racenums = DB::$V->reqValues($args, 'races', $this->REGATTA->getUnscoredRaceNumbers($divisions), 1, "Invalid races.");

    // Get sail numbers for all the races
    $races = array();
    foreach ($divisions as $div) {
      foreach ($racenums as $num) {
        if (($race = $this->REGATTA->getRace($div, $num)) !== null)
          $races[] = $race;
      }
    }
    if (count($races) == 0)
      throw new SoterException("No valid races chosen.");
    $sails = $rotation->getCommonSails($races);

    // ------------------------------------------------------------
    // 2a. Add to existing sails
    // ------------------------------------------------------------
    if (isset($args['addsails'])) {

      // Validate amount
      $min = Rotation::min($sails);
      $amount = DB::$V->reqInt($args, 'addamount', 1 - $min, 1000 - $min, "Invalid amount to add to sails (sails numbers must be positive).");
      if ($amount == 0)
        throw new SoterException("It is senseless to add nothing to the sails.");

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
      $fromsail = DB::$V->reqValue($args, 'from_sail', $sails, "Invalid/missing sail to replace.");
      $tosail = DB::$V->reqString($args, 'to_sail', 1, 9, "Invalid/missing sail to change to.");
      if (in_array($tosail, $sails))
        throw new SoterException("Duplicate value for sail $tosail.");

      foreach ($races as $race)
        $rotation->replaceSail($race, $fromsail, $tosail);

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(sprintf("Successfully replaced sail %s with %s.", $fromsail, $tosail)));
      unset($args['edittype']);
    }

    return $args;
  }
}
?>