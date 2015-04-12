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
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = $this->createForm());
    $p->add($f = new XPort("General parameters"));
    

    $f->add(new FReqItem("Application Name:", new XTextInput(STN::APP_NAME, DB::g(STN::APP_NAME), array('maxlength'=>50))));
    $f->add(new FReqItem("Version:", new XTextInput(STN::APP_VERSION, DB::g(STN::APP_VERSION))));
    $f->add(new FItem("Copyright:", new XTextInput(STN::APP_COPYRIGHT, DB::g(STN::APP_COPYRIGHT))));
    $f->add(new FItem("Send e-mails from:", new XTextInput(STN::TS_FROM_MAIL, DB::g(STN::TS_FROM_MAIL))));
    $f->add(new FItem("Divert e-mails to:", new XEmailInput(STN::DIVERT_MAIL, DB::g(STN::DIVERT_MAIL)), "For production, this value should be blank"));
    $f->add(new FItem("Help base URL:", new XUrlInput(STN::HELP_HOME, DB::g(STN::HELP_HOME), array('size'=>60))));

    $p->add($f = new XPort("Conference Settings"));
    $f->add(new FReqItem("Conference name:", new XTextInput(STN::CONFERENCE_TITLE, DB::g(STN::CONFERENCE_TITLE))));
    $f->add(new FReqItem("Conf. abbreviation:", new XTextInput(STN::CONFERENCE_SHORT, DB::g(STN::CONFERENCE_SHORT))));
    $f->add(new FReqItem("Conferences URL:", new XTextInput(STN::CONFERENCE_URL, DB::g(STN::CONFERENCE_URL))));
    $f->add(new FItem("Conference Pages", new FCheckbox(STN::PUBLISH_CONFERENCE_SUMMARY, 1, "Publish conference summary pages in public site.", DB::g(STN::PUBLISH_CONFERENCE_SUMMARY) !== null)));


    $p->add($f = new XPort("Database Sync"));
    $f->add(new FItem("Sailor API URL:", new XUrlInput(STN::SAILOR_API_URL, DB::g(STN::SAILOR_API_URL), array('size'=>60))));
    $f->add(new FItem("Coach API URL:", new XUrlInput(STN::COACH_API_URL, DB::g(STN::COACH_API_URL), array('size'=>60))));
    $f->add(new FItem("School API URL:", new XUrlInput(STN::SCHOOL_API_URL, DB::g(STN::SCHOOL_API_URL), array('size'=>60))));
    $f->add(new FItem("Unique sailors/season?", new FCheckbox(STN::UNIQUE_SEASON_SAILOR, 1, "Enforce unique IDs for sailors from one season to the next.", DB::g(STN::UNIQUE_SEASON_SAILOR) !== null)));


    $p->add($f = new XPort("Scoring Options"));
    // Scoring options
    $n = STN::SCORING_OPTIONS . '[]';
    $lst = Regatta::getScoringOptions();
    foreach (array(Regatta::SCORING_STANDARD => "Standard fleet scoring",
                   Regatta::SCORING_COMBINED => "Combined fleet scoring",
                   Regatta::SCORING_TEAM => "Team racing") as $setting => $desc) {
      $id = 'chk-' . $setting;
      $f->add(new FItem("", new FCheckbox($n, $setting, sprintf("Allow %s", $desc), isset($lst[$setting]))));
    }

    $f->add(new FItem("Allow cross RP?", new FCheckbox(STN::ALLOW_CROSS_RP, 1, "RP entries may contain teams from other schools in the system.", DB::g(STN::ALLOW_CROSS_RP) !== null)));

    $f->add(new FItem("Allow reserves?", new FCheckbox(STN::ALLOW_RESERVES, 1, "Prompt users for reserve/attendee information.", DB::g(STN::ALLOW_CROSS_RP) !== null)));

    $f->add(new FItem("Allow Host Venue?", new FCheckbox(STN::ALLOW_HOST_VENUE, 1, "Allow scorers to manually specify the regatta host.", DB::g(STN::ALLOW_HOST_VENUE) !== null)));


    $p->add($f = new XPort("System Settings"));
    $f->add(new FItem("PDF Socket:", new XTextInput(STN::PDFLATEX_SOCKET, DB::g(STN::PDFLATEX_SOCKET)), "Full path, or leave blank to use \"exec\" function."));
    $f->add(new FItem("Notice board limit:", new XNumberInput(STN::NOTICE_BOARD_SIZE, DB::g(STN::NOTICE_BOARD_SIZE), 1), "Size in bytes for each item."));

    $p->add($f = new XPort("Features"));
    $f->add(new FItem("Auto-merge sailors:", new FCheckbox(STN::AUTO_MERGE_SAILORS, 1, "Auto-merge unregistered sailors on a daily basis.", DB::g(STN::AUTO_MERGE_SAILORS) !== null)));
    $f->add(new FItem("Regatta sponsors:", new FCheckbox(STN::REGATTA_SPONSORS, 1, "Allow scorers to choose from list of sponsors at regatta level.", DB::g(STN::REGATTA_SPONSORS) !== null)));
    $f->add(new FItem("Sailor profiles:", new FCheckbox(STN::SAILOR_PROFILES, 1, "Publish sailor profiles on public site.", DB::g(STN::SAILOR_PROFILES) !== null)));

    $p->add(new XSubmitP('set-params', "Save changes"));
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

      $val = DB::$V->incInt($args, STN::ALLOW_RESERVES, 1, 2, null);
      if ($val != DB::g(STN::ALLOW_RESERVES)) {
        $changed = true;
        DB::s(STN::ALLOW_RESERVES, $val);
      }

      $val = DB::$V->incInt($args, STN::ALLOW_HOST_VENUE, 1, 2, null);
      if ($val != DB::g(STN::ALLOW_HOST_VENUE)) {
        $changed = true;
        DB::s(STN::ALLOW_HOST_VENUE, $val);
      }

      $val = DB::$V->incInt($args, STN::PUBLISH_CONFERENCE_SUMMARY, 1, 2, null);
      if ($val != DB::g(STN::PUBLISH_CONFERENCE_SUMMARY)) {
        $changed = true;
        DB::s(STN::PUBLISH_CONFERENCE_SUMMARY, $val);

        require_once('public/UpdateManager.php');
        foreach (DB::getConferences() as $conf) {
          if ($conf->url === null && $val !== null) {
            $conf->url = $conf->createUrl();
            DB::set($conf);
          }
          UpdateManager::queueConference(
            $conf,
            UpdateConferenceRequest::ACTIVITY_DISPLAY,
            null, // season
            $conf->url
          );
        }
      }

      $val = DB::$V->reqString($args, STN::CONFERENCE_URL, 1, 101, "Invalid conference URL");
      if ($val != DB::g(STN::CONFERENCE_URL)) {
        $changed = true;

        // Queue deletion of old URLs
        require_once('public/UpdateManager.php');
        foreach (DB::getConferences() as $conf) {
          if ($conf->url !== null) {
            UpdateManager::queueConference(
              $conf,
              UpdateConferenceRequest::ACTIVITY_URL,
              null, // season
              $conf->url
            );
          }
          $conf->url = $conf->createUrl();
          DB::set($conf);
        }
        DB::s(STN::CONFERENCE_URL, $val);
      }

      $val = DB::$V->incString($args, STN::UNIQUE_SEASON_SAILOR, 1, 2);
      if ($val != DB::g(STN::UNIQUE_SEASON_SAILOR)) {
        $changed = true;
        DB::s(STN::UNIQUE_SEASON_SAILOR, $val);
      }

      $val = DB::$V->incString($args, STN::PDFLATEX_SOCKET, 1, 101);
      if ($val != DB::g(STN::PDFLATEX_SOCKET)) {
        $changed = true;
        DB::s(STN::PDFLATEX_SOCKET, $val);
      }

      $val = DB::$V->reqInt($args, STN::NOTICE_BOARD_SIZE, 100, 16777215, "Invalid notice size provided (must be between 100B and 16MB.");
      if ($val != DB::g(STN::NOTICE_BOARD_SIZE)) {
        $changed = true;
        DB::s(STN::NOTICE_BOARD_SIZE, $val);
      }

      $val = DB::$V->incInt($args, STN::AUTO_MERGE_SAILORS, 1, 2, null);
      if ($val != DB::g(STN::AUTO_MERGE_SAILORS)) {
        $changed = true;
        DB::s(STN::AUTO_MERGE_SAILORS, $val);
      }

      $val = DB::$V->incInt($args, STN::REGATTA_SPONSORS, 1, 2, null);
      if ($val != DB::g(STN::REGATTA_SPONSORS)) {
        $changed = true;
        DB::s(STN::REGATTA_SPONSORS, $val);
        if ($val !== null) {
          Session::pa(new PA(
                        array("To make sponsors available to regattas, you will need to ",
                              new XA(WS::link('/sponsor'), "configure the list of sponsors"),
                              ". You may also wish to ",
                              new XA(WS::link('/roles'), "grant the appropriate permission"),
                              " to one or more roles."
                        ),
                        PA::I));
        }
      }

      $val = DB::$V->incInt($args, STN::SAILOR_PROFILES, 1, 2, null);
      if ($val != DB::g(STN::SAILOR_PROFILES)) {
        $changed = true;
        DB::s(STN::SAILOR_PROFILES, $val);
      }

      if (!$changed)
        throw new SoterException("No changes to save.");
      Session::pa(new PA("Saved settings."));
    }
  }
}
?>