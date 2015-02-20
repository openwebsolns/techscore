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
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Enable summary emails"));
    $p->add($f = $this->createForm());
    $f->add(new XP(array(), "To allow scorers to send e-mail with the daily summaries, check the box below."));
    $f->add(new FItem("Allow mail:", new FCheckbox(STN::SEND_MAIL, 1, "Check to allow scorers to send mail with daily summaries.", (string)DB::g(STN::SEND_MAIL) == 1)));
    $f->add(new XSubmitP('set-mail', "Save changes"));

    if (!(string)DB::g(STN::SEND_MAIL) == 1)
      return;
    
    $this->PAGE->addContent($p = new XPort("Mailing lists by regatta types"));
    $p->add(new XP(array(), "Scorers have the option of sending a summary e-mail once for each day of competition. This auto-generated message will be sent to the mailing lists associated with that regatta's type. Use the form below to specify which mailing lists to use for each regatta type."));
    $p->add(new XP(array(), "Please note that in all cases, the e-mail will be sent to the participating conferences; so there is no need to specify those below. Enter each e-mail address on a newline."));

    $p->add($f = $this->createForm());
    foreach (DB::getAll(DB::T(DB::ACTIVE_TYPE)) as $type) {
      $list = $type->mail_lists;
      if ($list === null)
        $list = array();
      $f->add($fi = new FItem($type . ":", new XTextArea('lists[]', implode("\n", $list))));
      $fi->add(new XHiddenInput('type[]', $type->id));
    }
    $f->add(new XSubmitP('set-lists', "Update"));

    // ------------------------------------------------------------
    // Conferences
    // ------------------------------------------------------------
    $confs = DB::getConferences();
    if (count($confs) > 0) {
      $this->PAGE->addContent($p = new XPort(sprintf("%s mailing lists", DB::g(STN::CONFERENCE_TITLE))));
      $p->add(new XP(array(), sprintf("For each %s, specify the e-mail addresses (one per line) which will receive the daily summary messages.", DB::g(STN::CONFERENCE_TITLE))));

      $p->add($f = $this->createForm());
      foreach ($confs as $conf) {
        $list = $conf->mail_lists;
        if ($list === null)
          $list = array();
        $f->add($fi = new FItem($conf . ":", new XTextArea('lists[]', implode("\n", $list))));
        $fi->add(new XHiddenInput('conference[]', $conf->id));
      }
      $f->add(new XSubmitP('set-conf-list', "Update"));
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Checkbox
    // ------------------------------------------------------------
    if (isset($args['set-mail'])) {
      DB::s(STN::SEND_MAIL, DB::$V->incInt($args, STN::SEND_MAIL, 1, 2, null));
      Session::pa(new PA("Settings updated."));
    }

    // ------------------------------------------------------------
    // By regatta type
    // ------------------------------------------------------------
    if (isset($args['set-lists'])) {
      $types = array();
      $map = DB::$V->reqMap($args, array('type', 'lists'));
      foreach ($map['type'] as $i => $id) {
        $type = DB::get(DB::T(DB::ACTIVE_TYPE), $id);
        if ($type === null) {
          throw new SoterException(sprintf("Invalid regatta type provided: %s.", $id));
        }
        $lists = $map['lists'][$i];
        if ($lists !== null)
          $lists = explode(" ", preg_replace('/[\s,]+/', ' ', $lists));
        $type->mail_lists = $lists;
        $types[] = $type;
      }

      // update
      foreach ($types as $type) {
        DB::set($type);
      }
      Session::pa(new PA("Updated mailing lists associated with regatta types."));
    }

    // ------------------------------------------------------------
    // By conference
    // ------------------------------------------------------------
    if (isset($args['set-conf-list'])) {
      $confs = array();
      $map = DB::$V->reqMap($args, array('conference', 'lists'));
      foreach ($map['conference'] as $i => $id) {
        $conf = DB::getConference($id);
        if ($conf === null) {
          throw new SoterException(sprintf("Invalid conference ID provided: %s.", $id));
        }
        $lists = $map['lists'][$i];
        if ($lists !== null)
          $lists = explode(" ", preg_replace('/[\s,]+/', ' ', $lists));
        $conf->mail_lists = $lists;
        $confs[] = $conf;
      }

      // update
      foreach ($confs as $conf) {
        DB::set($conf);
      }
      Session::pa(new PA("Updated mailing lists associated with conference."));
    }
  }
}
?>