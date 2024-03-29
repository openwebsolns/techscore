<?php
use \model\StudentProfile;
use \ui\BurgeePort;
use \ui\TeamNamesPort;
use \ui\UnregisteredSailorsPort;
use \ui\UserRegattaTable;
use \users\AbstractUserPane;

/**
 * User's home pane, which shows this season's current regattas
 *
 * @author Dayan Paez
 * @created 2012-09-27
 */
class HomePane extends AbstractUserPane {

  /**
   * Creates a new user home page, which displays current season's
   * regattas.
   *
   * @param Account $user the user
   */
  public function __construct(Account $user) {
    parent::__construct("Welcome", $user);
  }

  /**
   * Display table of current season's regattas
   *
   * If there is no current season, an appropriate message is
   * displayed instead.
   *
   * @param Array $args
   */
  protected function fillHTML(Array $args) {

    $canDoSomething = false;

    $studentProfiles = $this->USER->getStudentProfiles();
    if (count($studentProfiles) > 0) {
      $canDoSomething = true;
      $hasSailorRecords = false;
      foreach ($studentProfiles as $studentProfile) {
        if (count($studentProfile->getSailorRecords()) > 0) {
          $hasSailorRecords = true;
        }
        $this->addStudentProfilePort($studentProfile);
      }

      if (!$hasSailorRecords) {
        $this->redirectTo('users\membership\StudentProfilePane');
      }
    }
    elseif ($this->USER->ts_role && $this->USER->ts_role->is_student !== null) {
      $canDoSomething = true;
      $this->PAGE->addContent(new XWarning(array("You are registered as a student, but do not have a student profile. Please visit the ", new XA($this->linkTo('users\membership\RegisterStudentPane'), "sailor registration page"), ".")));
    }

    if ($this->isPermitted('UserSeasonPane')) {
      $canDoSomething = true;
      $this->addInFocusPort();
    }

    if ($this->USER->can(Permission::EDIT_USERS)) {
      $canDoSomething = true;
      $this->addPendingUsersPort();
    }

    // Access for school editors
    $confs = $this->USER->getConferences();
    $schools = $this->USER->getSchools(null, false);

    if ($this->USER->can(Permission::EDIT_UNREGISTERED_SAILORS)) {
      $canDoSomething = true;
      $schoolsWithSailors = $this->USER->getSchoolsWithUnregisteredSailors();
      if (count($schoolsWithSailors) > 0) {
        if (count($schoolsWithSailors) <= 3) {
          foreach ($schoolsWithSailors as $school) {
            $this->PAGE->addContent(new UnregisteredSailorsPort($school));
          }
        }
        else {
          $this->addUnregisteredSummaryPort($schoolsWithSailors);
        }
      }
    }

    if ($this->USER->can(Permission::EDIT_TEAM_NAMES)) {
      $canDoSomething = true;
      if (count($confs) > 0) {
        $this->addTeamNamesSummaryPort($confs);
      }
      else {
        if (count($schools) <= 3) {
          foreach ($schools as $school) {
            $this->PAGE->addContent(new TeamNamesPort($school));
          }
        }
      }
    }

    if ($this->USER->can(Permission::EDIT_SCHOOL_LOGO)) {
      $canDoSomething = true;
      if (count($confs) > 0) {
        $this->addBurgeeSummaryPort($confs);
      }
      else {
        if (count($schools) <= 3) {
          foreach ($schools as $school) {
            $this->PAGE->addContent(new BurgeePort($school));
          }
        }
      }
    }

    // Greet those special beings with nothing to do
    if (!$canDoSomething) {
      $this->PAGE->addContent(
        new XWarning("Hello! It appears you are not affiliated with any schools in the system. As you can see, this severely limits what the program can do for you. Please contact administration as soon as possible to remedy that situation. Happy sailing!")
      );
    }
  }

  private function hasSchoolIn(Regatta $reg, $schools) {
    foreach ($schools as $school) {
      if (count($reg->getTeams($school)) > 0)
        return true;
    }
    return false;
  }

  private function addStudentProfilePort(StudentProfile $profile) {
    $url = $this->linkTo('users\membership\StudentProfilePane');
    $this->PAGE->addContent($p = new XPort(new XA($url, "Student Profile")));
    $p->add(new FItem("Name:", $profile->getName()));
    $p->add(new FItem("School:", $profile->school));
    $p->add(new FItem("Graduation Year:", $profile->graduation_year));

    $records = $profile->getSailorRecords();
    if (count($records) === 1) {
      // vast majority of cases: just print the sailor ID, since everything else
      // ought to match. At least sailor ID is all that is needed to support the
      // All-Academic self-nomination process.
      $p->add(new FItem("Sailor ID:", $records[0]->id));
    }
    if (count($records) > 1) {
      $table = new XQuickTable(
        array('class' => 'profile-sailor-records narrow'),
        array("ID", "Name", "School", "Active")
      );
      foreach ($records as $record) {
        $table->addRow(
          array(
            $record->id,
            $record,
            $record->school,
            $record->active ? "Yes" : "No"
          )
        );
      }
      $p->add(new FItem("Sailors:", $table));
    }
  }

  private function addPendingUsersPort() {
    $pending = DB::getPendingUsers();
    if (($num_pending = count($pending)) > 0) {
      $this->PAGE->addContent($p = new XPort("Pending users"));
      $p->set('id', 'port-pending-users');
      if ($num_pending == 1)
        $p->add(new XP(array(),
                       array("There is one pending account request for ", new XA(WS::link('/pending', array('account'=>$pending[0]->id)), $pending[0]), ".")));
      else
        $p->add(new XP(array(),
                       array("There are ", new XA(WS::link('/pending'), "$num_pending pending account requests"), ".")));
    }
  }

  private function addInFocusPort() {
    $season = Season::forDate(DB::T(DB::NOW));
    if ($season === null) {
      $this->PAGE->addContent($p = new XPort("No season"));
      $p->add(new XWarning(
                     array("There is no current season in the program. Please contact the administrator. No regattas can be created to start in the \"off-season\". You may wish to ",
                           new XA(WS::link('/archive'), "browse the archive"),
                           " instead.")));
    }
    else {
      $start = clone(DB::T(DB::NOW));
      $start->add(new DateInterval('P3DT0H'));
      $start->setTime(0, 0);

      $end = clone(DB::T(DB::NOW));
      $end->sub(new DateInterval('P3DT0H'));
      $end->setTime(0, 0);

      DB::T(DB::REGATTA)->db_set_order(array('start_time' => true));
      $regattas = DB::getAll(DB::T(DB::REGATTA),
                             new DBBool(array(new DBCond('start_time', $start, DBCond::LE),
                                              new DBCond('end_date', $end, DBCond::GE))));
      DB::T(DB::REGATTA)->db_set_order();

      $cur_tab = new UserRegattaTable($this->USER, true);

      $schools = $this->USER->getSchools();

      // Sort all current regattas
      foreach ($regattas as $reg) {
        if ($this->USER->hasJurisdiction($reg) || $this->hasSchoolIn($reg, $schools))
          $cur_tab->addRegatta($reg);
      }

      $this->PAGE->addContent($p = new XPort("In focus"));
      $p->set('id', 'port-in-focus');
      if ($cur_tab->count() > 0)
        $p->add($cur_tab);
      $p->add(new XP(array(),
                     array("See all the regattas for ",
                           new XA(WS::link('/season'), $season->fullString()),
                           " or browse the ",
                           new XA(WS::link('/archive'), "archives"),
                           ".")));
    }
  }

  private function addUnregisteredSummaryPort($schools) {
    $count = count($schools);
    $url = $this->linkTo('users\membership\UnregisteredSailorsPane');
    $this->PAGE->addContent($p = new XPort("Unregistered Sailors"));
    $p->set('id', 'port-unregistered');
      
    $p->add(
      new XP(
        array(),
        array(
          "You have ",
          new XStrong($count),
          " schools with at least one active, unregistered sailor. Visit the ",
          new XA($url, "Membership/Sailors"),
          " page to fix."
        )
      )
    );
  }

  private function addBurgeeSummaryPort($conferences) {
    $schools = DB::getAll(DB::T(DB::ACTIVE_SCHOOL),
                          new DBBool(array(new DBCond('burgee', null),
                                           new DBCondIn('conference', $conferences))));
    $count = count($schools);
    if ($count == 1) {
      $url = $this->linkTo(
        'users\membership\SchoolsPane',
        array('id' => $schools[0]->id)
      );
      $this->PAGE->addContent($p = new XPort("School logo"));
      $p->set('id', 'port-burgee');
      $p->add(
        new XP(
          array(),
          array(
            new XStrong($schools[0]),
            " has no burgee. ",
            new XA($url, "Add one now"),
            "."
          )
        )
      );
    }
    elseif ($count > 1) {
      $url = $this->linkTo('users\membership\SchoolsPane');
      $this->PAGE->addContent($p = new XPort("School logo"));
      $p->set('id', 'port-burgee');
      $p->add(
        new XP(
          array(),
          array(
            "There are ",
            new XStrong($count), 
            " schools with no mascot/logo. Visit the ",
            new XA($url, "Preferences"),
            " page to fix."
          )
        )
      );
    }
  }

  private function addTeamNamesSummaryPort($conferences) {
    $schools = DB::getAll(DB::T(DB::ACTIVE_SCHOOL),
                          new DBBool(array(new DBCondIn('conference', $conferences),
                                           new DBCondIn('id',
                                                        DB::prepGetAll(DB::T(DB::TEAM_NAME_PREFS), null, array('school')),
                                                        DBCondIn::NOT_IN))));
    $count = count($schools);
    if ($count == 1) {
      $url = $this->linkTo(
        'users\membership\SchoolsPane',
        array('id' => $schools[0]->id)
      );
      $this->PAGE->addContent($p = new XPort("Squad Names"));
      $p->set('id', 'port-team-names');
      $p->add(
        new XP(
          array(),
          array(
            new XStrong($schools[0]),
            " has no squad name preferences. ",
            new XA($url, "Add one now"),
            "."
          )
        )
      );
    }
    elseif ($count > 1) {
      $url = $this->linkTo('users\membership\SchoolsPane');
      $this->PAGE->addContent($p = new XPort("Squad Names"));
      $p->set('id', 'port-team-names');
      $p->add(
        new XP(
          array(),
          array(
            "There are ",
            new XStrong($count), 
            " schools with no squad names. Visit the ",
            new XA($url, "Preferences"),
            " page to fix."
          )
        )
      );
    }
  }

  /**
   * This pane does not edit anything
   *
   * @param Array $args can be an empty array
   */
  public function process(Array $args) { throw new SoterException("Nothing to do here."); }

}
