<?php
namespace users\membership;

use \users\AbstractUserPane;
use \xml5\GraduationYearInput;
use \xml5\PageWhiz;
use \xml5\SailorPageWhizCreator;
use \xml5\XExternalA;

use \Account;
use \DB;
use \Conf;
use \Permission;
use \Sailor;
use \SoterException;
use \STN;

use \FItem;
use \FReqItem;
use \XA;
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

  const PORT_EDIT = "Edit sailor";
  const PORT_LIST = "All sailors";

  const FIELD_FIRST_NAME = 'first_name';
  const FIELD_LAST_NAME = 'last_name';
  const FIELD_YEAR = 'year';
  const FIELD_REGISTERED_ID = 'registered_id';
  const FIELD_GENDER = 'gender';
  const FIELD_URL = 'url';

  const REGEX_URL = '^[a-z0-9]+[a-z0-9-]*[a-z0-9]+$';

  /**
   * @var boolean true if given user can perform edit operations.
   */
  private $canEdit;

  public function __construct(Account $user) {
    parent::__construct("Sailors", $user);
    $this->canEdit = $this->USER->can(Permission::EDIT_SAILOR_LIST);
  }

  public function fillHTML(Array $args) {
    if (array_key_exists(self::EDIT_KEY, $args)) {
      try {
        $sailor = DB::$V->reqID($args, self::EDIT_KEY, DB::T(DB::SAILOR), "Invalid sailor ID provided.");
        $this->fillSailor($sailor, $args);
        return;
      }
      catch (SoterException $e) {
        Session::error($e->getMessage());
      }
    }

    $this->fillList($args);
  }

  private function fillSailor(Sailor $sailor, Array $args) {
    $this->PAGE->addContent(new XP(array(), array(new XA($this->link(), "← Back to list"))));
    $this->PAGE->addContent($p = new XPort(self::PORT_EDIT));
    $p->add($form = $this->createForm());
    $form->add(
      new FReqItem(
        "First name:",
        new XTextInput(self::FIELD_FIRST_NAME, $sailor->first_name)
      )
    );
    $form->add(
      new FReqItem(
        "Last name:",
        new XTextInput(self::FIELD_LAST_NAME, $sailor->last_name)
      )
    );
    $form->add(
      new FReqItem(
        "Graduation year:",
        new GraduationYearInput(self::FIELD_YEAR, $sailor->year)
      )
    );
    if (!$sailor->isRegistered()) {
      $orgname = DB::g(STN::ORG_NAME);
      $form->add(
        new FItem(
          "Merge with registered:",
          XSelect::fromDBM(
            self::FIELD_REGISTERED_ID,
            $sailor->school->getSailors(),
            null,
            array(),
            ''
          ),
          "This is an unregistered sailor. Choose this option to merge this sailor's sailing record with a registered one."
        )
      );
    }

    $genders = array('' => '');
    foreach (Sailor::getGenders() as $key => $val) {
      $genders[$key] = $val;
    }
    $form->add(
      new FReqItem(
        "Gender:",
        XSelect::fromArray(
          self::FIELD_GENDER,
          $genders,
          $sailor->gender
        )
      )
    );
    if (DB::g(STN::SAILOR_PROFILES) !== null) {
      $form->add(
        new FItem(
          "URL slug:",
          new XTextInput('url', $sailor->url, array('pattern' => self::REGEX_URL)),
          "Must be lowercase letters, numbers, and hyphens (-). Leave blank to auto-generate a URL based on sailor name."
        )
      );
    }
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
    throw new SoterException("Not yet implemented.");
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
    return $this->USER->hasSchool($sailor->school);
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
}