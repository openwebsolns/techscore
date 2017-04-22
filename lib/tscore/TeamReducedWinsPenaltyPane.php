<?php
namespace tscore;

use \model\ReducedWinsPenalty;

use \AbstractPane;
use \Session;
use \UpdateManager;
use \UpdateRequest;
use \WS;

use \FItem;
use \FReqItem;
use \XA;
use \XHiddenInput;
use \XNumberInput;
use \XOption;
use \XOptionGroup;
use \XP;
use \XPort;
use \XQuickTable;
use \XScript;
use \XSelect;
use \XSubmitInput;
use \XSubmitP;
use \XTD;
use \XTextArea;

use \SoterException;

use \Account;
use \DB;
use \Division;
use \Regatta;

require_once('AbstractPane.php');

/**
 * Edit "discretionary" penalties (reduced-wins), per RRS.D3.
 *
 * @author Dayan Paez
 * @version 2017-04-28
 */
class TeamReducedWinsPenaltyPane extends AbstractPane {

  const AMOUNT_MIN = 0.25;
  const AMOUNT_STEP = 0.25;

  const ID_RACE_INPUT = 'race-input';
  const ID_TEAM_INPUT = 'team-input';

  const INPUT_AMOUNT = 'amount';
  const INPUT_COMMENTS = 'comments';
  const INPUT_RACE = 'race';
  const INPUT_REDUCED_WINS_PENALTY = 'reduced-wins-penalty';
  const INPUT_TEAM = 'team';

  const SUBMIT_ADD = 'add-penalty';
  const SUBMIT_DROP = 'drop-penalty';

  public function __construct(Account $user, Regatta $regatta) {
    parent::__construct("Discretionary penalty", $user, $regatta);
  }

  protected function fillHTML(Array $args) {
    $this->fillAddPort($args);
    $this->fillCurrentPort($args);
  }

  private function fillAddPort(Array $args) {
    $this->PAGE->addContent($p = new XPort("Discretionary penalties"));
    $p->add(
      new XP(
        array(),
        
        array(
          "This is an extra penalty that reduces a team's overall win record by a fixed amount. It can be associated with an individual race or the overall team. It will be applied after the final team record. Standard team penalties (PFD, LOP, MRP, etc) can be entered via the ",
          new XA($this->link('team-penalty'), "Team penalty"),
          " pane."
        )
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
    $teams[''] = "[Choose team...]";

    $p->add($form = $this->createForm());
    $form->add(new FReqItem("Team:", XSelect::fromArray(self::INPUT_TEAM, $teams, '', array('id' => self::ID_TEAM_INPUT))));
    $raceDropdown = $this->getRaceDropdown();
    if ($raceDropdown !== null) {
      $form->add(new FItem("Race:", $raceDropdown, "(Optional) Include this value to indicate when the infraction happened."));
    } else {
      $form->add(new XHiddenInput(self::INPUT_RACE, ''));
    }
    $form->add(new FReqItem("Number of wins to deduct:", 
      new XNumberInput(
        self::INPUT_AMOUNT,
        '',
        self::AMOUNT_MIN,
        null,
        self::AMOUNT_STEP
      )
    ));
    $form->add(new FItem("Comments:", new XTextArea(self::INPUT_COMMENTS, '', array('rows'=>'2', 'cols'=>'50'))));

    $form->add(new XSubmitP(self::SUBMIT_ADD, "Enter penalty"));
    $form->add(new XScript('text/javascript', WS::link('/inc/js/reduced-wins-penalty.js')));
  }

  private function fillCurrentPort(Array $args) {
    $this->PAGE->addContent($p = new XPort("Existing penalties"));
    $penalties = $this->REGATTA->getReducedWinsPenalties();

    if (count($penalties) == 0) {
      $p->add(new XP(array(), "There are no discretionary penalties."));
      return;
    }

    $p->add($tab = new XQuickTable(array('class'=>'full penaltytable'), array("Team name", "Race", "Amount", "Comments", "Action")));
    foreach ($penalties as $p) {
      $tab->addRow(
        array(
          $p->team,
          $p->race,
          $p->amount,
          new XTD(array('style'=>'text-align:left;width:10em;'), $p->comments),
          $form = $this->createForm()
        )
      );

      $form->add(new XHiddenInput(self::INPUT_REDUCED_WINS_PENALTY, $p->id));
      $form->add(new XSubmitInput(self::SUBMIT_DROP, "Drop", array('class'=>'small')));
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Add penalty
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_ADD, $args)) {
      $team = DB::$V->reqTeam($args, self::INPUT_TEAM, $this->REGATTA, "Invalid or missing team.");
      $race = DB::$V->incScoredRace($args, self::INPUT_RACE, $this->REGATTA);
      if ($race !== null && $race->tr_team1->id !== $team->id && $race->tr_team2->id !== $team->id) {
        throw new SoterException("Chosen team did not participate in provided race.");
      }
      $amount = DB::$V->reqFloat($args, self::INPUT_AMOUNT, self::AMOUNT_MIN, 100, "No amount provided.");
      $comm = DB::$V->incString($args, self::INPUT_COMMENTS, 1, 16000, null);

      // create
      $penalty = new ReducedWinsPenalty();
      $penalty->race = $race;
      $penalty->amount = $amount;
      $penalty->comments = $comm;
      $this->REGATTA->addReducedWinsPenalty($team, $penalty);
      Session::info(sprintf("Added discretionary penalty for %s.", $team));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
    }

    // ------------------------------------------------------------
    // Drop penalty
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_DROP, $args)) {
      $penalty = DB::$V->reqID($args, self::INPUT_REDUCED_WINS_PENALTY, DB::T(DB::REDUCED_WINS_PENALTY), "Invalid penalty to drop.");
      if ($this->REGATTA->getTeam($penalty->team->id) === null) {
        throw new SoterException("Invalid penalty requested.");
      }
      $this->REGATTA->dropReducedWinsPenalty($penalty);
      Session::info(sprintf("Dropped discretionary penalty for %s.", $penalty->team));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
    }
  }

  /**
   * Returns a dropdown of sailed races each indicating via dataSet the teams involved.
   *
   * @return XSelect
   */
  private function getRaceDropdown() {
    $scoredRounds = $this->REGATTA->getScoredRounds();
    if (count($scoredRounds) === 0) {
      return null;
    }

    $dropdown = new XSelect(self::INPUT_RACE, array('id' => self::ID_RACE_INPUT, 'class' => 'no-mselect'));
    $dropdown->add(new XOption('', array(), "No race"));
    foreach ($scoredRounds as $round) {
      $dropdown->add($optGroup = new XOptionGroup($round));
      foreach ($this->REGATTA->getScoredRacesInRound($round, Division::A()) as $race) {
        $optGroup->add(new XOption(
          $race, 
          array('data-team1' => $race->tr_team1->id, 'data-team2' => $race->tr_team2->id),
          sprintf("%s: %s vs. %s", $race, $race->tr_team1, $race->tr_team2)
        ));
      }
    }
    return $dropdown;
  }
}