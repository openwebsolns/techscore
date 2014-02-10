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
    $f->add(new FItem("Version:", new XTextInput(STN::APP_VERSION, DB::g(STN::APP_VERSION))));
    $f->add(new FItem("Copyright:", new XTextInput(STN::APP_COPYRIGHT, DB::g(STN::APP_COPYRIGHT))));
    $f->add(new FItem("Send e-mails from:", new XTextInput(STN::TS_FROM_MAIL, DB::g(STN::TS_FROM_MAIL))));

    $f->add(new FItem("Conference name:", new XTextInput(STN::CONFERENCE_TITLE, DB::g(STN::CONFERENCE_TITLE))));
    $f->add(new FItem("Conf. abbreviation:", new XTextInput(STN::CONFERENCE_SHORT, DB::g(STN::CONFERENCE_SHORT))));

    $f->add(new FItem("Divert e-mails to:", new XInput('email', STN::DIVERT_MAIL, DB::g(STN::DIVERT_MAIL)), "For production, this value should be blank"));

    $f->add(new FItem("Sailor API URL:", new XInput('url', STN::SAILOR_API_URL, DB::g(STN::SAILOR_API_URL), array('size'=>60))));
    $f->add(new FItem("Coach API URL:", new XInput('url', STN::COACH_API_URL, DB::g(STN::COACH_API_URL), array('size'=>60))));
    $f->add(new FItem("School API URL:", new XInput('url', STN::SCHOOL_API_URL, DB::g(STN::SCHOOL_API_URL), array('size'=>60))));

    $f->add(new FItem("Help base URL:", new XInput('url', STN::HELP_HOME, DB::g(STN::HELP_HOME), array('size'=>60))));

    // Scoring options
    $n = STN::SCORING_OPTIONS . '[]';
    $lst = Regatta::getScoringOptions();
    foreach (array(Regatta::SCORING_STANDARD => "Standard fleet scoring",
                   Regatta::SCORING_COMBINED => "Combined fleet scoring",
                   Regatta::SCORING_TEAM => "Team racing") as $setting => $desc) {
      $id = 'chk-' . $setting;
      $f->add($fi = new FItem("", $chk = new XCheckboxInput($n, $setting, array('id'=>$id))));
      $fi->add(new XLabel($id, sprintf("Allow %s", $desc)));
      if (isset($lst[$setting]))
        $chk->set('checked', 'checked');
    }

    $f->add($fi = new FItem("Allow cross RP?", $chk = new XCheckboxInput(STN::ALLOW_CROSS_RP, 1, array('id'=>'chk-' . STN::ALLOW_CROSS_RP))));
    $fi->add(new XLabel('chk-' . STN::ALLOW_CROSS_RP, "RP entries may contain teams from other schools in the regatta."));
    if (DB::g(STN::ALLOW_CROSS_RP) !== null)
      $chk->set('checked', 'checked');

    $f->add(new FItem("PDF Socket:", new XTextInput(STN::PDFLATEX_SOCKET, DB::g(STN::PDFLATEX_SOCKET)), "Full path, or leave blank to use \"exec\" function."));

    $f->add(new XSubmitP('set-params', "Save changes"));
  }

  public function process(Array $args) {
    if (isset($args['set-params'])) {
      $changed = false;

      foreach (array(STN::APP_NAME => "application name",
                     STN::CONFERENCE_TITLE => "conference title",
                     STN::CONFERENCE_SHORT => "conference abbreviation",
                     STN::APP_VERSION => "version",
                     STN::APP_COPYRIGHT => "copyright") as $setting => $title) {
        $val = DB::$V->reqString($args, $setting, 1, 101, sprintf("Invalid %s provided.", $title));
        if ($val != DB::g($setting)) {
          $changed = true;
          DB::s($setting, $val);
        }
      }

      $val = DB::$V->reqString($args, STN::TS_FROM_MAIL, 1, 1001, "No from address provided.");
      if ($val != DB::g(STN::TS_FROM_MAIL)) {
        $changed = true;
        DB::s(STN::TS_FROM_MAIL, $val);
      }

      $val = DB::$V->incString($args, STN::DIVERT_MAIL, 1, 101);
      if ($val != DB::g(STN::DIVERT_MAIL)) {
        $changed = true;
        DB::s(STN::DIVERT_MAIL, $val);
      }

      foreach (array(STN::SAILOR_API_URL,
                     STN::COACH_API_URL,
                     STN::SCHOOL_API_URL,
                     STN::HELP_HOME) as $setting) {
        $val = DB::$V->incRE($args, $setting, '_^https?://.{5,}$_', array(null));
        if ($val[0] != DB::g($setting)) {
          $changed = true;
          DB::s($setting, $val[0]);
        }
      }

      // Scoring options
      $opts = array();
      $list = DB::$V->reqList($args, STN::SCORING_OPTIONS, null, "No list of scoring options provided.");
      $is_different = false;
      $current = Regatta::getScoringOptions();
      $valid = array(Regatta::SCORING_STANDARD,
                     Regatta::SCORING_COMBINED,
                     Regatta::SCORING_TEAM);
      foreach ($list as $opt) {
        if (!in_array($opt, $valid))
          throw new SoterException("Invalid scoring option provided: $opt.");
        if (!isset($current[$opt]))
          $is_different = true;
        else
          unset($current[$opt]);
        $opts[] = $opt;
      }
      if (count($opts) == 0)
        throw new SoterException("No scoring options makes for a useless program.");
      if ($is_different || count($current) > 0) {
        $changed = true;
        DB::s(STN::SCORING_OPTIONS, implode("\0", $opts));
      }

      $val = DB::$V->incInt($args, STN::ALLOW_CROSS_RP, 1, 2, null);
      if ($val != DB::g(STN::ALLOW_CROSS_RP)) {
        $changed = true;
        DB::s(STN::ALLOW_CROSS_RP, $val);
      }

      $val = DB::$V->incString($args, STN::PDFLATEX_SOCKET, 1, 101);
      if ($val != DB::g(STN::PDFLATEX_SOCKET)) {
        $changed = true;
        DB::s(STN::PDFLATEX_SOCKET, $val);
      }

      if (!$changed)
        throw new SoterException("No changes to save.");
      Session::pa(new PA("Saved settings."));
    }
  }
}
?>