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
    $season = Season::forDate(DB::T(DB::NOW));
    if ($season === null) {
      $this->PAGE->addContent($p = new XPort("No season"));
      $p->add(new XP(array('class'=>'warning'),
                     array("There is no current season in the program. Please contact the administrator. No regattas can be created to start in the \"off-season\". You may wish to ",
                           new XA(WS::link('/archive'), "browse the archive"),
                           " instead.")));
      return;
    }

    DB::T(DB::REGATTA)->db_set_order(array('start_time' => true));
    $regattas = $this->USER->getRegattas($season, true);
    DB::T(DB::REGATTA)->db_set_order();

    if (count($regattas) == 0) {
      $this->PAGE->addContent($p = new XPort(sprintf("Regattas for %s", $season->fullString())));
      $p->add($xp = new XP(array('class'=>'warning'),
                           array("You have no regattas for season ",
                                 $season->fullString(),
                                 ". Try ",
                                 new XA(WS::link('/archive'), "browsing the archive"))));
      if ($this->USER->can(Permission::CREATE_REGATTA)) {
        $xp->add(" or ");
        $xp->add(new XA("create", "create one"), ".");
      }
      $xp->add(".");
      return;
    }

    // Track two different tables: one for regattas happening "right
    // now", to appear before the the second, which is a list of every
    // other regatta. Only create the first port if there are regattas
    // to list there.
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/tablefilter.js')));
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/tfinit.js')));
    require_once('xml5/UserRegattaTable.php');
    $all_tab = new UserRegattaTable($this->USER);
    $cur_tab = new UserRegattaTable($this->USER, true);

    // Sort all current regattas
    $start = clone(DB::T(DB::NOW));
    $start->add(new DateInterval('P3DT0H'));
    $start->setTime(0, 0);

    foreach ($regattas as $reg) {
      if ($this->isCurrent($reg, $start)) {
        $cur_tab->addRegatta($reg);
      }
      else {
        $all_tab->addRegatta($reg);
      }
    }

    // Add the tables
    if ($cur_tab->count() > 0) {
      $this->PAGE->addContent($p = new XPort("Current and recent"));
      $p->add($cur_tab);
    }
    
    $this->PAGE->addContent($p = new XPort(sprintf("Regattas for %s", $season->fullString())));
    if ($all_tab->count() == 0)
      $p->add(new XP(array(), new XEm("No regattas to show.")));
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
    return $end > DB::T(DB::NOW);
  }

  /**
   * This pane does not edit anything
   *
   * @param Array $args can be an empty array
   */
  public function process(Array $args) { return $args; }
}
?>