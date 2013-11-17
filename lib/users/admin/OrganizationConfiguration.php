<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Configure organization parameters such as name, URL, etc.
 *
 * @author Dayan Paez
 * @version 2013-11-16
 */
class OrganizationConfiguration extends AbstractAdminUserPane {

  public function __construct(Account $user) {
    parent::__construct("Organization settings", $user);
    $this->page_url = 'org';
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("General settings"));
    $p->add(new XP(array(), "These parameters should be set once to indicate the name and URL of the organization to link to from the public site."));
    $p->add($f = $this->createForm());
    $f->add(new FItem("Name:", new XTextInput(STN::ORG_NAME, DB::g(STN::ORG_NAME), array('maxlength'=>50))));

    $f->add($fi = new FItem("URL:", new XInput('url', STN::ORG_URL, DB::g(STN::ORG_URL))));
    $fi->add(new XMessage("Include protocol, i.e. \"http://\""));

    $f->add($fi = new FItem("Team URL:", new XInput('url', STN::ORG_TEAMS_URL, DB::g(STN::ORG_TEAMS_URL))));
    $fi->add(new XMessage("Full URL (with protocol) to list of teams. Optional."));

    $f->add(new XSubmitP('set-params', "Save changes"));
  }

  public function process(Array $args) {
    if (isset($args['set-params'])) {
      $changed = false;
      $val = DB::$V->incString($args, STN::ORG_NAME, 1, 51);
      if ($val != DB::g(STN::ORG_NAME)) {
        $changed = true;
        DB::s(STN::ORG_NAME, $val);
      }

      $val = DB::$V->incString($args, STN::ORG_URL, 1, 1001);
      if ($val != DB::g(STN::ORG_URL)) {
        $changed = true;
        DB::s(STN::ORG_URL, $val);
      }

      $val = DB::$V->incString($args, STN::ORG_TEAMS_URL, 1, 1001);
      if ($val != DB::g(STN::ORG_TEAMS_URL)) {
        $changed = true;
        DB::s(STN::ORG_TEAMS_URL, $val);
      }

      if (!$changed)
        throw new SoterException("No changes to save.");
      Session::pa(new PA("Saved settings. Changes will take effect on future pages."));
    }
  }
}
?>