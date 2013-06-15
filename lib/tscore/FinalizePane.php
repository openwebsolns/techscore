<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package tscore
 */

require_once('tscore/AbstractPane.php');

/**
 * Mighty useful pane for reviewing and finalizing regatta
 *
 * @author Dayan Paez
 * @created 2013-03-28
 */
class FinalizePane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Settings", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Review and finalize"));
    $p->add(new XP(array(), "Please review the information about this regatta that appears below, and address any outstanding issues."));

    $can_finalize = true;
    $p->add($tab = new XQuickTable(array('id'=>'finalize-issues'), array("Status", "Issue")));

    $VALID = new XImg(WS::link('/inc/img/s.png'), "✓");
    $ERROR = new XImg(WS::link('/inc/img/e.png'), "X");
    $WARN  = new XImg(WS::link('/inc/img/i.png'), "⚠");

    // Missing races
    $list = $this->getUnsailedMiddleRaces();
    $mess = "No middle races unscored.";
    $icon = $VALID;
    if (count($list) > 0) {
      $nums = array();
      foreach ($list as $race)
        $nums[] = $race->number;
      $mess = "The following races must be scored: " . DB::makeRange($nums);
      $icon = $ERROR;
      $can_finalize = false;
    }
    $tab->addRow(array($icon, $mess));

    // PR 24
    if (($mess = $this->passesPR24()) !== null) {
      $tab->addRow(array($ERROR,
                         new XTD(array(),
                                 new XP(array(),
                                        array($mess,
                                              " To delete extra finishes, use the ",
                                              new XA($this->link('finishes'), "finishes pane"),
                                              ".")))));
      $can_finalize = false;
    }

    // Summaries
    $list = $this->getMissingSummaries();
    $mess = "All daily summaries completed.";
    $icon = $VALID;
    if (count($list) > 0) {
      $mess = new XP(array(),
                     array("Missing one or more ",
                           new XA($this->link('summaries'), "daily summaries"),
                           "."));
      $icon = $ERROR;
      $can_finalize = false;
    }
    $tab->addRow(array($icon, $mess));

    // RP
    $mess = "All RP info is present.";
    $icon = $VALID;
    $rpm = $this->REGATTA->getRpManager();
    if (!$rpm->isComplete()) {
      $mess = array("There is ",
                    new XA($this->link('missing'), "missing RP"),
                    " information. Note that this may be edited after finalization.");
      $icon = $WARN;
    }
    $tab->addRow(array($icon, $mess));

    // ------------------------------------------------------------
    // Team racing
    // ------------------------------------------------------------
    if ($can_finalize && $this->REGATTA->scoring == Regatta::SCORING_TEAM) {
      // Ranks
      $p->add(new XH4("Rankings"));
      $p->add(new XP(array(),
                     array("Since tiebreaks must be resolved manually for team racing, please take this opportunity to make sure the teams are correctly ranked below. To make edits, visit the ",
                           new XA($this->link('rank'), "Rank teams"),
                           " pane.")));
      $p->add($tab = new XQuickTable(array('id'=>'ranktable', 'class'=>'teamtable'),
                                     array("#", "Explanation", "Record", "Team")));
      foreach ($this->REGATTA->getRankedTeams() as $i => $team) {
        $tab->addRow(array($team->dt_rank,
                           $team->dt_explanation,
                           $team->getRecord(),
                           $team),
                     array('class'=>'row' . ($i % 2)));
      }
    }

    if ($can_finalize) {
      $p->add($f = $this->createForm());
      $f->add(new FItem($chk = new XCheckboxInput('approve', 1, array('id'=>'approve')),
                        new XLabel('approve',
                                   "I have reviewed the information above and wish to finalize this regatta.",
                                   array("class"=>"strong"))));
      $f->add(new XSubmitP("finalize", "Finalize!"));
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Finalize
    if (isset($args['finalize'])) {
      if (!$this->REGATTA->hasFinishes())
        throw new SoterException("You cannot finalize a project with no finishes.");

      $list = $this->getUnsailedMiddleRaces();
      if (count($list) > 0)
        throw new SoterException("Cannot finalize with unsailed races: " . implode(", ", $list));

      if (($mess = $this->passesPR24()) !== null)
        throw new SoterException($mess);

      if (count($this->getMissingSummaries()) > 0)
        throw new SoterException("Missing at least one daily summary.");

      if (!isset($args['approve']))
        throw new SoterException("Please check the box to finalize.");

      $this->REGATTA->finalized = new DateTime();
      $removed = 0;
      foreach ($this->REGATTA->getUnscoredRaces() as $race) {
        $this->REGATTA->removeRace($race);
        $removed++;
      }

      if ($this->REGATTA->scoring == Regatta::SCORING_TEAM) {
        // Lock scores
        foreach ($this->REGATTA->getRankedTeams() as $team) {
          $team->lock_rank = 1;
          DB::set($team);
        }
      }

      DB::set($this->REGATTA);
      Session::pa(new PA("Regatta has been finalized."));
      if ($removed > 0)
        Session::pa(new PA("Removed $removed unsailed races.", PA::I));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_FINALIZED);
      $this->redirect('home');
    }
  }

  /**
   * Fetch list of unsailed races
   *
   */
  private function getUnsailedMiddleRaces() {
    $divs = ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) ?
      $this->REGATTA->getDivisions() :
      array(Division::A());
    
    $list = array();
    foreach ($divs as $division) {
      $prevNumber = 0;
      foreach ($this->REGATTA->getScoredRaces($division) as $race) {
        for ($i = $prevNumber + 1; $i < $race->number; $i++)
          $list[] = $this->REGATTA->getRace($division, $i);
        $prevNumber = $race->number;
      }
    }
    return $list;
  }

  private function passesPR24() {
    if ($this->REGATTA->scoring != Regatta::SCORING_STANDARD)
      return null;
    $divisions = $this->REGATTA->getDivisions();
    if (count($divisions) < 2)
      return null;

    $max = 0;
    $min = null;
    foreach ($divisions as $division) {
      $num = count($this->REGATTA->getScoredRaces($division));
      if ($num > $max)
        $max = $num;
      if ($min === null || $num < $min)
        $min = $num;
    }
    if ($this->REGATTA->getDuration() == 1) {
      if ($max != $min)
        return "PR 24b: Final regatta scores shall be based only on the scores of the races in which each division has completed an equal number.";
      return null;
    }
    elseif (($max - $min) > 2)
      return "PR 24b(i): Multi-day events: no more than two (2) additional races shall be scored in any one division more than the division with the least number of races.";
    return null;
  }

  private function getMissingSummaries() {
    $stime = clone $this->REGATTA->start_time;
    $missing = array();
    for ($i = 0; $i < $this->REGATTA->getDuration(); $i++) {
      $comms = $this->REGATTA->getSummary($stime);
      if (strlen($comms) == 0)
        $missing[] = clone $stime;
      $stime->add(new DateInterval('P1DT0H'));
    }
    return $missing;
  }
}
?>