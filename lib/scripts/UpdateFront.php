<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

require_once('AbstractScript.php');

/**
 * Creates the front page: Includes a brief welcoming message,
 * displayed alongside the list of regattas sailing now, if any. (The
 * welcome message takes up the entire width otherwise).
 *
 * Underneath that, includes a list of upcoming events, in ascending
 * chronological order.
 *
 */
class UpdateFront extends AbstractScript {

  private function getPage() {
    require_once('xml5/TPublicFrontPage.php');
    $page = new TPublicFrontPage();

    // Add welcome message
    $page->addSection($div = new XDiv(array('id'=>'message-container')));
    $div->add(new XDiv(array('id'=>'welcome'),
                       array($this->h1("Welcome"),
                             new XP(array(),
                                    array("This is the home for real-time results of College Sailing regattas. This site includes scores and participation records for all fleet-racing events within ICSA. An archive of ",
                                          new XA('/seasons/', "all previous seasons"),
                                          " is also available.")),
                             new XP(array(),
                                    array("To follow a specific school, use our ",
                                          new XA('/schools/', "listing of schools"),
                                          " organized by ICSA Conference. Each school's participation is summarized by season.")),
                             new XP(array(),
                                    array("For more information about college sailing, ICSA, the teams, and our sponsors, please visit the ",
                                          new XA(Conf::$ICSA_HOME, "ICSA site."))))));

    // Menu
    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));

    // Get current season's coming regattas
    require_once('regatta/Regatta.php');

    $success = false;
    $seasons = Season::getActive();
    if (count($seasons) == 0) {
      $page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));

      // Wow! There is NO information to report!
      $page->addSection(new XPort("Nothing to show!", array(new XP(array(), "We are sorry, but there are no regattas in the system! Please come back later. Happy sailing!"))));
      return $page;
    }

    $types = Regatta::getTypes();
    $page->addMenu(new XA('/'.$seasons[0]->id.'/', $seasons[0]->fullString()));
    $page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));

    // ------------------------------------------------------------
    // Are there any regattas in progress? Such regattas must exist in
    // Dt_Regatta, be happening now according to date, and have a
    // status not equal to 'SCHEDULED' (which usually indicates that a
    // regatta is not yet ready, and might possibly never be scored).
    $start = new DateTime();
    $start->setTime(23, 59, 59);
    $end = new DateTime();
    $end->setTime(0, 0, 0);
    $potential = DB::getAll(DB::$PUBLIC_REGATTA,
                            new DBBool(array(new DBCond('start_time', $start, DBCond::LE),
                                             new DBCond('end_date', $end, DBCond::GE))));
    $in_prog = array();
    foreach ($potential as $reg) {
      $data = $reg->getData();
      if ($data->status != Dt_Regatta::STAT_SCHEDULED)
        $in_prog[] = $reg;
    }
    if (count($in_prog) > 0) {
      $div->add(new XDiv(array('id'=>'in-progress'),
                         array($this->h1("In progress"),
                               $tab = new XQuickTable(array('class'=>'season-summary'),
                                                      array("Name",
                                                            "Type",
                                                            "Status",
                                                            "Leading")))));
      foreach ($in_prog as $i => $reg) {
        $data = $reg->getData();
        $row = array(new XA($reg->getURL(), $reg->name), $types[$reg->type]);
        $tms = $data->getTeams();
        if ($data->status == Dt_Regatta::STAT_READY || count($tms) == 0) {
          $row[] = new XTD(array('colspan'=>2), new XEm("No scores yet"));
        }
        else {
          $row[] = new XStrong(ucwords($data->status));
          if ($tms[0]->school->burgee !== null)
            $row[] = new XImg(sprintf('/inc/img/schools/%s.png', $tms[0]->school->id), $tms[0], array('height'=>40));
          else
            $row[] = (string)$tms[0];
        }
        $tab->addRow($row, array('class'=>'row'.($i % 2)));
      }
    }

    // ------------------------------------------------------------
    // Fill list of coming soon regattas
    $now = new DateTime('tomorrow');
    $now->setTime(0, 0);
    DB::$REGATTA->db_set_order(array('start_time'=>true));
    $regs = DB::getAll(DB::$PUBLIC_REGATTA, new DBCond('start_time', $now, DBCond::GE));
    DB::$REGATTA->db_set_order();
    if (count($regs) > 0) {
      $page->addSection($p = new XPort("Upcoming schedule"));
      $p->add($tab = new XQuickTable(array('class'=>'coming-regattas'),
                                     array("Name",
                                           "Host",
                                           "Type",
                                           "Start time")));
      foreach ($regs as $reg) {
        $data = $reg->getData();
        $tab->addRow(array(new XA($reg->getURL(), $reg->name),
                           implode("/", $data->hosts),
                           $types[$reg->type],
                           $reg->start_time->format('m/d/Y @ H:i')));
      }
    }

    // ------------------------------------------------------------
    // Add links to all seasons
    $num = 0;
    $ul = new XUl(array('id'=>'other-seasons'));
    $seasons = Season::getActive();
    foreach ($seasons as $s) {
      if (count($s->getRegattas()) > 0) {
        $num++;
        $ul->add(new XLi(new XA('/'.$s.'/', $s->fullString())));
      }
    }
    if ($num > 0)
      $page->addSection(new XDiv(array('id'=>'submenu-wrapper'),
                                       array(new XH3("Other seasons", array('class'=>'nav')), $ul)));
    return $page;
  }

  /**
   * Creates a fancy, three-part, h1 heading
   *
   * @param String $heading the title of the heading (arg to XSpan)
   * @return XH1
   */
  private function h1($heading) {
    $h1 = new XH1("");
    $h1->add(new XSpan("", array('class'=>'left-fill')));
    $h1->add(new XSpan($heading));
    $h1->add(new XSpan("", array('class'=>'right-fill')));
    return $h1;
  }

  /**
   * Creates the new page summary in the public domain
   *
   */
  public function run() {
    self::writeXml('/index.html', $this->getPage());
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateFront();
  $opts = $P->getOpts($argv);
  if (count($opts) > 0)
    throw new TSScriptException("Invalid argument(s).");
  $P->run();
}
?>
