<?php
use \data\finalize\CompleteRpCriterion;
use \data\finalize\FinalizeStatus;
use \data\finalize\MissingSummariesCriterion;
use \data\finalize\Pr24Criterion;
use \data\finalize\UnsailedMiddleRacesCriterion;

require_once('tscore/AbstractPane.php');

/**
 * Mighty useful pane for reviewing and finalizing regatta
 *
 * @author Dayan Paez
 * @created 2013-03-28
 */
class FinalizePane extends AbstractPane {

  private $criteriaRegistry = array();

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Settings", $user, $reg);
    $this->criteriaRegistry = array(
      new UnsailedMiddleRacesCriterion(),
      new Pr24Criterion(),
      new MissingSummariesCriterion(),
      new CompleteRpCriterion(),
    );
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Review and finalize"));
    $p->add(new XP(array(), "Please review the information about this regatta that appears below, and address any outstanding issues."));

    $can_finalize = true;
    $p->add($tab = new XQuickTable(array('id'=>'finalize-issues', 'class'=>'full'), array("Status", "Issue")));

    $ICONS = array(
      FinalizeStatus::VALID => new XImg(WS::link('/inc/img/s.png'), "✓"),
      FinalizeStatus::ERROR => new XImg(WS::link('/inc/img/e.png'), "X"),
      FinalizeStatus::WARN  => new XImg(WS::link('/inc/img/i.png'), "⚠"),
    );

    // Criteria
    foreach ($this->criteriaRegistry as $criterion) {
      if ($criterion->canApplyTo($this->REGATTA)) {
        foreach ($criterion->getFinalizeStatuses($this->REGATTA) as $status) {
          $type = $status->getType();
          $message = $status->getMessage();

          if ($message !== null) {
            $tab->addRow(array($ICONS[$type], $message));
          }

          if ($type == FinalizeStatus::ERROR) {
            $can_finalize = false;
          }
        }
      }
    }

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
      $f->add(new FReqItem("Approval:", new FCheckbox('approve', 1, "I have reviewed the information above and wish to finalize this regatta.")));
      $f->add(new XSubmitP("finalize", "Finalize!"));
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Finalize
    if (isset($args['finalize'])) {
      if (!$this->REGATTA->hasFinishes())
        throw new SoterException("You cannot finalize a project with no finishes.");

      foreach ($this->criteriaRegistry as $criterion) {
        if ($criterion->canApplyTo($this->REGATTA)) {
          foreach ($criterion->getFinalizeStatuses($this->REGATTA) as $status) {
            if ($status == FinalizeStatus::ERROR) {
              throw new SoterException($status->getMessage());
            }
          }
        }
      }

      if (!isset($args['approve']))
        throw new SoterException("Please check the box to finalize.");

      $this->REGATTA->finalized = new DateTime();
      $unscoredRaces = $this->REGATTA->getUnscoredRaces();
      $this->REGATTA->removeRaces($unscoredRaces);
      $removed = count($unscoredRaces);

      if ($this->REGATTA->scoring == Regatta::SCORING_TEAM) {
        // Lock scores
        foreach ($this->REGATTA->getRankedTeams() as $team) {
          $team->lock_rank = 1;
          DB::set($team);
        }
      }

      $this->REGATTA->setData(); // implies update to object
      Session::pa(new PA("Regatta has been finalized."));
      if ($removed > 0) {
        Session::pa(new PA("Removed $removed unsailed races.", PA::I));
      }
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_FINALIZED);
      $this->redirect('home');
    }
  }
}
?>