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
class UserSeasonPane extends AbstractUserPane {

  /**
   * Creates a new user home page, which displays current season's
   * regattas.
   *
   * @param Account $user the user
   */
  public function __construct(Account $user) {
    parent::__construct("Season summary", $user);
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
    // Regattas
    // ------------------------------------------------------------
    $season = Season::forDate(DB::$NOW);
    if ($season === null) {
      $this->PAGE->addContent($p = new XPort("No season"));
      $p->add(new XP(array('class'=>'warning'),
                     array("There is no current season in the program. Please contact the administrator. No regattas can be created to start in the \"off-season\". You may wish to ",
                           new XA(WS::link('/archive'), "browse the archive"),
                           " instead.")));
      return;
    }

    require_once('regatta/Regatta.php');
    DB::$REGATTA->db_set_order(array('start_time' => true));
    $regattas = $this->USER->getRegattas($season);
    DB::$REGATTA->db_set_order();

    if (count($regattas) == 0) {
      $this->PAGE->addContent($p = new XPort(sprintf("Regattas for %s", $season->fullString())));
      $p->add(new XP(array('class'=>'warning'),
                     array("You have no regattas for season ",
                           $season->fullString(),
                           ". Try ",
                           new XA(WS::link('/archive'), "browsing the archive"),
                           " or ",
                           new XA("create", "create one"), ".")));
      return;
    }

    // Track two different tables: one for regattas happening "right
    // now", to appear before the the second, which is a list of every
    // other regatta. Only create the first port if there are regattas
    // to list there.
    $headers = array("Name");
    if ($this->USER->isAdmin())
      $headers[] = "Host(s)";
    $headers[] = "Date";
    $headers[] = "Type";
    $headers[] = "Scoring";
    $headers[] = "Finalized";
    $cur_tab = new XQuickTable(array('class'=>'regatta-list-current'), $headers);
    $num_cur = 0;

    $all_tab = new XQuickTable(array('class'=>'regatta-list'), $headers);
    $num_all = 0;

    // Sort all current regattas
    $start = clone(DB::$NOW);
    $start->add(new DateInterval('P3DT0H'));
    $start->setTime(0, 0);

    

    foreach ($regattas as $reg) {
      $link = new XA('/score/' . $reg->id, $reg->name);
      $row = array($link);

      if ($this->USER->isAdmin()) {
        $hosts = array();
        foreach ($reg->getHosts() as $host)
          $hosts[$host->id] = $host->id;
        $row[] = implode("/", $hosts);
      }

      $finalized = '--';
      if ($reg->finalized !== null) {
        $rpm = $reg->getRpManager();
        if (!$rpm->isComplete())
          $finalized = new XA(WS::link(sprintf('/score/%s/rp', $reg->id)), "Missing RP",
                              array('class'=>'stat missing-rp',
                                    'title'=>"At least one skipper/crew is missing."));
        else
          $finalized = $reg->finalized->format("Y-m-d");
      }
      elseif ($reg->end_date < DB::$NOW) {
        if (count($reg->getTeams()) == 0 || count($reg->getRaces()) == 0)
          $finalized = new XSpan("Incomplete", array('class'=>'stat incomplete', 'title'=>"Missing races or teams."));
        elseif (!$reg->hasFinishes())
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
      if ($this->isCurrent($reg, $start)) {
        $cur_tab->addRow($row, array('class' => $class . 'row'.($num_cur++ % 2)));
      }
      else {
        $all_tab->addRow($row, array('class' => $class . 'row'.($num_all++ % 2)));
      }
    }

    // Add the tables
    if ($num_cur > 0) {
      $this->PAGE->addContent($p = new XPort("Current and recent"));
      $p->add($cur_tab);
    }
    
    $this->PAGE->addContent($p = new XPort(sprintf("Regattas for %s", $season->fullString())));
    if ($num_all == 0)
      $p->add(new XP(array(), new XEm("No regattas other those listed above.")));
    else
      $p->add($all_tab);
  }

  /**
   * Determines whether a regatta is "recent enough"
   *
   * Condition: start_time in the next 3 days, end_date + 4 days
   * beyond now
   */
  private function isCurrent(Regatta $reg, DateTime $start) {
    if ($reg->start_time > $start)
      return false;
    $end = clone($reg->end_date);
    $end->add(new DateInterval('P3DT0H'));
    return $end > DB::$NOW;
  }

  /**
   * This pane does not edit anything
   *
   * @param Array $args can be an empty array
   */
  public function process(Array $args) { return $args; }
}
?>