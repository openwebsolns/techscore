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
    if ($this->USER->isAdmin()) {
      $pending = DB::getPendingUsers();
      if (($num_pending = count($pending)) > 0) {
        $this->PAGE->addContent($p = new XPort("Pending users"));
        $p->set('id', 'port-pending-users');
        if ($num_pending == 1)
          $p->add(new XP(array(),
                         array("There is one pending account request for ", new XA(WS::link('/pending'), $pending[0]), ".")));
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
                                              new DBCond('end_date', DB::$NOW, DBCond::GE))));
      DB::$REGATTA->db_set_order();

      $headers = array("Inv", "Name");
      if ($this->USER->isAdmin())
        $headers[] = "Host(s)";
      $headers[] = "Date";
      $headers[] = "Type";
      $headers[] = "Scoring";
      $headers[] = "Finalized";
      $cur_tab = new XQuickTable(array('class'=>'regatta-list-current'), $headers);
      $num_cur = 0;

      $schools = $this->USER->getSchools();

      // Sort all current regattas
      foreach ($regattas as $reg) {
        $is_participant = false;
        $inv = null;
        if ($this->USER->hasJurisdiction($reg))
          $inv = new XImg(WS::link('/inc/img/scoring.png'), "Scoring");
        elseif ($this->hasSchoolIn($reg, $schools)) {
          $inv = new XImg(WS::link('/inc/img/part.png'), "Part.");
          $is_participant = true;
        }
        else
          continue;

        $link = new XA('/score/' . $reg->id, $reg->name);
        $row = array($inv, $link);

        if ($this->USER->isAdmin()) {
          $hosts = array();
          foreach ($reg->getHosts() as $host)
            $hosts[$host->id] = $host->id;
          $row[] = implode("/", $hosts);
        }

        $finalized = '--';
        if ($reg->finalized !== null) {
          $is_complete = true;
          $rpm = $reg->getRpManager();

          if ($is_participant) {
            foreach ($schools as $school) {
              foreach ($reg->getTeams($school) as $team) {
                if (!$rpm->isComplete($team)) {
                  $finalized = new XA(WS::link(sprintf('/score/%s/rp?chosen_team=%s', $reg->id, $team->id)), "Missing RP",
                                      array('class'=>'stat missing-rp',
                                            'title'=>"At least one skipper/crew is missing."));
                  $is_complete = false;
                  break;
                }
              }
              if ($is_complete)
                break;
            }
          }
          elseif (!$rpm->isComplete()) {
            $is_complete = false;
            $finalized = new XA(WS::link(sprintf('/score/%s/rp', $reg->id)), "Missing RP",
                                array('class'=>'stat missing-rp',
                                      'title'=>"At least one skipper/crew is missing."));
          }
          if ($is_complete)
            $finalized = $reg->finalized->format("Y-m-d");
        }
        elseif ($reg->end_date < DB::$NOW) {
          if (count($reg->getTeams()) == 0 || count($reg->getRaces()) == 0)
            $finalized = new XSpan("Incomplete", array('class'=>'stat incomplete', 'title'=>"Missing races or teams."));
          elseif ($reg->private === null && !$reg->hasFinishes())
            $finalized = new XA(WS::link(sprintf('/score/%s/finishes', $reg->id)), "No finishes",
                                array('class'=>'stat empty',
                                      'title'=>"No finishes entered"));
          else
            $finalized = new XA(WS::link('/score/'.$reg->id.'#finalize'), "Pending",
                                array('title'=>'Regatta must be finalized!',
                                      'class'=>'stat pending'));
        }

        $scoring = ucfirst($reg->scoring);
        if ($reg->isSinglehanded())
          $scoring = "Singlehanded";
        $row[] = $reg->start_time->format("Y-m-d");
        $row[] = $reg->type;
        $row[] = $scoring;
        $row[] = $finalized;

        $class = "";
        if ($reg->private)
          $class = 'personal-regatta ';
        $cur_tab->addRow($row, array('class' => $class . 'row'.($num_cur++ % 2)));
      }

      // Add the tables
      $this->PAGE->addContent($p = new XPort("In focus"));
      $p->set('id', 'port-in-focus');
      if ($num_cur > 0)
        $p->add($cur_tab);
      $p->add(new XP(array(),
                     array("See all the regattas for ",
                           new XA(WS::link('/season'), $season->fullString()),
                           " or browse the ",
                           new XA(WS::link('/archive'), "archives"),
                           ".")));
    }

    // ------------------------------------------------------------
    // Mascot/burgee
    // ------------------------------------------------------------
    $lnk = WS::link(sprintf('/prefs/%s/logo', $this->USER->school->id));
    $this->PAGE->addContent($p = new XPort(new XA($lnk, $this->USER->school->nick_name . " logo")));
    $p->set('id', 'port-burgee');
    if ($this->USER->school->burgee === null)
      $p->add(new XP(array('class'=>'message'),
                     new XA($lnk, "Add one now")));
    else
      $p->add(new XP(array('class'=>'burgee-cell'),
                     new XA($lnk, new XImg('data:image/png;base64,'.$this->SCHOOL->burgee->filedata, $this->SCHOOL->nick_name))));

    // ------------------------------------------------------------
    // Team names
    // ------------------------------------------------------------
    $lnk = sprintf('/prefs/%s/team', $this->USER->school->id);
    $this->PAGE->addContent($p = new XPort(new XA($lnk, "Team names for " . $this->USER->school->nick_name)));
    $p->set('id', 'port-team-names');
    $names = $this->USER->school->getTeamNames();
    if (count($names) == 0)
      $p->add(new XP(array('class'=>'warning'),
                     array(new XStrong("Note:"), " There are no team names for your school. ",
                           new XA(WS::link($lnk), "add one now"),
                           ".")));
    else {
      $p->add($ul = new XOl());
      foreach ($names as $name)
        $ul->add(new XLi($name));
    }
  }

  private function hasSchoolIn(Regatta $reg, Array $schools) {
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