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

  private static $TEMPLATES = array(STN::RP_SINGLEHANDED => "Singlehanded",
                                    STN::RP_1_DIVISION => "1 Division",
                                    STN::RP_2_DIVISION => "2 Divisions",
                                    STN::RP_3_DIVISION => "3 Divisions",
                                    STN::RP_4_DIVISION => "4 Divisions",
                                    STN::RP_TEAM_RACE => "Team racing");

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

    $f->add(new FItem("Default regatta start time:", new XInput('time', STN::DEFAULT_START_TIME, DB::g(STN::DEFAULT_START_TIME))));

    $f->add(new XSubmitP('set-params', "Save changes"));

    $this->PAGE->addContent($p = new XPort("RP Form Templates"));
    $p->add(new XP(array(),
                   array("To have the  program generate PDF RP forms from the information entered, you must create and install an RP form writer by extending ", new XVar("AbstractRpForm"), " from the ", new XVar("lib/rpwriter"), " directory. Use the form below to specify the classname of the template to be used for each of the given regatta formats.")));
    $p->add(new XP(array(),
                   array("To install a template, create a file with the same name as the classname (and with a ", new XVar(".php"), " extension), which subclasses ", new XVar("AbstractRpForm"), ". Then, specify the classname below.")));

    $p->add($f = $this->createForm());
    foreach (self::$TEMPLATES as $setting => $title) {
      $val = DB::g($setting);
      $mes = null;
      if ($val !== null) {
        try {
          $this->verifyRpTemplate($val);
        } catch (SoterException $e) {
          $val = null;
          $mes = $e->getMessage();
        }
      }
      $f->add(new FItem($title . ":", new XTextInput($setting, $val), $mes));
    }
    $f->add(new XSubmitP('set-rp-templates', "Save changes"));
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

      $val = null;
      if (!empty($args[STN::DEFAULT_START_TIME])) {
        $val = DB::$V->reqDate($args, STN::DEFAULT_START_TIME, null, null, "Invalid time format.");
        $val = $val->format('H:i');
      }
      if ($val != DB::g(STN::DEFAULT_START_TIME)) {
        $changed = true;
        DB::s(STN::DEFAULT_START_TIME, $val);
      }

      if (!$changed)
        throw new SoterException("No changes to save.");
      Session::pa(new PA("Saved settings. Changes will take effect on future pages."));
    }

    // ------------------------------------------------------------
    // RP Templates
    // ------------------------------------------------------------
    if (isset($args['set-rp-templates'])) {
      $changed = false;
      foreach (self::$TEMPLATES as $setting => $title) {
        $val = DB::$V->incString($args, $setting, 1, 101);
        if ($val !== null) {
          $val = basename($val);
          $this->verifyRpTemplate($val);
        }
        if ($val !== DB::g($setting)) {
          DB::s($setting, $val);
          $changed = true;

          // Queue all regattas of this type
          $updated = 0;
          require_once('public/UpdateManager.php');
          foreach ($this->getRegattasByRpType($setting) as $reg) {
            $rp = $reg->getRpManager();
            $rp->updateLog();
            $updated++;
          }

          if ($updated > 0)
            Session::pa(new PA(sprintf("Updated PDF RP form for %d regattas.", $updated)));
        }
      }

      if (!$changed)
        throw new SoterException("No changes to save.");
      Session::pa(new PA("Set the RP forms."));
    }
  }

  private function verifyRpTemplate($classname) {
    if (class_exists($classname, false)) {
      $obj = new $classname("", "", "", "");
      if ($obj instanceof AbstractRpForm)
        return true;
      throw new SoterException(sprintf("Classname %s exists and does not subclass AbstractRpForm.", $classname));
    }

    $path = sprintf('%s/rpwriter/%s.php', dirname(dirname(__DIR__)), $classname);
    if (!file_exists($path))
      throw new SoterException("File does not exist. Expected path " . $path);

    ob_start();
    require_once($path);
    $len = ob_get_length();
    ob_end_clean();
    if ($len > 0)
      throw new SoterException("File invalid because it echoes to standard output.");

    if (!class_exists($classname, false))
      throw new SoterException("File does not define class " . $classname);

    $obj = new $classname("", "", "", "");
    if (!($obj instanceof AbstractRpForm))
      throw new SoterException(sprintf("Class %s does not subclass AbstractRpForm."));

    return true;
  }

  /**
   * Fetches regattas based on RP form type
   *
   * @param Const $type one of the STN::RP_* settings.
   * @return Array:Regatta
   */
  private function getRegattasByRpType($type) {
    require_once('regatta/Regatta.php');
    $cond = null;
    switch ($type) {
    case STN::RP_SINGLEHANDED:
      $cond = new DBCond('dt_singlehanded', null, DBCond::NE);
      break;

    case STN::RP_1_DIVISION:
      $cond = new DBBool(array(new DBCond('dt_singlehanded', null),
                               new DBCond('scoring', Regatta::SCORING_STANDARD),
                               new DBCond('dt_num_divisions', 1)));
      break;

    case STN::RP_2_DIVISION:
      $cond = new DBBool(array(new DBCond('dt_num_divisions', 2),
                               new DBCond('scoring', Regatta::SCORING_TEAM, DBCond::NE)));
      break;

    case STN::RP_3_DIVISION:
      $cond = new DBBool(array(new DBCond('dt_num_divisions', 3),
                               new DBCond('scoring', Regatta::SCORING_TEAM, DBCond::NE)));
      break;

    case STN::RP_4_DIVISION:
      $cond = new DBBool(array(new DBCond('dt_num_divisions', 4),
                               new DBCond('scoring', Regatta::SCORING_TEAM, DBCond::NE)));
      break;

    case STN::RP_TEAM_RACE:
      $cond = new DBCond('scoring', Regatta::SCORING_TEAM);
      break;

    default:
      throw new InvalidArgumentException("Unknown regatta RP type: $type.");
    }

    return DB::getAll(DB::$REGATTA, $cond);
  }
}
?>