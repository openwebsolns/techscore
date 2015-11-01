<?php
namespace users\admin;

use \Account;
use \DB;
use \Conf;
use \Permission;
use \School;
use \STN;
use \xml5\XExternalA;
use \xml5\PageWhiz;

use \FReqItem;
use \XA;
use \XCollapsiblePort;
use \XHiddenInput;
use \XP;
use \XPort;
use \XQuickTable;
use \XSpan;
use \XStrong;
use \XTextInput;
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

  const REGEX_ID = '^[A-Za-z0-9-]+$';
  const REGEX_URL = '^[a-z0-9-]+$';

  /**
   * @var boolean false if syncing from outside entity.
   */
  private $manualUpdateAllowed;

  /**
   * @var boolean true if given user can perform edit operations.
   */
  private $canEdit;

  public function __construct(Account $user) {
    parent::__construct("Schools", $user);
    $this->manualUpdateAllowed = DB::g(STN::SCHOOL_API_URL) == null;
    $this->canEdit = $this->USER->can(Permission::EDIT_SCHOOL_LIST);
  }

  public function fillHTML(Array $args) {
    if ($this->canEdit) {
      if (array_key_exists(self::EDIT_KEY, $args)) {
        $school = DB::getSchool($args[self::EDIT_KEY]);
        if ($school !== null) {
          if ($this->manualUpdateAllowed) {
            $this->fillEditManual($school, $args);
          }
          else {
            $this->fillEditNonManual($school, $args);
          }
          return;
        }
        Session::error("Invalid school ID provided.");
      }

      if ($this->manualUpdateAllowed) {
        $this->fillNew($args);
      }
    }

    $this->fillList($args);
  }

  private function fillNew(Array $args) {
    $this->PAGE->addContent($p = new XCollapsiblePort("Add new"));
    $p->add($form = $this->createForm());
  }

  private function fillEditManual(School $school, Array $args) {
    $this->PAGE->addContent(new XP(array(), array(new XA($this->link(), "← Back to list"))));
    $this->PAGE->addContent($p = new XPort("Edit " . $school));
    $p->add($form = $this->createForm());

    $form->add(
        new FReqItem(
          "ID:",
          new XTextInput('id', $school->id, array('pattern' => self::REGEX_ID)),
          "Must be unique. Allowed characters: A-Z, 0-9, and hyphens (-)."
        )
    );
    $form->add(new XHiddenInput('original-id', $school->id));
  }

  private function fillEditNonManual(School $school, Array $args) {
    $this->PAGE->addContent(new XP(array(), array(new XA($this->link(), "← Back to list"))));
    $this->PAGE->addContent($p = new XPort("Edit " . $school));
    $p->add($form = $this->createForm());

    $form->add(new FReqItem("ID:", new XStrong($school->id)));
    $form->add(new XHiddenInput('original-id', $school->id));
    $form->add(new FReqItem("Name:", new XStrong($school->name)));
    $form->add(
      new FReqItem(
        "URL slug:",
        new XTextInput('url', $school->url, array('pattern' => self::REGEX_URL)),
        "Allowed characters are \"a-z\", 0-9, and hyphens (-)."
      )
    );
    $form->add(new FReqItem(DB::g(STN::CONFERENCE_TITLE) . ":", new XStrong($school->conference)));
    $form->add(new FReqItem("City:", new XStrong($school->city)));
    $form->add(new FReqItem("State:", new XStrong($school->state)));
    $form->add(new FReqItem("Burgee:", $school->drawBurgee()));
  }

  private function fillList(Array $args) {
    $this->PAGE->addContent($p = new XPort("All schools"));
    $schools = DB::getSchools();
    if (count($schools) == 0) {
      $p->add(new XWarning("There are no active schools in the system."));
      return;
    }

    $whiz = new PageWhiz(count($schools), self::NUM_PER_PAGE, $this->link(), $args);
    $slice = $whiz->getSlice($schools);
    $ldiv = $whiz->getPageLinks();

    $p->add($ldiv);
    $p->add($this->getSchoolsTable($slice));
    $p->add($ldiv);
  }

  private function getSchoolsTable(Array $schools) {
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
    
  }
}