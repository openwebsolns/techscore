<?php
namespace xml5;

use \DateTime;
use \DB;
use \Conf;
use \STN;
use \Conference;
use \Regatta;
use \Season;
use \TPublicPage;

use \XA;
use \XDiv;
use \XElem;
use \XEm;
use \XH3;
use \XLi;
use \XPort;
use \XQuickTable;
use \XSpan;
use \XStrong;
use \XText;
use \XUl;

require_once('xml5/TPublicPage.php');

/**
 * Conference page.
 *
 * @author Dayan Paez
 * @version 2015-11-25
 */
class ConferencePage extends TPublicPage {

  public function __construct(Conference $conference, Season $season) {
    parent::__construct(
      sprintf("%s | %s", $conference, $season->fullString())
    );
    $this->fill($conference, $season);
  }

  private function fill(Conference $conference, Season $season) {
    $CONFERENCE = DB::g(STN::CONFERENCE_TITLE);
    $CONF = DB::g(STN::CONFERENCE_SHORT);

    $this->body->set('class', 'school-page');
    $this->setDescription(sprintf("Summary of activity for %s %s during the %s season.", $conference, $CONFERENCE, $season->fullString()));
    $this->addMetaKeyword($conference->id);
    $this->addMetaKeyword($conference->name);
    $this->addMetaKeyword($season->getSeason());
    $this->addMetaKeyword($season->getYear());
    $this->addSocialPlugins(true);

    $url = sprintf('http://%s%s', Conf::$PUB_HOME, $conference->getURL());
    $og = array('type'=>'website', 'url'=>$url);

    $this->setFacebookLike($url);
    $this->setOpenGraphProperties($og);

    $this->body->set('itemscope', 'itemscope');
    $this->body->set('itemtype', 'http://schema.org/Organization');

    // SETUP navigation
    $this->addMenu(new XA('/', "Home"));
    $this->addMenu(new XA('/schools/', "Schools"));
    $this->addMenu(new XA('/seasons/', "Seasons"));
    $this->addMenu(new XA($conference->getURL(), $conference));
    if (($link = $this->getOrgTeamsLink()) !== null) {
      $this->addMenu($link);
    }

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
      if ($reg->dt_status === null || $reg->dt_status == Regatta::STAT_SCHEDULED) {
        continue;
      }

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
      if ($reg->start_time >= $tomorrow) {
        $coming[] = $reg;
      }
    }
    $avg = "Not applicable";
    if ($avg_total > 0) {
      $avg = sprintf('%3.1f%%', 100 * ($places / $avg_total));
    }

    // ------------------------------------------------------------
    // SCHOOL season summary
    $table = array(
      "Number of Schools" => count($conference->getSchools()),
      "Number of Regattas" => $total
    );
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
    $this->setHeader($conference, $table, array('itemprop'=>'name'));

    // ------------------------------------------------------------
    // SCHOOL sailing now
    if (count($current) > 0) {
      usort($current, 'Regatta::cmpTypes');
      $this->addSection($p = new XPort("Sailing now", array(), array('id'=>'sailing')));
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
        $tab->addRow(
          array(
            $link,
            $reg->getHostVenue(),
            $reg->type,
            $reg->getDataScoring(),
            $status
          ),
          array('class' => 'row' . ($row % 2))
        );
      }
    }
    // ------------------------------------------------------------
    // SCHOOL coming soon: ONLY if there is no current ones
    elseif (count($coming) > 0) {
      usort($coming, 'Regatta::cmpTypes');
      $this->addSection($p = new XPort("Coming soon"));
      $p->add(
        $tab = new XQuickTable(
          array('class'=>'coming-regattas'),
          array(
            "Name",
            "Host",
            "Type",
            "Scoring",
            "Start time"
          )
        )
      );
      foreach ($coming as $reg) {
        $tab->addRow(
          array(
            new XA(sprintf('/%s/%s', $season, $reg->nick), $reg->name),
            $reg->getHostVenue(),
            $reg->type,
            $reg->getDataScoring(),
            $reg->start_time->format('m/d/Y @ H:i')
          )
        );
      }
    }

    // ------------------------------------------------------------
    // SCHOOL past regattas
    if (count($past) > 0) {
      $this->addSection($p = new XPort(array("Season history for ", $season_link)));
      $p->set('id', 'history');

      $p->add(
        $tab = new XQuickTable(
          array('class'=>'participation-table'),
          array("Name", "Host", "Type", "Scoring", "Date", "Status")
        )
      );

      foreach ($past as $row => $reg) {
        $link = new XA(
          sprintf('/%s/%s/', $season, $reg->nick),
          new XSpan($reg->name, array('itemprop'=>'name')),
          array('itemprop'=>'url')
        );
        $tab->addRow(
          array(
            $link,
            $reg->getHostVenue(),
            $reg->type,
            $reg->getDataScoring(),
            new XElem(
              'time',
              array(
                'datetime' => $reg->start_time->format('Y-m-d\TH:i'),
                'itemprop'=>'startDate'
              ),
              array(
                new XText($reg->start_time->format('M d'))
              )
            ),
            ($reg->finalized === null) ? "Pending" : new XStrong("Official")
          ),
          array(
            'class' => sprintf('row' . ($row % 2)),
            'itemprop'=>'event',
            'itemscope'=>'itemscope',
            'itemtype'=>'http://schema.org/SportsEvent'
          )
        );
      }
    }

    // ------------------------------------------------------------
    // Add links to all seasons
    $ul = new XUl(array('id'=>'other-seasons'));
    $num = 0;
    $root = $conference->getURL();
    foreach (DB::getAll(DB::T(DB::SEASON)) as $s) {
      $regs = $s->getConferenceParticipation($conference);
      if (count($regs) > 0) {
        $num++;
        $ul->add(new XLi(new XA($root . $s->shortString() . '/', $s->fullString())));
      }
    }
    if ($num > 0) {
      $this->addSection(
        new XDiv(
          array('id'=>'submenu-wrapper'),
          array(
            new XH3("Other seasons", array('class'=>'nav')),
            $ul
          )
        )
      );
    }
  }

}