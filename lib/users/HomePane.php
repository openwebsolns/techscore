<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

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
    // ------------------------------------------------------------
    // Pending users
    // ------------------------------------------------------------
    if ($this->USER->can(Permission::EDIT_USERS)) {
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

    // ------------------------------------------------------------
    // Messages
    // ------------------------------------------------------------
    $num_messages = count(DB::getUnreadMessages($this->USER));
    if ($num_messages > 0) {
      $this->PAGE->addContent($p = new XPort("Messages"));
      $p->set('id', 'port-messages');
      $p->add($para = new XP(array(), "You have "));
      if ($num_messages == 1)
        $para->add(new XA("inbox", "1 unread message."));
      else
        $para->add(new XA("inbox", "$num_messages unread messages."));
    }

    // ------------------------------------------------------------
    // Regattas
    // ------------------------------------------------------------
    if ($this->isPermitted('UserSeasonPane')) {
      $season = Season::forDate(DB::$NOW);
      if ($season === null) {
        $this->PAGE->addContent($p = new XPort("No season"));
        $p->add(new XP(array('class'=>'warning'),
                       array("There is no current season in the program. Please contact the administrator. No regattas can be created to start in the \"off-season\". You may wish to ",
                             new XA(WS::link('/archive'), "browse the archive"),
                             " instead.")));
      }
      else {
        $start = clone(DB::$NOW);
        $start->add(new DateInterval('P3DT0H'));
        $start->setTime(0, 0);

        $end = clone(DB::$NOW);
        $end->sub(new DateInterval('P3DT0H'));
        $end->setTime(0, 0);

        require_once('regatta/Regatta.php');
        DB::$REGATTA->db_set_order(array('start_time' => true));
        $regattas = DB::getAll(DB::$REGATTA,
                               new DBBool(array(new DBCond('start_time', $start, DBCond::LE),
                                                new DBCond('end_date', $end, DBCond::GE))));
        DB::$REGATTA->db_set_order();

        require_once('xml5/UserRegattaTable.php');
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

    // Access for school editors
    if ($this->isPermitted('PrefsHomePane')) {
    // ------------------------------------------------------------
    // Unregistered sailors
    // ------------------------------------------------------------
      $sailors = $this->SCHOOL->getUnregisteredSailors();
      if (count($sailors) > 0) {
        $lnk = WS::link(sprintf('/prefs/%s/sailor', $this->SCHOOL->id));
        $this->PAGE->addContent($p = new XPort(new XA($lnk, "Unreg. sailors for " . $this->SCHOOL->nick_name)));
        $p->set('id', 'port-unregistered');
        $limit = 5;
        if (count($sailors) > 5)
          $limit = 4;
        $p->add($ul = new XUl());
        for ($i = 0; $i < $limit; $i++)
          $ul->add(new XLi($sailors[$i]));
        if ($limit == 4)
          $ul->add(new XLi(new XEm(sprintf("%d more...", (count($sailors) - $limit)))));
      }

      // ------------------------------------------------------------
      // Mascot/burgee
      // ------------------------------------------------------------
      $lnk = WS::link(sprintf('/prefs/%s/logo', $this->SCHOOL->id));
      $this->PAGE->addContent($p = new XPort(new XA($lnk, $this->SCHOOL->nick_name . " logo")));
      $p->set('id', 'port-burgee');
      if ($this->SCHOOL->burgee === null)
        $p->add(new XP(array('class'=>'message'),
                       new XA($lnk, "Add one now")));
      else
        $p->add(new XP(array('class'=>'burgee-cell'),
                       new XA($lnk, new XImg('data:image/png;base64,'.$this->SCHOOL->burgee->filedata, $this->SCHOOL->nick_name))));

      // ------------------------------------------------------------
      // Team names
      // ------------------------------------------------------------
      $lnk = sprintf('/prefs/%s/team', $this->SCHOOL->id);
      $this->PAGE->addContent($p = new XPort(new XA($lnk, "Team names for " . $this->SCHOOL->nick_name)));
      $p->set('id', 'port-team-names');
      $names = $this->SCHOOL->getTeamNames();
      if (count($names) == 0)
        $p->add(new XP(array('class'=>'warning'),
                       array(new XStrong("Note:"), " There are no team names for your school. ",
                             new XA(WS::link($lnk), "Add one now"),
                             ".")));
      else {
        $p->add($ul = new XOl());
        foreach ($names as $name)
          $ul->add(new XLi($name));
      }
    }
  }

  private function hasSchoolIn(Regatta $reg, $schools) {
    foreach ($schools as $school) {
      if (count($reg->getTeams($school)) > 0)
        return true;
    }
    return false;
  }

  /**
   * This pane does not edit anything
   *
   * @param Array $args can be an empty array
   */
  public function process(Array $args) { throw new SoterException("Nothing to do here."); }
}
?>