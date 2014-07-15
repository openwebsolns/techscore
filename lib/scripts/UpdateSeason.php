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
    $name = $season->fullString();
    $page = new TPublicPage($name);
    $page->body->set('class', 'season-page');
    $page->setDescription(sprintf("List of regattas for %s", $name));
    $page->addMetaKeyword($season->getSeason());
    $page->addMetaKeyword($season->getYear());

    // 2010-11-14: Separate regattas into "weekends", descending by
    // timestamp, based solely on the start_time, assuming that the
    // week ends on a Sunday.

    $first_saturday = $season->getFirstSaturday();
    $first_week = $first_saturday->format('W');
    $weeks = array();
    $regattas = $season->getRegattas();
    foreach ($regattas as $reg) {
      if ($reg->dt_num_divisions === null)
        continue;

      $week = $reg->start_time->format('W') - $first_week + 1;
      if ($week < 0 && $reg->start_time > $first_saturday)
        $week += 52;
      if ($week <= 0)
        $week = "Preweek " . (1 - $week);
      else
        $week = "Week " . $week;

      if (!isset($weeks[$week]))
        $weeks[$week] = array();
      $weeks[$week][] = $reg;
    }

    // SETUP menus top menu: Org Home, Schools, Seasons, *this*
    // season, and About
    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    $page->addMenu(new XA(sprintf('/%s/', $season->id), $season->fullString()));
    if (($lnk = $page->getOrgLink()) !== null)
      $page->addMenu($lnk);

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
                                      "Scoring",
                                      "Start date",
                                      "Status",
                                      "Leading"));
    $now = new DateTime();
    $next_sunday = new DateTime();
    $next_sunday->add(new DateInterval('P7DT0H'));
    $next_sunday->setTime(0, 0);

    $coming = array(Regatta::STAT_READY);

    $rowindex = 0;
    foreach ($weeks as $week => $list) {
      $rows = array();
      usort($list, 'Regatta::cmpTypes');
      foreach ($list as $reg) {
        if ($reg->start_time >= $now) {
          if ($reg->start_time < $next_sunday && in_array($reg->dt_status, $coming))
            array_unshift($coming_regattas, $reg);
        }
        elseif (!in_array($reg->dt_status, $coming)) {
          $teams = $reg->getRankedTeams();
          if (count($teams) == 0)
            continue;

          $total++;
          $status = null;
          $wt = $teams[0];

          switch ($reg->dt_status) {
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
            $status = "In progress: " . $reg->dt_status;
          }

          $num_teams += count($teams);

          $link = new XA($reg->nick, $reg->name);
          $burg = $wt->school->drawSmallBurgee($wt->school->nick_name);
          $rows[] = array($link,
                          $reg->getHostVenue(),
                          $reg->type,
                          $reg->getDataScoring(),
                          $reg->start_time->format('m/d/Y'),
                          $status,
                          new XTD(array('title' => $wt), $burg));
        }
      }
      if (count($rows) > 0) {
        $num_weeks++;
        $past_tab->addRow(array(new XTH(array('colspan'=>7), $week)));
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
                                           "Scoring",
                                           "Start time")));
      foreach ($coming_regattas as $reg) {
        $tab->addRow(array(new XA(sprintf('/%s/%s', $season, $reg->nick), $reg->name),
                           $reg->getHostVenue(),
                           $reg->type,
                           $reg->getDataScoring(),
                           $reg->start_time->format('m/d/Y @ H:i')));
      }
    }
    if ($total > 0)
      $page->addSection(new XPort("Season regattas", array($past_tab)));

    // Complete SUMMARY
    $summary_table["Number of Weekends"] = $num_weeks;
    $summary_table["Number of Regattas"] = $total;
    $summary_table["Number of Entries"] = $num_teams;

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
    // Do season
    $dirname = "/$season/index.html";
    self::write($dirname, $this->getPage($season));
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
