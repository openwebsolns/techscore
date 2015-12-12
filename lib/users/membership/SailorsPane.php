<?php
namespace users\membership;

use \ui\AddSailorsTable;
use \users\AbstractUserPane;
use \users\membership\tools\EditSailorForm;
use \users\membership\tools\EditSailorProcessor;
use \users\membership\tools\SailorMerger;
use \xml5\GraduationYearInput;
use \xml5\PageWhiz;
use \xml5\SailorPageWhizCreator;
use \xml5\XExternalA;
use \xml5\XWarningPort;

use \Account;
use \DB;
use \Conf;
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
use \XCollapsiblePort;
use \XHiddenInput;
use \XP;
use \XPort;
use \XSelect;
use \XSubmitDelete;
use \XSubmitP;
use \XTextInput;
use \XQuickTable;
use \XWarning;

/**
 * Manages the database of sailors.
 *
 * @author Dayan Paez
 * @version 2015-11-12
 */
class SailorsPane extends AbstractUserPane {

  const NUM_PER_PAGE = 30;
  const EDIT_KEY = 'id';
  const SEARCH_KEY = 'q';

  const SUBMIT_DELETE = 'delete-sailor';
  const SUBMIT_EDIT = 'edit-sailor';
  const SUBMIT_ADD = 'add-sailor';
  const SUBMIT_MERGE = 'merge';

  const PORT_ADD = "Add sailor";
  const PORT_EDIT = "Edit sailor";
  const PORT_LIST = "All sailors";
  const PORT_MERGE = "Merge unregistered";

  const FIELD_REGISTERED_ID = 'registered_id';
  const FIELD_SAILORS = 'sailor';

  /**
   * @var EditSailorProcessor processor for editing sailor info.
   */
  private $editSailorProcessor;

  /**
   * @var SailorMerger to merge sailors (auto injected).
   */
  private $sailorMerger;

  /**
   * @var boolean true if given user can perform edit operations.
   */
  private $canEdit;

  public function __construct(Account $user) {
    parent::__construct("Sailors", $user);
    $this->canEdit = $this->USER->can(Permission::EDIT_SAILOR_LIST);
  }

  public function fillHTML(Array $args) {
    if (array_key_exists(self::EDIT_KEY, $args) && $this->canEdit) {
      try {
        $sailor = DB::$V->reqID($args, self::EDIT_KEY, DB::T(DB::SAILOR), "Invalid sailor ID provided.");
        $this->fillSailor($sailor, $args);
        return;
      }
      catch (SoterException $e) {
        Session::error($e->getMessage());
      }
    }

    if ($this->USER->can(Permission::ADD_SAILOR)) {
      $this->fillAddNew($args);
    }
    $this->fillList($args);
  }

  private function fillAddNew(Array $args) {
    $this->PAGE->addContent($p = new XCollapsiblePort(self::PORT_ADD));
    $p->add($form = $this->createForm());
    $form->add(new AddSailorsTable(self::FIELD_SAILORS, $this->USER->getSchools()));
    $form->add(new XSubmitP(self::SUBMIT_ADD, "Add"));
  }

  private function fillSailor(Sailor $sailor, Array $args) {
    $this->PAGE->addContent(new XP(array(), array(new XA($this->link(), "← Back to list"))));

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

    $this->PAGE->addContent($p = new XPort(self::PORT_EDIT));
    $p->add($form = new EditSailorForm($this->link(), $sailor));
    $this->addCsrfToken($form);
    
    $form->add($xp = new XSubmitP(self::SUBMIT_EDIT, "Edit"));
    try {
      $this->validateDeletion($sailor);
      $xp->add(new XSubmitDelete(self::SUBMIT_DELETE, "Delete"));
    }
    catch (SoterException $e) {
      // No op
    }
  }

  private function fillList(Array $args) {
    $this->PAGE->addContent($p = new XPort(self::PORT_LIST));
    $link = $this->link();

    $whizCreator = new SailorPageWhizCreator($this->USER, $args, self::NUM_PER_PAGE, $link);
    $sailors = $whizCreator->getMatchedSailors();

    $whiz = $whizCreator->getPageWhiz();
    $slice = $whiz->getSlice($sailors);
    $ldiv = $whiz->getPageLinks();

    $p->add($whizCreator->getFilterForm($link));
    $p->add($whizCreator->getSearchForm());
    $p->add($ldiv);
    if (count($slice) > 0) {
      $p->add($this->getSailorsTable($slice));
    }
    $p->add($ldiv);
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
    // Add
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_ADD, $args)) {
      if (!$this->USER->can(Permission::ADD_SAILOR)) {
        throw new SoterException("No permission to add sailor.");
      }
      $sailorList = DB::$V->reqList($args, self::FIELD_SAILORS, null, "No list of sailors provided.");
      $genders = Sailor::getGenders();
      $sailors = array();
      foreach ($sailorList as $sailObject) {
        $sailor = new Sailor();
        $sailor->school = DB::$V->incSchool($sailObject, 'school');
        if (
          $sailor->school !== null
          && !$this->USER->hasSchool($sailor->school)
        ) {
          throw new SoterException("Invalid school provided.");
        }
        $sailor->first_name = DB::$V->incString($sailObject, 'first_name');
        $sailor->last_name = DB::$V->incString($sailObject, 'last_name');
        $sailor->year = DB::$V->incInt($sailObject, 'year', GraduationYearInput::MINIMUM);
        $sailor->gender = DB::$V->incKey($sailObject, 'gender', $genders);

        if (
          $sailor->school !== null
          && $sailor->first_name !== null
          && $sailor->last_name !== null
          && $sailor->year !== null
          && $sailor->gender !== null
        ) {
          if (DB::g(STN::SAILOR_PROFILES)) {
            $sailor->url = DB::createUrlSlug(
              $sailor->getUrlSeeds(),
              function ($slug) use ($sailor) {
                $other = DB::getSailorByUrl($slug);
                return ($other === null || $other->id == $sailor->id);
              }
            );
          }
          $sailors[] = $sailor;
        }
      }

      $count = count($sailors);
      if ($count == 0) {
        throw new SoterException("No sailors provided.");
      }

      foreach ($sailors as $sailor) {
        DB::set($sailor);
      }
      if ($count == 1) {
        Session::info(sprintf("Added %s.", $sailors[0]));
      }
      else {
        Session::info(sprintf("Added %d sailors.", $count));
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

      $this->redirect($this->pane_url());
    }
  }

  private function getSailorsTable($sailors) {
    $genders = Sailor::getGenders();
    $useProfiles = DB::g(STN::SAILOR_PROFILES) !== null;
    $headers = array(
      "ID",
      "Full Name",
      "School",
      "Year",
      "Gender",
    );
    if ($useProfiles) {
      $headers[] = "URL";
    }
    $table = new XQuickTable(
      array('class' => 'sailors-table'),
      $headers
    );

    foreach ($sailors as $i => $sailor) {
      $id = $sailor->id;
      if ($this->canEdit($sailor)) {
        $id = new XA(
          $this->link(array(self::EDIT_KEY => $id)),
          $id
        );
      }

      $row = array(
        $id,
        $sailor,
        $sailor->school,
        $sailor->year,
        $genders[$sailor->gender],
      );
      if ($useProfiles) {
        $url = '';
        if ($useProfiles && $sailor->url !== null) {
          $url = new XExternalA(
            sprintf('http://%s%s', Conf::$PUB_HOME, $sailor->getURL()),
            $sailor->url
          );
        }

        $row[] = $url;
      }
        
      $table->addRow($row, array('class' => 'row' . ($i % 2))
      );
    }

    return $table;
  }

  private function canEdit(Sailor $sailor) {
    // TODO
    return (
      $this->canEdit
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