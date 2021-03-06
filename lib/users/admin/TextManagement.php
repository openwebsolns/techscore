<?php
namespace users\admin;

use \users\AbstractUserPane;

use \Account;
use \DB;
use \Session;
use \SoterException;
use \Text_Entry;
use \TSEditor;
use \UpdateManager;
use \UpdateSeasonRequest;

use \XA;
use \XDiv;
use \XEm;
use \XHiddenInput;
use \XP;
use \XPort;
use \XQuickTable;
use \XRawText;
use \XSubmitP;
use \XTextEditor;

/**
 * Edit the messages used throughout the public/private site
 *
 * @author Dayan Paez
 * @created 2013-03-12
 */
class TextManagement extends AbstractUserPane {

  const INPUT_CONTENT = 'content';
  const INPUT_SECTION = 'section';

  private $sections;

  /**
   * Creates a new text-management pane
   *
   * @param Account $user the user
   */
  public function __construct(Account $user) {
    parent::__construct("Edit text", $user);
    $this->sections = Text_Entry::getSections();
  }

  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific section
    // ------------------------------------------------------------
    if (array_key_exists(self::INPUT_SECTION, $args)) {
      try {
        $section = DB::$V->reqKey($args, self::INPUT_SECTION, $this->sections, "Invalid text section to edit.");
        $this->fillText($args, $section);
        return;
      }
      catch (SoterException $e) {
        Session::error($e->getMessage());
      }
    }

    // ------------------------------------------------------------
    // List of sections to edit
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Text entries"));
    $p->add($tab = new XQuickTable(array('class' => 'text-entries'),
                                   array("Name", "Description", "Current Value")));

    $i = 0;
    foreach ($this->sections as $section => $name) {
      $entry = DB::get(DB::T(DB::TEXT_ENTRY), $section);
      $display = new XEm("No entry exists.");
      if ($entry !== null)
        $display = new XDiv(array('class'=>'entry-preview'), array(new XRawText($entry->html)));
      
      $tab->addRow(
        array(
          new XA($this->link(array(self::INPUT_SECTION => $section)), $name),
          $this->getExplanation($section),
          $display
        ),
        array('class' => 'row' . ($i++ % 2))
      );
    }
  }

  private function fillText(Array $args, $section) {
    $this->setupTextEditors(array(self::INPUT_CONTENT));

    $this->PAGE->addContent($p = new XPort("Edit " . $section));
    $p->add(new XP(array(), $this->getExplanation($section)));

    $entry = DB::get(DB::T(DB::TEXT_ENTRY), $section);
    if ($entry === null)
      $entry = new Text_Entry();

    $p->add($f = $this->createForm());
    $f->add(new XTextEditor('content', 'content', $entry->plain));

    $f->add($xp = new XSubmitP('set-text', "Save changes"));
    $xp->add(new XHiddenInput(self::INPUT_SECTION, $section));
    $xp->add(new XA($this->link(), "Cancel"));
  }

  public function process(Array $args) {
    $section = DB::$V->reqKey($args, self::INPUT_SECTION, $this->sections, "Invalid or missing text section to edit.");
    $entry = DB::get(DB::T(DB::TEXT_ENTRY), $section);
    if ($entry === null) {
      $entry = new Text_Entry();
      $entry->id = $section;
    }

    $input = DB::$V->incRaw($args, self::INPUT_CONTENT, 1, 10000);
    if ($input == $entry->plain) {
      Session::warn("Nothing changed.");
      return;
    }

    require_once('xml5/TSEditor.php');
    $DPE = new TSEditor();
    $entry->plain = $input;
    if ($input === null) {
      $entry->html = null;
    }
    else {
      $html = new XDiv(array(), $DPE->parse($input));
      $entry->html = $html->toXML();
    }
    DB::set($entry);

    // Notify appropriate updates (requires seasons)
    $seasons = DB::getAll(DB::T(DB::SEASON));
    if (count($seasons) > 0) {
      switch ($section) {
      case Text_Entry::WELCOME:
        UpdateManager::queueSeason($seasons[0], UpdateSeasonRequest::ACTIVITY_FRONT);
        break;

      case Text_Entry::GENERAL_404:
        UpdateManager::queueSeason($seasons[0], UpdateSeasonRequest::ACTIVITY_404);
        break;

      case Text_Entry::SCHOOL_404:
        UpdateManager::queueSeason($seasons[0], UpdateSeasonRequest::ACTIVITY_SCHOOL_404);
        break;
      }
    }

    Session::info(sprintf("Updated text section for \"%s\".",  $this->sections[$section]));
  }

  private function getExplanation($section) {
    switch ($section) {
    case Text_Entry::ANNOUNCEMENTS:
      return "Announcements are shown on the scoring login page. They provide a quick way to convey general information to the scorers and future registrants.";

    case Text_Entry::WELCOME:
      return "This message is shown in the \"Welcome\" box of the public home page.";

    case Text_Entry::GENERAL_404:
      return "This is the body of the 404 page shown with invalid URLs.";

    case Text_Entry::SCHOOL_404:
      return "This is the body of the 404 page shown with invalid URLs within the schools section.";

    case Text_Entry::EULA:
      return "The End-User License Agreement that is shown to new users.";

    case Text_Entry::SAILOR_EULA:
      return "The End-User License Agreement that is shown to new student registrants.";

    case Text_Entry::REGISTER_MESSAGE:
      return "This is an optional message to be provided in the new user registration pane.";

    case Text_Entry::SAILOR_REGISTER_MESSAGE:
      return "The message displayed on the registration page before students sign up.";

    default:
      return "";
    }
  }
}
