<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manage the mailing list per regatta type and conference
 *
 * @author Dayan Paez
 * @created 2013-03-06
 */
class MailingListManagement extends AbstractAdminUserPane {

  public function __construct(Account $user) {
    parent::__construct("Mailing lists", $user);
    $this->page_url = 'lists';
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Enable summary emails"));
    $p->add($f = $this->createForm());
    $f->add(new XP(array(), "To allow scorers to send e-mail with the daily summaries, check the box below."));
    $f->add($fi = new FItem("Allow mail:", $chk = new XCheckboxInput(STN::SEND_MAIL, 1, array('id'=>'chk-mail'))));
    $fi->add(new XLabel('chk-mail', "Check to allow scorers to send mail with daily summaries."));
    $f->add(new XSubmitP('set-mail', "Save changes"));

    if ((string)DB::g(STN::SEND_MAIL) == 1)
      $chk->set('checked', 'checked');
    else
      return;
    
    $this->PAGE->addContent($p = new XPort("Mailing lists by regatta types"));
    $p->add(new XP(array(), "Scorers have the option of sending a summary e-mail once for each day of competition. This auto-generated message will be sent to the mailing lists associated with that regatta's type. Use the form below to specify which mailing lists to use for each regatta type."));
    $p->add(new XP(array(), "Please note that in all cases, the e-mail will be sent to the participating conferences; so there is no need to specify those below. Enter each e-mail address on a newline."));

    foreach (DB::getAll(DB::$ACTIVE_TYPE) as $type) {
      $p->add($f = $this->createForm());
      $list = $type->mail_lists;
      if ($list === null)
        $list = array();
      $f->add($fi = new FItem($type . ":", new XTextArea('lists', implode("\n", $list))));
      $fi->add(new XHiddenInput('type', $type->id));
      $fi->add(new XSubmitInput('set-lists', "Update"));
    }

    // ------------------------------------------------------------
    // Conferences
    // ------------------------------------------------------------
    $confs = DB::getConferences();
    if (count($confs) > 0) {
      $this->PAGE->addContent($p = new XPort(sprintf("%s mailing lists", DB::g(STN::CONFERENCE_TITLE))));
      $p->add(new XP(array(), sprintf("For each %s, specify the e-mail addresses (one per line) which will receive the daily summary messages.", DB::g(STN::CONFERENCE_TITLE))));

      foreach ($confs as $conf) {
        $p->add($f = $this->createForm());
        $list = $conf->mail_lists;
        if ($list === null)
          $list = array();
        $f->add($fi = new FItem($conf . ":", new XTextArea('lists', implode("\n", $list))));
        $fi->add(new XHiddenInput('conference', $conf->id));
        $fi->add(new XSubmitInput('set-conf-list', "Update"));
      }
    }
  }

  public function process(Array $args) {
    if (isset($args['set-mail'])) {
      DB::s(STN::SEND_MAIL, DB::$V->incInt($args, STN::SEND_MAIL, 1, 2, null));
      Session::pa(new PA("Settings updated."));
    }
    if (isset($args['set-lists'])) {
      $type = DB::$V->reqID($args, 'type', DB::$ACTIVE_TYPE, "Invalid or missing type.");
      $lists = DB::$V->incString($args, 'lists', 1, 16000, null);
      if ($lists !== null)
        $lists = explode(" ", preg_replace('/[\s,]+/', ' ', $lists));
      $type->mail_lists = $lists;
      DB::set($type);
      Session::pa(new PA(sprintf("Updated mailing lists for regattas of type \"%s\".", $type)));
    }
    if (isset($args['set-conf-list'])) {
      $conf = DB::$V->reqID($args, 'conference', DB::$CONFERENCE, "Invalid or missing conference.");
      $lists = DB::$V->incString($args, 'lists', 1, 16000, null);
      if ($lists !== null)
        $lists = explode(" ", preg_replace('/[\s,]+/', ' ', $lists));
      $conf->mail_lists = $lists;
      DB::set($conf);
      Session::pa(new PA(sprintf("Updated e-mail addresses for \"%s\" conference.", $conf)));
    }
  }
}
?>