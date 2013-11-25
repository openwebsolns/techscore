<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/super/AbstractSuperUserPane.php');

/**
 * Manage the global settings for this installation of Techscore
 *
 * @author Dayan Paez
 * @created 2013-11-24
 */
class GlobalSettings extends AbstractSuperUserPane {

  public function __construct(Account $user) {
    parent::__construct("Global settings", $user);
    $this->page_url = 'conf';
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("General parameters"));
    $p->add($f = $this->createForm());

    $f->add(new FItem("Application Name:", new XTextInput(STN::APP_NAME, DB::g(STN::APP_NAME), array('maxlength'=>50))));
    $f->add(new FItem("Send e-mails from:", new XTextInput(STN::TS_FROM_MAIL, DB::g(STN::TS_FROM_MAIL))));

    $f->add(new XSubmitP('set-params', "Save changes"));
  }

  public function process(Array $args) {
    if (isset($args['set-params'])) {
      $changed = false;

      $val = DB::$V->reqString($args, STN::APP_NAME, 1, 51, "Invalid application name provided.");
      if ($val != DB::g(STN::APP_NAME)) {
        $changed = true;
        DB::s(STN::APP_NAME, $val);
      }

      $val = DB::$V->reqString($args, STN::TS_FROM_MAIL, 1, 1001, "No from address provided.");
      if ($val != DB::g(STN::TS_FROM_MAIL)) {
        $changed = true;
        DB::s(STN::TS_FROM_MAIL, $val);
      }

      if (!$changed)
        throw new SoterException("No changes to save.");
      Session::pa(new PA("Saved settings."));
    }
  }
}
?>