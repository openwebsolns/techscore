<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Enter and drop team penalties
 *
 * @author Dayan Paez
 * @version 2010-01-25
 */
class DivisionPenaltyPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Team penalty", $user, $reg);
  }

  protected function fillHTML(Array $args) {

    $divisions = array();
    foreach ($this->REGATTA->getDivisions() as $div)
      $divisions[(string)$div] = $div;
    $teams = array();
    foreach ($this->REGATTA->getTeams() as $team)
      $teams[$team->id] = $team;

    $this->PAGE->addContent($p = new XPort("Team penalties per division"));
    $p->add(new XP(array(),
                   array("These penalties will be added to the final " .
                         "team score after all race finishes have been " .
                         "totaled. The penalty is applied per division.")));

    if (count($teams) == 0) {
      $p->add(new XHeading("No teams have been registered."));
      return;
    }

    require_once('xml5/XMultipleSelect.php');
    $p->add($form = $this->createForm());
    $form->add(new FItem("Team:", XSelect::fromArray('team', $teams)));
    if (count($divisions) > 1) {
      $form->add(new FReqItem("Division(s):", new XMultipleSelect('division[]', $divisions)));
    }
    else
      $form->add(new XHiddenInput('division[]', array_shift($divisions)));

    // Penalty type
    $opts = array("" => "");
    $settings = DivisionPenalty::getSettingsList();
    foreach (DivisionPenalty::getList() as $penalty => $description) {
      $amount = "";
      if (array_key_exists($penalty, $settings)) {
        $amount = " (+" . DB::g($settings[$penalty]) . ")";
      }
      $opts[$penalty] = $description . $amount;
    }
    $form->add(new FReqItem("Penalty type:", XSelect::fromArray('penalty', $opts)));

    $form->add(new FItem("Comments:",
                         new XTextArea("comments", "",
                                       array("rows"=>"2",
                                             "cols"=>"35"))));

    $form->add(new XSubmitP("t_submit", "Enter team penalty"));


    // ------------------------------------------------------------
    // Existing penalties
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Team penalties"));
    $penalties = $this->REGATTA->getDivisionPenalties();

    if (count($penalties) == 0)
      $p->add(new XP(array(), "There are no team penalties."));
    else {
      $p->add($tab = new XQuickTable(array('class'=>'full penaltytable'), array("Team name", "Division", "Penalty", "Amount", "Comments", "Action")));
      foreach ($penalties as $p) {
        $tab->addRow(array($p->team,
                           $p->division,
                           $p->type,
                           "+" . $p->amount,
                           new XTD(array('style'=>'text-align:left;width:10em;'), $p->comments),
                           $form = $this->createForm()));

        $form->add(new XP(array('class'=>'thin'),
                          array(new XHiddenInput("r_team", $p->team->id),
                                new XHiddenInput("r_div",  $p->division),
                                new XSubmitInput("t_remove", "Drop", array("class"=>"small")))));
      }
    }
  }


  public function process(Array $args) {

    // ------------------------------------------------------------
    // Add penalty
    // ------------------------------------------------------------
    if (isset($args['t_submit'])) {
      $team = DB::$V->reqTeam($args, 'team', $this->REGATTA, "Invalid or missing team.");
      $pnty = DB::$V->reqKey($args, 'penalty', DivisionPenalty::getList(), "Invalid or missing penalty type.");
      $comm = DB::$V->incString($args, 'comments', 1, 16000, null);
      $divs = DB::$V->reqDivisions($args, 'division', $this->REGATTA->getDivisions(), 1, "Division list not provided.");

      foreach ($divs as $div) {
        $pen = new DivisionPenalty();
        $pen->team = $team;
        $pen->type = $pnty;
        $pen->comments = $comm;
        $pen->division = $div;
        $this->REGATTA->setDivisionPenalty($pen);
      }
      $this->REGATTA->setRanks();
      Session::pa(new PA("Added team penalty."));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
    }

    // ------------------------------------------------------------
    // Drop penalty
    // ------------------------------------------------------------
    if (isset($args['t_remove'])) {
      $team = DB::$V->reqTeam($args, 'r_team', $this->REGATTA, "Invalid or missing team.");
      $div = DB::$V->reqDivision($args, 'r_div', $this->REGATTA->getDivisions(), "Invalid or missing division.");
      if ($this->REGATTA->dropDivisionPenalty($team, $div)) {
        $this->REGATTA->setRanks($div);
        Session::pa(new PA(sprintf("Dropped team penalty for %s in division %s.", $team, $div)));
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
      }
      else
        Session::pa(new PA("No team penalty dropped.", PA::I));
    }

    return $args;
  }
}
