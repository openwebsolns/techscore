<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Edit the messages used throughout the public/private site
 *
 * @author Dayan Paez
 * @created 2013-03-12
 */
class TextManagement extends AbstractAdminUserPane {

  private $section;

  /**
   * Creates a new text-management pane
   *
   * @param Account $user the user
   * @param Const $section the specific section
   */
  public function __construct(Account $user, $section) {
    parent::__construct("Text for $section", $user);
    $this->section = $section;
    $secs = Text_Entry::getSections();
    if (!isset($secs[$section]))
      throw new InvalidArgumentException("Invalid section provided: $section.");
    $this->page_url = sprintf('text/%s', $section);
  }

  public function fillHTML(Array $args) {
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/DPEditor.js')));
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/DPEditorUI.js')));
    $this->PAGE->head->add(new XScript('text/javascript', null,
                                       'window.onload = function(evt) { new DPEditor("content").uiInit();};'));

    $this->PAGE->addContent($p = new XPort("Edit " . $this->section));
    $this->fillExplanation($p);

    $entry = DB::get(DB::$TEXT_ENTRY, $this->section);
    if ($entry === null)
      $entry = new Text_Entry();

    $p->add($f = $this->createForm());
    $f->add(new XDiv(array(), array(new XTextArea('content', $entry->plain,
                                                  array('id'=>'content', 'cols'=>'80', 'rows'=>'10')))));
    $f->add(new XSubmitP('set-text', "Save changes"));
  }

  public function process(Array $args) {
    $entry = DB::get(DB::$TEXT_ENTRY, $this->section);
    if ($entry === null) {
      $entry = new Text_Entry();
      $entry->id = $this->section;
    }

    $input = DB::$V->incRaw($args, 'content', 1, 10000);
    if ($input == $entry->plain) {
      Session::pa(new PA("Nothing changed.", PA::I));
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
    Session::pa(new PA("Updated text section for " . $this->section . "."));
  }

  private function fillExplanation(XPort $p) {
    switch ($this->section) {
    case Text_Entry::ANNOUNCEMENTS:
      $p->add(new XP(array(), "Announcements are shown on the scoring login page. They provide a quick way to convey general information to the scorers and future registrants."));
    }
  }
}
?>