<?php
namespace users\membership;

use \ui\ImageInputWithPreview;
use \ui\SchoolTeamNamesInput;
use \users\AbstractUserPane;
use \users\membership\tools\EditSchoolForm;
use \users\membership\tools\EditSchoolProcessor;
use \users\membership\tools\SchoolTeamNamesProcessor;
use \users\utils\burgees\AssociateBurgeesToSchoolHelper;
use \utils\SailorSearcher;
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

use \FReqItem;
use \XA;
use \XCollapsiblePort;
use \XHiddenInput;
use \XLi;
use \XP;
use \XPort;
use \XQuickTable;
use \XSpan;
use \XStrong;
use \XSubmitDelete;
use \XSubmitInput;
use \XSubmitP;
use \XUl;
use \XWarning;

/**
 * Manage the list of schools, depending on settings.
 *
 * This page shows all the schools that the account has access to,
 * with options to edit if the user can do so based on assigned
 * permissions.
 *
 * @author Dayan Paez
 * @version 2015-11-05
 */
class SchoolsPane extends AbstractUserPane {

  const NUM_PER_PAGE = 50;
  const EDIT_KEY = 'id';
  const SEARCH_KEY = 'q';

  const SUBMIT_DELETE = 'delete-school';
  const SUBMIT_EDIT = 'edit-school';
  const SUBMIT_ADD = 'add-school';
  const SUBMIT_EDIT_NAMES = 'edit-school-names';
  const SUBMIT_SET_LOGO = 'set-logo';
  const SUBMIT_DELETE_LOGO = 'delete-logo';

  const PORT_ADD = "Add new";
  const PORT_EDIT = "General properties";
  const PORT_LIST = "All schools";
  const PORT_SQUAD_NAMES = "Squad names";
  const PORT_LOGO = "Logo";

  const FIELD_BURGEE = 'burgee';
  const FIELD_SCHOOL_ID = 'school';

  /**
   * @var EditSchoolProcessor the auto-injected school editor.
   */
  private $editSchoolProcessor;

  /**
   * @var AssociateBurgeesToSchoolHelper for processing burgees.
   */
  private $burgeeHelper;

  /**
   * @var SchoolTeamNamesProcessor auto-injected processor.
   */
  private $teamNamesProcessor;

  /**
   * @var boolean true if given user can add a new school.
   */
  private $canAdd;

  public function __construct(Account $user) {
    parent::__construct("Schools", $user);
    $this->canAdd = $this->USER->can(Permission::ADD_SCHOOL);
  }

  public function fillHTML(Array $args) {
    if (array_key_exists(self::EDIT_KEY, $args)) {
      try {
        $school = $this->getSchoolById($args[self::EDIT_KEY]);
        $this->fillEdit($school, $args);
        return;
      }
      catch (SoterException $e) {
        Session::error($e->getMessage());
      }
    }

    if ($this->canAdd) {
      $this->fillNew($args);
    }

    $this->fillList($args);
  }

  private function fillNew(Array $args) {
    $this->PAGE->addContent($p = new XCollapsiblePort(self::PORT_ADD));
    $url = '/' . $this->pane_url();
    $form = new EditSchoolForm(
      $url,
      new School(),
      array(
        EditSchoolForm::FIELD_URL,
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
    $editableFields = $this->getEditableFields($school);
    $this->fillEditSettings($school, $editableFields);
    if ($this->USER->can(Permission::EDIT_SCHOOL_LOGO)) {
      $this->fillEditLogo($school, $args);
    }
    if ($this->USER->can(Permission::EDIT_TEAM_NAMES)) {
      $this->fillEditTeamNames($school, $args);
    }
  }

  private function fillEditSettings(School $school, Array $editableFields) {
    $this->PAGE->addContent($p = new XPort(self::PORT_EDIT));
    $url = '/' . $this->pane_url();
    $form = new EditSchoolForm($url, $school, $editableFields);
    $form->add(new XHiddenInput('csrf_token', Session::getCsrfToken()));
    $form->add($xp = new XP(array('class' => XSubmitP::CLASSNAME)));
    if (count($editableFields) > 0) {
      $xp->add(new XSubmitInput(self::SUBMIT_EDIT, "Edit"));
    }
    try {
      $this->validateDeletion($school);
      $xp->add(new XSubmitDelete(self::SUBMIT_DELETE, "Delete"));
    }
    catch (SoterException $e) {
      // No op
    }
    $p->add($form);
  }

  private function fillEditTeamNames(School $school, Array $args) {
    $this->PAGE->addContent($p = new XPort(self::PORT_SQUAD_NAMES));
    $p->add(new XP(array(), "Enter all possible squad names (usually a variation of the school's mascot) in the list below. There may be a squad name for coed teams, and a different name for women teams. Or a school may have a varsity and junior varsity combination, etc."));
    $p->add(new XP(array(), array("When a team from this school is added to a regatta, the ", new XStrong("primary"), " squad name (first on the list below) will be chosen automatically. Later, the scorer or the school's coach may choose an alternate name from those specified in the list below.")));
    $p->add(new XP(array(), array("The squad names should all be different. ", new XStrong("Squad names may not be differentiated with the simple addition of a numeral suffix."), " This will be done automatically by the program.")));

    $p->add($form = $this->createForm());
    $form->add(new XHiddenInput(self::FIELD_SCHOOL_ID, $school->id));
    $form->add(new FReqItem("Team names:", new SchoolTeamNamesInput($school)));
    $form->add(new XSubmitP(self::SUBMIT_EDIT_NAMES, "Save changes"));
  }

  private function fillEditLogo(School $school, Array $args) {
    $this->PAGE->addContent($p = new XPort(self::PORT_LOGO));
    $p->add(new XP(array(), "Guidelines for school logo:"));
    $p->add(
      new XUl(
        array(),
        array(
          new XLi("File can be no larger than 200 KB in size."),
          new XLi(array("Only ", new XStrong("PNG"), " and ", new XStrong("GIF"), " images are allowed.")),
          new XLi("The image used should have a transparent background, so that it looks appropriate throught the application."),
          new XLi(array("All images will be resized to fit in an aspect ratio of 3:2. We ", new XStrong("strongly recommend"), " properly cropping the image prior to uploading.")),
        )
      )
    );

    $p->add($form = $this->createFileForm());
    $form->add(new XHiddenInput(self::FIELD_SCHOOL_ID, $school->id));
    $form->add(new FReqItem("Logo:", new ImageInputWithPreview(self::FIELD_BURGEE, $school->drawBurgee())));
    $form->add($xp = new XSubmitP(self::SUBMIT_SET_LOGO, "Edit logo"));
    if ($school->burgee !== null) {
      $xp->add(new XSubmitDelete(self::SUBMIT_DELETE_LOGO, "Delete"));
    }
  }

  private function fillList(Array $args) {
    $this->PAGE->addContent($p = new XPort(self::PORT_LIST));

    $query = DB::$V->incString($args, self::SEARCH_KEY, 3, 101, null);
    if ($query !== null) {
      $schools = $this->USER->searchSchools($query);
    }
    else {
      $schools = $this->USER->getSchools();
      if (count($schools) == 0) {
        $p->add(new XWarning("No active schools to display."));
        return;
      }
    }

    $whiz = new PageWhiz(count($schools), self::NUM_PER_PAGE, $this->link(), $args);
    $slice = $whiz->getSlice($schools);
    $ldiv = $whiz->getPageLinks();

    $p->add($whiz->getSearchForm($query, self::SEARCH_KEY));
    $p->add($ldiv);
    if (count($slice) > 0) {
      $p->add($this->getSchoolsTable($slice));
    }
    $p->add($ldiv);
  }

  private function getSchoolsTable($schools) {
    $canEdit = $this->USER->canAny(
      array(
        Permission::EDIT_SCHOOL,
        Permission::EDIT_SCHOOL_LOGO,
        Permission::EDIT_TEAM_NAMES,
        Permission::EDIT_UNREGISTERED_SAILORS,
      )
    );

    $table = new XQuickTable(
      array('class' => 'schools-table'),
      array(
        "ID",
        "Full Name",
        "Short Name",
        "URL",
        "City",
        DB::g(STN::CONFERENCE_TITLE),
        "Burgee",
        "Roster",
      )
    );

    foreach ($schools as $i => $school) {
      $id = $school->id;
      if ($canEdit) {
        $id = new XA(
          $this->link(array(self::EDIT_KEY => $id)),
          $id
        );
      }

      $url = '';
      if ($school->url !== null) {
        $url = new XExternalA(
          sprintf('http://%s/schools/%s/', Conf::$PUB_HOME, $school->url),
          $school->url
        );
      }

      $rosterLink = sprintf("%d sailor(s)", count($school->getSailors()));
      if ($this->USER->canAny(array(Permission::VIEW_SAILOR_LIST, Permission::EDIT_SAILOR_LIST))) {
        $rosterLink = new XA(
          $this->linkTo(
            'users\membership\SailorsPane',
            array(SailorSearcher::FIELD_SCHOOL => $school->id)
          ),
          $rosterLink
        );
      }

      $table->addRow(
        array(
          $id,
          $school->name,
          $school->nick_name,
          $url,
          sprintf('%s, %s', $school->city, $school->state),
          $school->conference,
          $school->drawSmallBurgee(),
          $rosterLink,
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
      $school = $this->getSchoolById(
        DB::$V->reqString($args, 'original-id', 1, 100, "Invalid school provided.")
      );
      $oldUrl = $school->getURL();

      $editableFields = $this->getEditableFields($school);
      $processor = $this->getEditSchoolProcessor();
      $changed = $processor->process($args, $school, $editableFields);

      $newUrl = $school->getURL();
      if ($newUrl != $oldUrl) {
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
        UpdateManager::queueSchool(
          $school,
          UpdateSchoolRequest::ACTIVITY_DETAILS
        );
        Session::info(sprintf("Updated school %s.", $school));
      }
      $this->redirect($this->pane_url(), array(self::EDIT_KEY => $school->id));
      return;
    }

    // ------------------------------------------------------------
    // Add
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_ADD, $args)) {
      if (!$this->canAdd) {
        throw new SoterException("No access to add schools.");
      }

      $school = new School();
      $editableFields = $this->getEditableFields($school);
      $processor = $this->getEditSchoolProcessor();
      $processor->process($args, $school, $editableFields);

      UpdateManager::queueSchool(
        $school,
        UpdateSchoolRequest::ACTIVITY_DETAILS
      );
      Session::info(sprintf("Added school %s.", $school));
      $this->redirect($this->pane_url(), array(self::EDIT_KEY => $school->id));
      return;
    }

    // ------------------------------------------------------------
    // Edit team names
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_EDIT_NAMES, $args)) {
      $school = DB::$V->reqSchool($args, self::FIELD_SCHOOL_ID, "Invalid school provided.");
      if (!$this->USER->can(Permission::EDIT_TEAM_NAMES)) {
        throw new SoterException("No access to edit team names.");
      }

      $list = DB::$V->reqList($args, 'name', null, "No list of names provided.");
      $processor = $this->getTeamNamesProcessor();
      $names = $processor->processNames($school, $list);
      Session::info(sprintf("Set %d team name(s) for %s.", count($names), $school));
      return;
    }

    // ------------------------------------------------------------
    // Set burgee
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_SET_LOGO, $args)) {
      $school = DB::$V->reqSchool($args, self::FIELD_SCHOOL_ID, "Invalid school provided.");
      if (!$this->USER->can(Permission::EDIT_SCHOOL_LOGO)) {
        throw new SoterException("No access to edit school logo.");
      }
      $this->processBurgee($school, $args);
      Session::info("Set new team logo.");
    }

    // ------------------------------------------------------------
    // Delete burgee
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_DELETE_LOGO, $args)) {
      $school = DB::$V->reqSchool($args, self::FIELD_SCHOOL_ID, "Invalid school provided.");
      if (!$this->USER->can(Permission::EDIT_SCHOOL_LOGO)) {
        throw new SoterException("No access to edit school logo.");
      }
      $helper = $this->getAssociateBurgeesHelper();
      $helper->removeBurgee($school);
      Session::info("Removed team logo.");
    }
  }

  /**
   * Processes the burgee for the given school from POST.
   *
   * @param School $school the school to process.
   * @param Array $args the posted parameters.
   * @return boolean true if the burgee changed.
   */
  private function processBurgee(School $school, Array $args) {
    $file = DB::$V->incFile($args, self::FIELD_BURGEE, 1, 200000);
    if ($file === null) {
      return false;
    }

    $helper = $this->getAssociateBurgeesHelper();
    $helper->setBurgee(
      Conf::$USER,
      $school,
      $file['tmp_name']
    );
    return true;
  }

  /**
   * Retrieves the school with the given ID if allowed.
   *
   * @param String $id the ID to search.
   * @return School with ID, if user has access to it.
   * @throws SoterException with invalid ID or permission.
   */
  private function getSchoolById($id) {
    $school = DB::getSchool($id);
    if ($school === null) {
      throw new SoterException("Invalid school ID provided.");
    }
    if (!$this->USER->hasSchool($school)) {
      throw new SoterException(
        sprintf("No permission to edit school %s.", $school)
      );
    }
    return $school;
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
    if (!$this->canAdd) {
      throw new SoterException("No permission to delete school.");
    }
    if (!$this->isManualUpdateAllowed($school)) {
      throw new SoterException("Specified school cannot be deleted because it is being synced externally.");
    }

    if (count($school->getSeasons()) > 0) {
      throw new SoterException("This school has active participation.");
    }
  }

  private function getEditableFields(School $school) {
    if (!$this->USER->hasSchool($school)) {
      return array();
    }

    $fields = array();

    if ($this->USER->can(Permission::EDIT_SCHOOL)) {
      $fields[] = EditSchoolForm::FIELD_URL;
      $fields[] = EditSchoolForm::FIELD_NICK_NAME;

      if ($this->isManualUpdateAllowed($school)) {
        $fields[] = EditSchoolForm::FIELD_ID;
        $fields[] = EditSchoolForm::FIELD_NAME;
        $fields[] = EditSchoolForm::FIELD_CITY;
        $fields[] = EditSchoolForm::FIELD_STATE;
        if ($this->USER->can(Permission::EDIT_CONFERENCE_LIST)) {
          $fields[] = EditSchoolForm::FIELD_CONFERENCE;
        }
      }
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

  public function setTeamNamesProcessor(SchoolTeamNamesProcessor $processor) {
    $this->teamNamesProcessor = $processor;
  }

  private function getTeamNamesProcessor() {
    if ($this->teamNamesProcessor == null) {
      $this->teamNamesProcessor = new SchoolTeamNamesProcessor();
    }
    return $this->teamNamesProcessor;
  }

  /**
   * Inject the processor for burgees.
   *
   * @param BurgeeProcessor $processor the new processor.
   */
  public function setAssociateBurgeesHelper(AssociateBurgeesToSchoolHelper $processor) {
    $this->burgeeHelper = $processor;
  }

  private function getAssociateBurgeesHelper() {
    if ($this->burgeeHelper == null) {
      $this->burgeeHelper = new AssociateBurgeesToSchoolHelper();
    }
    return $this->burgeeHelper;
  }

}