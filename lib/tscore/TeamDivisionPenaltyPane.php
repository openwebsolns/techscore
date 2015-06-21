<?php
namespace tscore;

use \AbstractPane;
use \PA;
use \Session;
use \UpdateManager;
use \UpdateRequest;

use \FItem;
use \FReqItem;
use \XHiddenInput;
use \XP;
use \XPort;
use \XQuickTable;
use \XSelect;
use \XSubmitInput;
use \XSubmitP;
use \XTD;
use \XTextArea;

use \SoterException;

use \Account;
use \DB;
use \Division;
use \DivisionPenalty;
use \Regatta;

require_once('AbstractPane.php');

/**
 * Edit "division-level" penalties (team penalty) for team racing.
 *
 * These penalties are added to all the divisions.
 *
 * @author Dayan Paez
 * @version 2015-04-12
 */
class TeamDivisionPenaltyPane extends AbstractPane {

  public function __construct(Account $user, Regatta $regatta) {
    parent::__construct("Team penalty", $user, $regatta);
  }

  protected function fillHTML(Array $args) {
    $this->fillAddPort($args);
    $this->fillCurrentPort($args);
  }

  private function fillAddPort(Array $args) {
    $this->PAGE->addContent($p = new XPort("Team penalties"));
    $p->add(
      new XP(
        array(),
        
        "These penalties will be applied after the final team record. The penalty is -2 wins and +2 losses."
      )
    );

    $teams = array();
    foreach ($this->REGATTA->getTeams() as $team) {
      $teams[$team->id] = $team;
    }
    if (count($teams) == 0) {
      $p->add(new XHeading("No teams have been registered."));
      return;
    }

    $p->add($form = $this->createForm());
    $form->add(new FItem("Team:", XSelect::fromArray('team', $teams)));
    $opts = array_merge(array(""=>""), DivisionPenalty::getList());
    $form->add(new FReqItem("Penalty type:", XSelect::fromArray('penalty', $opts)));

    $form->add(new FItem("Comments:", new XTextArea('comments', ''/*, array('rows'=>'2', 'cols'=>'35')*/)));

    $form->add(new XSubmitP('add-penalty', "Enter team penalty"));
  }

  private function fillCurrentPort(Array $args) {
    $this->PAGE->addContent($p = new XPort("Team penalties"));
    $penalties = $this->REGATTA->getDivisionPenalties(null, Division::A());

    if (count($penalties) == 0) {
      $p->add(new XP(array(), "There are no team penalties."));
      return;
    }

    $p->add($tab = new XQuickTable(array('class'=>'full penaltytable'), array("Team name", "Penalty", "Comments", "Action")));
    foreach ($penalties as $p) {
      $tab->addRow(
        array(
          $p->team,
          $p->type,
          new XTD(array('style'=>'text-align:left;width:10em;'), $p->comments),
          $form = $this->createForm()
        )
      );

      $form->add(new XHiddenInput('team', $p->team->id));
      $form->add(new XSubmitInput('drop-penalty', "Drop", array('class'=>'small')));
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Add penalty
    // ------------------------------------------------------------
    if (isset($args['add-penalty'])) {
      $team = DB::$V->reqTeam($args, 'team', $this->REGATTA, "Invalid or missing team.");
      $pnty = DB::$V->reqKey($args, 'penalty', DivisionPenalty::getList(), "Invalid or missing penalty type.");
      $comm = DB::$V->incString($args, 'comments', 1, 16000, null);

      // create
      $penalty = new DivisionPenalty();
      $penalty->team = $team;
      $penalty->type = $pnty;
      $penalty->comments = $comm;
      $penalty->division = Division::A();
      $this->REGATTA->setDivisionPenalty($penalty);

      $this->REGATTA->setRanks();
      Session::pa(new PA("Added team penalty."));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
    }

    // ------------------------------------------------------------
    // Drop penalty
    // ------------------------------------------------------------
    if (isset($args['drop-penalty'])) {
      $team = DB::$V->reqTeam($args, 'team', $this->REGATTA, "Invalid or missing team.");
      if ($this->REGATTA->dropDivisionPenalty($team, Division::A())) {
        $this->REGATTA->setRanks();
        Session::pa(new PA(sprintf("Dropped team penalty for %s.", $team)));
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
      }
      else {
        Session::pa(new PA("No team penalty dropped.", PA::I));
      }
    }
  }
}