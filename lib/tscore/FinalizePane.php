<?php
use \data\finalize\AggregatedFinalizeCriteria;
use \data\finalize\FinalizeStatus;

require_once('tscore/AbstractPane.php');

/**
 * Mighty useful pane for reviewing and finalizing regatta
 *
 * @author Dayan Paez
 * @created 2013-03-28
 */
class FinalizePane extends AbstractPane {

  const SUBMIT_FINALIZE = 'finalize';

  private $criteriaRegistry = array();

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Finalize", $user, $reg);
    $this->criteriaRegistry = new AggregatedFinalizeCriteria();
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
    if ($this->criteriaRegistry->canApplyTo($this->REGATTA)) {
      foreach ($this->criteriaRegistry->getFinalizeStatuses($this->REGATTA) as $status) {
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

    if (!$can_finalize && $this->USER->can(Permission::OVERRIDE_FINALIZE_REGATTA)) {
      $p->add(new XWarning("This regatta is not normally considered finalizable due to the issues above. You may decide to override this behavior at your own discretion, but proceed with caution."));
      $can_finalize = true;
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
      $f->add(new XSubmitP(self::SUBMIT_FINALIZE, "Finalize!"));
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Finalize
    if (array_key_exists(self::SUBMIT_FINALIZE, $args)) {
      if (!$this->REGATTA->hasFinishes())
        throw new SoterException("You cannot finalize a project with no finishes.");

      if ($this->criteriaRegistry->canApplyTo($this->REGATTA)) {
        if (!$this->USER->can(Permission::OVERRIDE_FINALIZE_REGATTA)) {
          foreach ($this->criteriaRegistry->getFinalizeStatuses($this->REGATTA) as $status) {
            if ($status->getType() == FinalizeStatus::ERROR) {
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