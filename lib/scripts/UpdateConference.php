<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2014-06-23
 * @package scripts
 */

require_once('AbstractScript.php');

/**
 * Update the given conference page, given as an argument
 *
 * @author Dayan Paez
 * @version 2014-06-23
 * @package scripts
 */
class UpdateConference extends AbstractScript {

  private function getPage(Conference $conference, Season $season) {
    require_once('xml5/TPublicPage.php');
    $CONFERENCE = DB::g(STN::CONFERENCE_TITLE);
    $CONF = DB::g(STN::CONFERENCE_SHORT);

    $page = new TPublicPage(sprintf("%s | %s", $conference, $season->fullString()));
    $page->body->set('class', 'school-page');
    $page->setDescription(sprintf("Summary of activity for %s %s during the %s season.", $conference, $CONFERENCE, $season->fullString()));
    $page->addMetaKeyword($conference->id);
    $page->addMetaKeyword($conference->name);
    $page->addMetaKeyword($season->getSeason());
    $page->addMetaKeyword($season->getYear());
    $page->addSocialPlugins(true);

    $url = sprintf('http://%s%s', Conf::$PUB_HOME, $conference->url);
    $og = array('type'=>'website', 'url'=>$url);

    $page->setFacebookLike($url);
    $page->setOpenGraphProperties($og);

    $page->body->set('itemscope', 'itemscope');
    $page->body->set('itemtype', 'http://schema.org/Organization');

    // SETUP navigation
    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    $page->addMenu(new XA($conference->url, $conference));
    if (($link = $page->getOrgTeamsLink()) !== null)
      $page->addMenu($link);

    // current season
    $today = new DateTime();
    $today->setTime(0, 0);
    $tomorrow = new DateTime('tomorrow');
    $tomorrow->setTime(0, 0);

    $regs = $season->getConferenceParticipation($conference);
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
      if ($reg->dt_status === null || $reg->dt_status == Regatta::STAT_SCHEDULED)
        continue;
      $teams = $reg->getRankedTeams();
      $num = count($teams);
      if ($reg->finalized !== null) {
        foreach ($teams as $pl => $team) {
          if ($team->school->conference->id == $conference->id) {
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
    $table = array("Number of Schools" => count($conference->getSchools()),
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
    $page->setHeader($conference, $table, array('itemprop'=>'name'));

    // ------------------------------------------------------------
    // SCHOOL sailing now
    if (count($current) > 0) {
      usort($current, 'Regatta::cmpTypes');
      $page->addSection($p = new XPort("Sailing now", array(), array('id'=>'sailing')));
      $p->add($tab = new XQuickTable(array('class'=>'participation-table'),
                                     array("Name", "Host", "Type", "Scoring", "Last race")));
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
                           $reg->getHostVenue(),
                           $reg->type,
                           $reg->getDataScoring(),
                           $status),
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
                           $reg->getHostVenue(),
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
                                     array("Name", "Host", "Type", "Scoring", "Date", "Status")));

      foreach ($past as $row => $reg) {
        $link = new XA(sprintf('/%s/%s/', $season, $reg->nick),
                       new XSpan($reg->name, array('itemprop'=>'name')),
                       array('itemprop'=>'url'));
        $tab->addRow(array($link,
                           $reg->getHostVenue(),
                           $reg->type,
                           $reg->getDataScoring(),
                           new XElem('time', array('datetime'=>$reg->start_time->format('Y-m-d\TH:i'),
                                                   'itemprop'=>'startDate'),
                                     array(new XText($reg->start_time->format('M d')))),
                           ($reg->finalized === null) ? "Pending" : new XStrong("Official")),
                     array('class' => sprintf('row' . ($row % 2)),
                           'itemprop'=>'event',
                           'itemscope'=>'itemscope',
                           'itemtype'=>'http://schema.org/SportsEvent'));
      }
    }

    // ------------------------------------------------------------
    // Add links to all seasons
    $ul = new XUl(array('id'=>'other-seasons'));
    $num = 0;
    $root = $conference->url;
    foreach (DB::getAll(DB::T(DB::SEASON)) as $s) {
      $regs = $s->getConferenceParticipation($conference);
      if (count($regs) > 0) {
        $num++;
        $ul->add(new XLi(new XA($root . $s->id . '/', $s->fullString())));
      }
    }
    if ($num > 0)
      $page->addSection(new XDiv(array('id'=>'submenu-wrapper'),
                                 array(new XH3("Other seasons", array('class'=>'nav')),
                                       $ul)));
    return $page;
  }

  /**
   * Creates the given season summary for the given conference
   *
   * @param Conference $conference the conference whose summary to generate
   * @param Season $season the season
   * @throws InvalidArgumentException
   */
  public function run(Conference $conference, Season $season) {
    if ($conference->url === null)
      throw new InvalidArgumentException(sprintf("Conference %s does not have a URL.", $conference));

    $dirname = $conference->url;

    // Do season
    $today = Season::forDate(DB::T(DB::NOW));
    $base = (string)$season;

    // Create season directory
    $fullname = $dirname . $base;

    // is this current season
    $current = false;
    if ((string)$today == (string)$season)
      $current = true;

    $filename = "$fullname/index.html";
    $content = $this->getPage($conference, $season);
    self::write($filename, $content);
    self::errln("Wrote season $season summary for $conference.", 2);
    
    // If current, do we also need to create index page?
    if ($current) {
      $filename = $dirname . 'index.html';
      self::write($filename, $content);
      self::errln("Wrote current summary for $conference.", 2);
    }
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------

  protected $cli_opts = '<conference_id> [season]';
  protected $cli_usage = " <conference_id>  the ID of the conference to update
 season       (optional) the season to update (defaults to current)";
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateConference();
  $opts = $P->getOpts($argv);

  // Validate inputs
  if (count($opts) == 0)
    throw new TSScriptException("No conference ID provided");
  $id = array_shift($opts);
  if (($conference = DB::getConference($id)) === null)
    throw new TSScriptException("Invalid conference ID provided: $id");

  // Season
  if (count($opts) > 1)
    throw new TSScriptException("Invalid argument provided");
  $season = Season::forDate(DB::T(DB::NOW));
  if (count($opts) > 0) {
    $id = array_shift($opts);
    if (($season = DB::getSeason($id)) === null)
      throw new TSScriptException("Invalid season provided: $id");
  }
  if ($season === null)
    throw new TSScriptException("No current season exists");

  $P->run($conference, $season);
}
?>
