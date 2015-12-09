<?php
namespace users\membership;

use \users\AbstractUserPane;
use \users\membership\tools\EditSailorForm;
use \xml5\PageWhiz;
use \xml5\SailorPageWhizCreator;
use \xml5\XExternalA;

use \Account;
use \DB;
use \Conf;
use \Permission;
use \Sailor;
use \Session;
use \SoterException;
use \STN;

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

  const PORT_ADD = "Add sailor";
  const PORT_EDIT = "Edit sailor";
  const PORT_LIST = "All sailors";

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

    if ($this->USER->can(Permission::ADD_SCHOOL)) {
      $this->fillAddNew($args);
    }
    $this->fillList($args);
  }

  private function fillAddNew(Array $args) {
    $this->PAGE->addContent($p = new XCollapsiblePort(self::PORT_ADD));
    $p->add(new EditSailorForm($this->link(), new Sailor()));
    $p->add(new XSubmitP(self::SUBMIT_ADD, "Add"));
  }

  private function fillSailor(Sailor $sailor, Array $args) {
    $this->PAGE->addContent(new XP(array(), array(new XA($this->link(), "← Back to list"))));

    $this->PAGE->addContent($p = new XPort(self::PORT_EDIT));
    $p->add($form = new EditSailorForm($this->link(), $sailor));
    
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
      $sailor = DB::$V->reqID($args, self::FIELD_ID, DB::T(DB::SAILOR), "Invalid sailor provided.");
      if (!$this->canEdit($sailor)) {
        throw new SoterException("No permission to edit given sailor.");
      }
      $sailor->first_name = DB::$V->reqString($args, self::FIELD_FIRST_NAME, 1, 200, "Invalid first name provided.");
      $sailor->last_name = DB::$V->reqString($args, self::FIELD_LAST_NAME, 1, 200, "Invalid last name provided.");
      $sailor->year = DB::$V->reqInt($args, self::FIELD_YEAR, 1970, 3001, "Invalid year provided.");
      $sailor->gender = DB::$V->reqKey($args, self::FIELD_GENDER, Sailor::getGenders(), "Invalid gender provided.");
      if (!$sailor->isRegistered()) {
        $otherSailor = DB::$V->incID($args, self::FIELD_REGISTERED_ID, DB::T(DB::SAILOR));
        // TODO
      }

      // If URL was requested, then enforce no collision
      if (DB::$V->incString($args, self::FIELD_URL, 1) != null) {
        $matches = DB::$V->reqRE(
          $args,
          self::FIELD_URL,
          DB::addRegexDelimiters(self::REGEX_URL),
          "Nonconformant URL provided."
        );

        $url = $matches[0];
        if ($url != $sailor->url) {
          // collision
          $otherSailor = DB::getSailorByUrl($url);
          if ($otherSailor !== null) {
            throw new SoterException(
              sprintf("Chosen URL belongs to another sailor (%s).", $otherSailor)
            );
          }
          $sailor->url = $url;
        }
      }
      else {
        $name = $sailor->getName();
        $seeds = array($name);
        if ($sailor->year > 0) {
          $seeds[] = $name . " " . $sailor->year;
        }
        $seeds[] = $name . " " . $sailor->school->nick_name;
        $url = DB::createUrlSlug(
          $seeds,
          function ($slug) use ($sailor) {
            $other = DB::getSailorByUrl($slug);
            return ($other === null || $other->id == $sailor->id);
          }
        );
        $sailor->url = $url;
      }

      DB::set($sailor);
      Session::info("Updated sailor information.");
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