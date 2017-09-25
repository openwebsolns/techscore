<?php
namespace users\membership;

use \users\AbstractUserPane;
use \users\membership\tools\EditSailorForm;
use \users\membership\tools\EditSailorProcessor;
use \users\membership\tools\SailorMerger;
use \xml5\GraduationYearInput;
use \xml5\XWarningPort;

use \Account;
use \DB;
use \Permission;
use \Sailor;
use \Session;
use \SoterException;
use \STN;
use \UpdateManager;
use \UpdateRequest;

use \FItem;
use \FReqItem;
use \XA;
use \XHiddenInput;
use \XP;
use \XPort;
use \XSelect;
use \XSubmitDelete;
use \XSubmitP;
use \XTextInput;
use \XWarning;

/**
 * Manages a single sailor's record. Requires EDIT_SAILOR_LIST permission.
 *
 * @author Dayan Paez
 * @version 2017-09-21
 */
class SingleSailorPane extends AbstractUserPane {

  const EDIT_KEY = 'id';
  const SEARCH_KEY = 'q';

  const SUBMIT_DELETE = 'delete-sailor';
  const SUBMIT_EDIT = 'edit-sailor';
  const SUBMIT_MERGE = 'merge';

  const PORT_EDIT = "Edit sailor";
  const PORT_MERGE = "Merge unregistered";

  const FIELD_REGISTERED_ID = 'registered_id';

  /**
   * @var EditSailorProcessor processor for editing sailor info.
   */
  private $editSailorProcessor;

  /**
   * @var SailorMerger to merge sailors (auto injected).
   */
  private $sailorMerger;

  public function __construct(Account $user) {
    parent::__construct("Sailor record", $user);
  }

  public function fillHTML(Array $args) {
    try {
      $sailor = DB::$V->reqID($args, self::EDIT_KEY, DB::T(DB::SAILOR), "Invalid sailor ID provided.");
      $this->fillSailor($sailor, $args);
      return;
    }
    catch (SoterException $e) {
      Session::error($e->getMessage());
    }
    $this->redirect(); // go back
  }

  private function fillSailor(Sailor $sailor, Array $args) {
    $this->PAGE->addContent(new XP(array(), array(new XA($this->linkTo('users\membership\SailorsPane'), "← Back to list"))));

    if (!$sailor->isRegistered() && $this->canMerge($sailor)) {
      $orgname = DB::g(STN::ORG_NAME);
      $this->PAGE->addContent($p = new XWarningPort(self::PORT_MERGE));
      $p->add($form = $this->createForm());
      $form->add(new XHiddenInput(EditSailorForm::FIELD_ID, $sailor->id));
      $form->add(
        new XP(
          array(),
          "This is an unregistered sailor. Choose this option to merge this sailor's sailing record with a registered one."
        )
      );

      $form->add(
        new FReqItem(
          "Merge into:",
          XSelect::fromDBM(
            self::FIELD_REGISTERED_ID,
            $sailor->school->getSailors(),
            null,
            array(),
            ''
          )
        )
      );

      $form->add(new XSubmitP(self::SUBMIT_MERGE, "Merge"));
    }

    $canEdit = $this->canEdit($sailor);
    $this->PAGE->addContent($p = new XPort(self::PORT_EDIT));
    $p->add($form = new EditSailorForm($this->link(), $sailor, $canEdit));
    $this->addCsrfToken($form);
    
    if ($canEdit) {
      $form->add($xp = new XSubmitP(self::SUBMIT_EDIT, "Edit"));
      try {
        $this->validateDeletion($sailor);
        $xp->add(new XSubmitDelete(self::SUBMIT_DELETE, "Delete"));
      }
      catch (SoterException $e) {
        // No op
      }
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Edit
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_EDIT, $args)) {
      $sailor = DB::$V->reqID($args, EditSailorForm::FIELD_ID, DB::T(DB::SAILOR), "Invalid sailor provided.");
      if (!$this->canEdit($sailor)) {
        throw new SoterException("No permission to edit given sailor.");
      }

      $processor = $this->getEditSailorProcessor();
      $changed = $processor->process($args, $sailor);
      if (count($changed) == 0) {
        Session::warn("Nothing changed.");
      }
      else {
        Session::info("Updated sailor information.");
      }
    }

    // ------------------------------------------------------------
    // Merge
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_MERGE, $args)) {
      $sailor = DB::$V->reqID($args, EditSailorForm::FIELD_ID, DB::T(DB::SAILOR), "Invalid sailor provided.");
      if (!$this->canMerge($sailor)) {
        throw new SoterException("No permission to merge given sailor.");
      }

      $otherSailor = DB::$V->incID($args, self::FIELD_REGISTERED_ID, DB::T(DB::SAILOR));
      if ($otherSailor === null) {
        return false;
      }
      if ($otherSailor->id == $sailor->id) {
        throw new SoterException("Cannot replace a sailor with itself.");
      }
      if (!$otherSailor->isRegistered()) {
        throw new SoterException("Cannot replace a sailor with an unregistered one.");
      }

      $merger = $this->getSailorMerger();
      $regattas = $merger->merge($sailor, $otherSailor);
      foreach ($regattas as $regatta) {
        UpdateManager::queueRequest(
          $regatta,
          UpdateRequest::ACTIVITY_RP,
          $sailor->school
        );
      }

      DB::remove($sailor);
      Session::info(
        sprintf(
            "Replaced %s with %s.",
            $sailor,
            $otherSailor
          )
      );

      $this->redirectTo('users\membership\SailorsPane');
    }
  }

  private function canEdit(Sailor $sailor) {
    return (
      $this->USER->can(Permission::EDIT_SAILOR_LIST)
      && $this->USER->hasSchool($sailor->school)
    );
  }

  private function canMerge(Sailor $sailor) {
    return (
      $this->USER->hasSchool($sailor->school)
      && $this->USER->can(Permission::EDIT_UNREGISTERED_SAILORS)
    );
  }

  /**
   * Can the given sailor be removed?
   *
   * @param Sailor $sailor whose deletion to validate.
   * @throws SoterException if unable to delete.
   */
  private function validateDeletion(Sailor $sailor) {
    if (!$this->USER->hasSchool($sailor->school)) {
      throw new SoterException("You do not have permission to delete given sailor.");
    }

    $rootAccount = DB::getRootAccount();
    if ($sailor->isRegistered()) {
      throw new SoterException("Sailor cannot be deleted because it is being synced externally.");
    }

    $regattasCount = count($sailor->getRegattas());
    if ($regattasCount > 0) {
      throw new SoterException(
        sprintf("Sailor has participated in %d regatta(s) and cannot be deleted.", $regattasCount)
      );
    }
  }

  public function setEditSailorProcessor(EditSailorProcessor $processor) {
    $this->editSailorProcessor = $processor;
  }

  private function getEditSailorProcessor() {
    if ($this->editSailorProcessor === null) {
      $this->editSailorProcessor = new EditSailorProcessor();
    }
    return $this->editSailorProcessor;
  }

  public function setSailorMerger(SailorMerger $merger) {
    $this->sailorMerger = $merger;
  }

  private function getSailorMerger() {
    if ($this->sailorMerger === null) {
      $this->sailorMerger = new SailorMerger();
    }
    return $this->sailorMerger;
  }

}