<?php
namespace users\admin;

use \users\admin\tools\EditSchoolForm;
use \users\admin\tools\EditSchoolProcessor;
use \xml5\XExternalA;
use \xml5\PageWhiz;

use \Account;
use \DB;
use \Conf;
use \Permission;
use \Session;
use \School;
use \SoterException;
use \STN;
use \UpdateManager;
use \UpdateSchoolRequest;

use \XA;
use \XCollapsiblePort;
use \XHiddenInput;
use \XP;
use \XPort;
use \XQuickTable;
use \XSpan;
use \XSubmitDelete;
use \XSubmitP;
use \XWarning;

/**
 * Manage the list of schools, depending on settings.
 *
 * @author Dayan Paez
 * @version 2015-11-05
 */
class SchoolsPane extends AbstractAdminUserPane {

  const NUM_PER_PAGE = 50;
  const EDIT_KEY = 'id';

  const SUBMIT_DELETE = 'delete-school';
  const SUBMIT_EDIT = 'edit-school';
  const SUBMIT_ADD = 'add-school';

  /**
   * @var EditSchoolProcessor the auto-injected school editor.
   */
  private $editSchoolProcessor;

  /**
   * @var boolean true if given user can perform edit operations.
   */
  private $canEdit;

  public function __construct(Account $user) {
    parent::__construct("Schools", $user);
    $this->canEdit = $this->USER->can(Permission::EDIT_SCHOOL_LIST);
  }

  public function fillHTML(Array $args) {
    if ($this->canEdit) {
      if (array_key_exists(self::EDIT_KEY, $args)) {
        $school = DB::getSchool($args[self::EDIT_KEY]);
        if ($school !== null) {
          $this->fillEdit($school, $args);
          return;
        }
        Session::error("Invalid school ID provided.");
      }

      $this->fillNew($args);
    }

    $this->fillList($args);
  }

  private function fillNew(Array $args) {
    $this->PAGE->addContent($p = new XCollapsiblePort("Add new"));
    $url = '/' . $this->pane_url();
    $form = new EditSchoolForm(
      $url,
      new School(),
      array(
        EditSchoolForm::FIELD_URL,
        EditSchoolForm::FIELD_BURGEE,
        EditSchoolForm::FIELD_ID,
        EditSchoolForm::FIELD_NAME,
        EditSchoolForm::FIELD_CONFERENCE,
        EditSchoolForm::FIELD_CITY,
        EditSchoolForm::FIELD_STATE,
      )
    );
    $form->add(new XSubmitP(self::SUBMIT_ADD, "Add"));
    $form->add(new XHiddenInput('csrf_token', Session::getCsrfToken()));
    $p->add($form);
  }

  private function fillEdit(School $school, Array $args) {
    $this->PAGE->addContent(new XP(array(), array(new XA($this->link(), "← Back to list"))));
    $this->PAGE->addContent($p = new XPort("Edit " . $school));
    $url = '/' . $this->pane_url();
    $form = new EditSchoolForm($url, $school, $this->getEditableFields($school));
    $form->add(new XHiddenInput('csrf_token', Session::getCsrfToken()));
    $form->add($xp = new XSubmitP(self::SUBMIT_EDIT, "Edit"));
    try {
      $this->validateDeletion($school);
      $xp->add(new XSubmitDelete(self::SUBMIT_DELETE, "Delete"));
    }
    catch (SoterException $e) {
      // No op
    }
    $p->add($form);
  }

  private function fillList(Array $args) {
    $this->PAGE->addContent($p = new XPort("All schools"));

    $query = DB::$V->incString($args, 'q', 3, 101, null);
    if ($query !== null) {
      $schools = DB::searchSchools($query);
    }
    else {
      $schools = DB::getSchools();
    }
    if (count($schools) == 0) {
      $p->add(new XWarning("There are no active schools in the system."));
      return;
    }

    $whiz = new PageWhiz(count($schools), self::NUM_PER_PAGE, $this->link(), $args);
    $slice = $whiz->getSlice($schools);
    $ldiv = $whiz->getPageLinks();

    $p->add($whiz->getSearchForm($query, 'q'));
    $p->add($ldiv);
    $p->add($this->getSchoolsTable($slice));
    $p->add($ldiv);
  }

  private function getSchoolsTable($schools) {
    $table = new XQuickTable(
      array('class' => 'schools-table'),
      array(
        "ID",
        "Full Name",
        "Short Name",
        "URL",
        "City",
        "State",
        DB::g(STN::CONFERENCE_TITLE),
        "Burgee",
      )
    );

    foreach ($schools as $i => $school) {
      $id = $school->id;
      if ($this->canEdit) {
        $id = new XA(
          $this->link(array(self::EDIT_KEY => $id)),
          $id
        );
      }

      $url = new XExternalA(
        sprintf('http://%s/schools/%s/', Conf::$PUB_HOME, $school->url),
        $school->url
      );

      $table->addRow(
        array(
          $id,
          $school->name,
          $school->nick_name,
          $url,
          $school->city,
          $school->state,
          $school->conference,
          $school->drawSmallBurgee()
        ),
        array('class' => 'row' . ($i % 2))
      );
    }

    return $table;
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_DELETE, $args)) {
      $school = DB::$V->reqSchool($args, 'original-id', "Invalid school provided.");
      $this->validateDeletion($school);
      DB::remove($school);
      Session::info(sprintf("Deleted school \"%s\".", $school));
      $this->redirect($this->pane_url());
      return;
    }

    // ------------------------------------------------------------
    // Edit
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_EDIT, $args)) {
      if (!$this->canEdit) {
        throw new SoterException("No access to edit schools.");
      }
      $school = DB::$V->reqSchool($args, 'original-id', "Invalid school provided.");
      $oldUrl = $school->getURL();

      $editableFields = $this->getEditableFields($school);
      $processor = $this->getEditSchoolProcessor();
      $changed = $processor->process($args, $school, $editableFields);

      $newUrl = $school->getURL();
      if ($newUrl != $oldUrl) {
        require_once('public/UpdateManager.php');
        UpdateManager::queueSchool(
          $school,
          UpdateSchoolRequest::ACTIVITY_URL,
          null,
          $oldUrl
        );
      }
      
      if (count($changed) == 0) {
        Session::warn("No changes requested.");
      }
      else {
        require_once('public/UpdateManager.php');
        UpdateManager::queueSchool(
          $school,
          UpdateSchoolRequest::ACTIVITY_DETAILS
        );
        Session::info(sprintf("Updated school %s.", $school));
      }
      $this->redirect($this->pane_url());
      return;
    }

    // ------------------------------------------------------------
    // Add
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_ADD, $args)) {
      if (!$this->canEdit) {
        throw new SoterException("No access to edit schools.");
      }

      $school = new School();
      $editableFields = $this->getEditableFields($school);
      $processor = $this->getEditSchoolProcessor();
      $processor->process($args, $school, $editableFields);

      require_once('public/UpdateManager.php');
      UpdateManager::queueSchool(
        $school,
        UpdateSchoolRequest::ACTIVITY_DETAILS
      );
      Session::info(sprintf("Added school %s.", $school));
      $this->redirect($this->pane_url());
      return;
    }
  }

  /**
   * I.e. is this school being synced from outside?
   *
   * @param School $school to check.
   * @return true if user can have at all the fields.
   */
  private function isManualUpdateAllowed(School $school) {
    $rootAccount = DB::getRootAccount();
    return $school->created_by != $rootAccount->id;
  }

  /**
   * Ascertains that the given school can be deleted.
   *
   * @param School $school the school to be deleted.
   * @throws SoterException if access violation encountered.
   */
  private function validateDeletion(School $school) {
    if (!$this->canEdit) {
      throw new SoterException("No permission to delete school.");
    }
    if (!$this->isManualUpdateAllowed($school)) {
      throw new SoterException("Specified school cannot be deleted becausen it is being synced externally.");
    }

    if (count($school->getSeasons()) > 0) {
      throw new SoterException("This school has active participation.");
    }
  }

  private function getEditableFields(School $school) {
    $fields = array(
      EditSchoolForm::FIELD_URL,
      EditSchoolForm::FIELD_BURGEE,
      EditSchoolForm::FIELD_NICK_NAME,
    );

    if ($this->isManualUpdateAllowed($school)) {
      $fields[] = EditSchoolForm::FIELD_ID;
      $fields[] = EditSchoolForm::FIELD_NAME;
      $fields[] = EditSchoolForm::FIELD_CONFERENCE;
      $fields[] = EditSchoolForm::FIELD_CITY;
      $fields[] = EditSchoolForm::FIELD_STATE;
    }

    return $fields;
  }

  public function setEditSchoolProcessor(EditSchoolProcessor $processor) {
    $this->editSchoolProcessor = $processor;
  }

  private function getEditSchoolProcessor() {
    if ($this->editSchoolProcessor == null) {
      $this->editSchoolProcessor = new EditSchoolProcessor();
    }
    return $this->editSchoolProcessor;
  }
}