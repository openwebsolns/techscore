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
 * Update the given school, given as an argument
 *
 * @author Dayan Paez
 * @version 2010-08-27
 * @package scripts
 */
class UpdateSchool extends AbstractScript {

  private function getBlogLink() {
    return null;
    // Turned off until we can figure out a consistent way of doing
    $link_fmt = 'http://collegesailing.info/blog/teams/%s';
    return sprintf($link_fmt, str_replace(' ', '-', strtolower($this->school->name)));
  }

  /**
   * Helper method provides a string representation of school's
   * current placement
   *
   */
  private function getPlaces(Regatta $reg, School $school) {
    $places = array();
    $teams = $reg->getRankedTeams($school);
    foreach ($teams as $team) {
      $places[] = $team->dt_rank;
    }
    if (count($teams) == 0)
      return "";
    return sprintf('%s/%d', implode(',', $places), count($reg->getTeams()));
  }

  private function getPage(School $school, Season $season) {
    require_once('xml5/TPublicPage.php');

    $page = new TPublicPage(sprintf("%s | %s", $school, $season->fullString()));
    $page->setDescription(sprintf("Summary of activity for %s during the %s season.", $school, $season->fullString()));
    $page->addMetaKeyword($school->id);
    $page->addMetaKeyword($school->name);
    $page->addMetaKeyword($season->getSeason());
    $page->addMetaKeyword($season->getYear());

    $page->body->set('itemscope', 'itemscope');
    $page->body->set('itemtype', 'http://schema.org/CollegeOrUniversity');

    // SETUP navigation
    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    $page->addMenu(new XA(sprintf("/schools/%s/", $school->id), $school->nick_name));
    if (($link = $this->getBlogLink()) !== null)
      $page->addMenu(new XA($link, "ICSA Info", array('itemprop'=>'url')));
    $page->addMenu(new XA(Conf::$ICSA_HOME . '/teams/', "ICSA Teams"));

    if ($school->burgee !== null)
      $page->addSection(new XP(array('class'=>'burgee'), new XImg(sprintf('/inc/img/schools/%s.png', $school->id), $school, array('itemprop'=>'image'))));

    // current season
    $today = new DateTime();
    $today->setTime(0, 0);
    $tomorrow = new DateTime('tomorrow');
    $tomorrow->setTime(0, 0);

    $regs = $season->getParticipation($school);
    $total = count($regs);
    $current = array(); // regattas happening NOW
    $past = array();    // past regattas from the current season
    $coming = array();  // upcoming schedule

    $skippers = array(); // associative array of sailor id => num times participating
    $skip_objs = array();
    $crews = array();
    $crew_objs = array();
    // get average placement
    $places = 0;
    $avg_total = 0;
    foreach ($regs as $reg) {
      $teams = $reg->getRankedTeams();
      $num = count($teams);
      if ($reg->finalized !== null) {
        foreach ($teams as $pl => $team) {
          if ($team->school->id == $school->id) {
            // track placement
            $places += (1 - (1 + $pl) / (1 + $num));
            $avg_total++;

            // track participation
            $sk = $team->getRpData(null, 'skipper');
            $cr = $team->getRpData(null, 'crew');
            foreach ($sk as $rp) {
              if (!isset($skippers[$rp->sailor->id])) {
                $skippers[$rp->sailor->id] = 0;
                $skip_objs[$rp->sailor->id] = $rp->sailor;
              }
              $skippers[$rp->sailor->id]++;
            }
            foreach ($cr as $rp) {
              if (!isset($crews[$rp->sailor->id])) {
                $crews[$rp->sailor->id] = 0;
                $crew_objs[$rp->sailor->id] = $rp->sailor;
              }
              $crews[$rp->sailor->id]++;
            }
          }
        }
      }
      if ($reg->start_time < $tomorrow && $reg->end_date >= $today) {
        $current[] = $reg;
      }
      if ($reg->end_date < $today) {
        $past[] = $reg;
      }
      if ($reg->start_time >= $tomorrow)
        $coming[] = $reg;
    }
    $avg = "Not applicable";
    if ($avg_total > 0)
      $avg = sprintf('%3.1f%%', 100 * ($places / $avg_total));

    // ------------------------------------------------------------
    // SCHOOL season summary
    $table = array("Conference" => $school->conference,
                   "Number of Regattas" => $total);
    // "Finish percentile" => $avg;
    $season_link = new XA('/'.(string)$season.'/', $season->fullString());

    // most active sailor?
    /*
    arsort($skippers, SORT_NUMERIC);
    arsort($crews, SORT_NUMERIC);
    if (count($skippers) > 0) {
      $txt = array();
      $i = 0;
      foreach ($skippers as $id => $num) {
        if ($i++ >= 2)
          break;
        $mes = ($num == 1) ? "race" : "races";
        $txt[] = sprintf("%s (%d %s)", $skip_objs[$id], $num, $mes);
      }
      $table["Most active skipper"] = implode(", ", $txt);
    }
    if (count($crews) > 0) {
      $txt = array();
      $i = 0;
      foreach ($crews as $id => $num) {
        if ($i++ >= 2)
          break;
        $mes = ($num == 1) ? "race" : "races";
        $txt[] = sprintf("%s (%d %s)", $crew_objs[$id], $num, $mes);
      }
      $table["Most active crew"] = implode(", ", $txt);
    }
    */
    $page->setHeader($school, $table, array('itemprop'=>'name'));

    // ------------------------------------------------------------
    // SCHOOL sailing now
    if (count($current) > 0) {
      usort($current, 'Regatta::cmpTypes');
      $page->addSection($p = new XPort("Sailing now", array(), array('id'=>'sailing')));
      $p->add($tab = new XQuickTable(array('class'=>'participation-table'),
                                     array("Name", "Host", "Type", "Scoring", "Last race", "Place(s)")));
      foreach ($current as $row => $reg) {
        // borrowed from UpdateSeason
        $status = null;
        switch ($reg->dt_status) {
        case Regatta::STAT_READY:
          $status = new XEm("No scores yet");
          break;

        default:
          $status = new XStrong(ucwords($reg->dt_status));
        }

        $link = new XA(sprintf('/%s/%s', $season, $reg->nick), $reg->name);
        $tab->addRow(array($link,
                           implode("/", $reg->dt_hosts),
                           $reg->type,
                           $reg->getDataScoring(),
                           $status,
                           $this->getPlaces($reg, $school)),
                     array('class' => 'row' . ($row % 2)));
      }
    }
    // ------------------------------------------------------------
    // SCHOOL coming soon: ONLY if there is no current ones
    elseif (count($coming) > 0) {
      usort($coming, 'Regatta::cmpTypes');
      $page->addSection($p = new XPort("Coming soon"));
      $p->add($tab = new XQuickTable(array('class'=>'coming-regattas'),
                                     array("Name",
                                           "Host",
                                           "Type",
                                           "Scoring",
                                           "Start time")));
      foreach ($coming as $reg) {
        $tab->addRow(array(new XA(sprintf('/%s/%s', $season, $reg->nick), $reg->name),
                           implode("/", $reg->dt_hosts),
                           $reg->type,
                           $reg->getDataScoring(),
                           $reg->start_time->format('m/d/Y @ H:i')));
      }
    }

    // ------------------------------------------------------------
    // SCHOOL past regattas
    if (count($past) > 0) {
      $page->addSection($p = new XPort(array("Season history for ", $season_link)));
      $p->set('id', 'history');

      $p->add($tab = new XQuickTable(array('class'=>'participation-table'),
                                     array("Name", "Host", "Type", "Scoring", "Date", "Status", "Place(s)")));

      foreach ($past as $row => $reg) {
        $link = new XA(sprintf('/%s/%s', $season, $reg->nick), $reg->name);
        $tab->addRow(array($link,
                           implode("/", $reg->dt_hosts),
                           $reg->type,
                           $reg->getDataScoring(),
                           $reg->start_time->format('M d'),
                           ($reg->finalized === null) ? "Pending" : new XStrong("Official"),
                           $this->getPlaces($reg, $school)),
                     array('class' => sprintf('row' . ($row % 2))));
      }
    }

    // ------------------------------------------------------------
    // Add links to all seasons
    $ul = new XUl(array('id'=>'other-seasons'));
    $num = 0;
    $root = sprintf('/schools/%s', $school->id);
    foreach (DB::getAll(DB::$SEASON) as $s) {
      $regs = $s->getParticipation($school);
      if (count($regs) > 0) {
        $num++;
        $ul->add(new XLi(new XA($root . '/' . $s->id, $s->fullString())));
      }
    }
    if ($num > 0)
      $page->addSection(new XDiv(array('id'=>'submenu-wrapper'),
                                 array(new XH3("Other seasons", array('class'=>'nav')),
                                       $ul)));
    return $page;
  }

  /**
   * Creates the given season summary for the given school
   *
   * @param School $school the school whose summary to generate
   * @param Season $season the season
   */
  public function run(School $school, Season $season) {
    $dirname = '/schools/' . $school->id;

    // Do season
    $today = Season::forDate(DB::$NOW);
    $base = (string)$season;

    // Create season directory
    $fullname = "$dirname/$base";

    // is this current season
    $current = false;
    if ((string)$today == (string)$season)
      $current = true;

    $filename = "$fullname/index.html";
    $content = $this->getPage($school, $season);
    self::writeXml($filename, $content);
    self::errln("Wrote season $season summary for $school.", 2);
    
    // If current, do we also need to create index page?
    if ($current) {
      $filename = "$dirname/index.html";
      self::writeXml($filename, $content);
      self::errln("Wrote current summary for $school.", 2);
    }
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------

  protected $cli_opts = '<school_id> [season]';
  protected $cli_usage = " <school_id>  the ID of the school to update
 season       (optional) the season to update (defaults to current)";
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateSchool();
  $opts = $P->getOpts($argv);

  // Validate inputs
  if (count($opts) == 0)
    throw new TSScriptException("No school ID provided");
  $id = array_shift($opts);
  if (($school = DB::getSchool($id)) === null)
    throw new TSScriptException("Invalid school ID provided: $id");

  // Season
  if (count($opts) > 1)
    throw new TSScriptException("Invalid argument provided");
  $season = Season::forDate(DB::$NOW);
  if (count($opts) > 0) {
    $id = array_shift($opts);
    if (($season = DB::getSeason($id)) === null)
      throw new TSScriptException("Invalid season provided: $id");
  }
  if ($season === null)
    throw new TSScriptException("No current season exists");

  $P->run($school, $season);
}
?>