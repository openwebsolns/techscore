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
 * Creates the season summary page for the given season. Such a page
 * contains a port with information about current regattas being
 * sailed and past regattas as well. For speed sake, this function
 * uses the dt_* tables, which contain summarized versions of only the
 * public regattas.
 *
 */
class UpdateSeason extends AbstractScript {

  private function getPage(Season $season) {
    require_once('xml5/TPublicPage.php');
    $types = Regatta::getTypes();
    $name = $season->fullString();
    $page = new TPublicPage($name);
    $page->setDescription(sprintf("Summary of ICSA regattas for %s", $name));
    $page->addMetaKeyword($season->getSeason());
    $page->addMetaKeyword($season->getYear());

    // 2010-11-14: Separate regattas into "weekends", descending by
    // timestamp, based solely on the start_time, assuming that the
    // week ends on a Sunday.
    require_once('regatta/PublicDB.php');

    $weeks = array();
    $regattas = DB::getAll(DB::$DT_REGATTA, new DBCond('season', (string)$season));
    foreach ($regattas as $reg) {
      $week = $reg->start_time->format('W');
      if (!isset($weeks[$week]))
        $weeks[$week] = array();
      $weeks[$week][] = $reg;
    }

    // SETUP menus top menu: ICSA Home, Schools, Seasons, *this*
    // season, and About
    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    $page->addMenu(new XA(sprintf('/%s/', $season->id), $season->fullString()));
    $page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));

    // SEASON summary
    $summary_table = array();
    $num_teams = 0;
    $num_weeks = 0;

    // COMING soon
    $coming_regattas = array();

    // WEEKENDS
    $count = count($weeks);
    if ($count == 0) {
      // Should this section even exist?
      $page->addSection(new XP(array(), "There are no regattas to report on yet."));
    }

    // stats
    $total = 0;
    $winning_school  = array();
    $now = date('U');
    $past_tab = new XQuickTable(array('class'=>'season-summary'),
                                array("Name",
                                      "Host",
                                      "Type",
                                      "Start date",
                                      "Status",
                                      "Leading"));
    $now = new DateTime();
    $next_sunday = new DateTime();
    $next_sunday->add(new DateInterval('P7DT0H'));
    $next_sunday->setTime(0, 0);

    $coming = array(Dt_Regatta::STAT_READY, Dt_Regatta::STAT_SCHEDULED);

    $rowindex = 0;
    foreach ($weeks as $week => $list) {
      $rows = array();
      foreach ($list as $reg) {
        if ($reg->start_time >= $now) {
          if ($reg->start_time < $next_sunday && in_array($reg->status, $coming))
            array_unshift($coming_regattas, $reg);
        }
        elseif (!in_array($reg->status, $coming)) {
          $teams = $reg->getTeams();
          if (count($teams) == 0)
            continue;

          $total++;
          $status = null;
          $wt = $teams[0];

          switch ($reg->status) {
          case 'finished':
            $status = "Pending";
            break;

          case 'final':
            $status = new XStrong("Official");
            if (!isset($winning_school[$wt->school->id]))
              $winning_school[$wt->school->id] = 0;
            $winning_school[$wt->school->id] += 1;
            break;

          default:
            $status = "In progress: " . $reg->status;
          }

          $num_teams += count($teams);
          $hosts = array();
          foreach ($reg->getHosts() as $host) {
            $hosts[$host->id] = $host->nick_name;
          }

          $link = new XA($reg->nick, $reg->name);
          $path = realpath(sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $wt->school->id));
          $burg = ($path !== false) ?
            new XImg(sprintf('/inc/img/schools/%s.png', $wt->school->id), $wt->school, array('height'=>40)) :
            $wt->school->nick_name;
          $rows[] = array($link,
                          implode("/", $hosts),
                          $types[$reg->type],
                          $reg->start_time->format('m/d/Y'),
                          $status,
                          new XTD(array('title' => $wt), $burg));
        }
      }
      if (count($rows) > 0) {
        $num_weeks++;
        $past_tab->addRow(array(new XTH(array('colspan'=>7), "Week $count")));
        foreach ($rows as $row)
          $past_tab->addRow($row, array('class' => sprintf("row%d", $rowindex++ % 2)));
      }
      $count--;
    }

    // WRITE coming soon, and weekend summary ports
    if (count($coming_regattas) > 0) {
      $page->addSection($p = new XPort("Coming soon"));
      $p->add($tab = new XQuickTable(array('class'=>'coming-regattas'),
                                     array("Name",
                                           "Host",
                                           "Type",
                                           "Start time")));
      foreach ($coming_regattas as $reg) {
        $hosts = array();
        foreach ($reg->getHosts() as $host) {
          $hosts[$host->id] = $host->nick_name;
        }
        $tab->addRow(array(new XA(sprintf('/%s/%s', $season, $reg->nick), $reg->name),
                           implode("/", $hosts),
                           $types[$reg->type],
                           $reg->start_time->format('m/d/Y @ H:i')));
      }
    }
    if ($total > 0)
      $page->addSection(new XPort("Season regattas", array($past_tab)));

    // Complete SUMMARY
    $summary_table["Number of Weekends"] = $num_weeks;
    $summary_table["Number of Regattas"] = $total;
    $summary_table["Number of Teams"] = $num_teams;

    // Sort the winning school to determine winningest, and only print
    // this stat if there is a something to have won. Also, print all
    // the tied teams for winningest spot.
    /*
      arsort($winning_school, SORT_NUMERIC);
      if (count($winning_school) > 0) {
      $school_codes = array_keys($winning_school);
      if ($winning_school[$school_codes[0]] != 0) {
      // tied teams
      $tied_number = array_shift($winning_school);
      $tied_schools = array();
      $tied_schools[] = DB::get(DB::$SCHOOL, array_shift($school_codes));
      while (count($school_codes) > 0) {
      $next_num = array_shift($winning_school);
      if ($next_num != $tied_number) break;
      $tied_schools[] = DB::get(DB::$SCHOOL, array_shift($school_codes));
      }
      }
      // 2011-04-09: feedback compiled by Matt Lindblad from users
      // that this stat was "confusing"
      $summary_port->add(new XDiv(array('class'=>'stat'),
      array(new XSpan("Winningest School(s):", array('class'=>'prefix')),
      implode('/',
      $tied_schools))));
      }
    */

    // Summary report
    $page->setHeader($season->fullString(), $summary_table);

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

  // ------------------------------------------------------------
  // Static component used to write the summary page to file
  // ------------------------------------------------------------

  /**
   * Creates the new page summary in the public domain
   *
   */
  public function run(Season $season) {
    $R = realpath(dirname(__FILE__).'/../../html');

    // Do season
    $dirname = "/$season/index.html";
    self::writeXml($dirname, $this->getPage($season));
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------

  protected $cli_opts = '<season>';
  protected $cli_usage = "Example of season format: \"s11\" for \"Spring 2011\"";
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateSeason();
  $opts = $P->getOpts($argv);
  if (count($opts) != 1)
    throw new TSScriptException("Invalid argument(s)");

  if (($season = DB::getSeason($opts[0])) === null)
    throw new TSScriptException("Invalid season provided: " . $opts[0]);
  $P->run($season);
}
?>
