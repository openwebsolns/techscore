<?php
namespace pub;

use \data\SailorRegattaTable;

use \Conf;
use \DateTime;
use \DB;
use \Member;
use \Regatta;
use \Season;
use \STN;
use \TPublicPage;

use \XA;
use \XP;
use \XPort;
use \XQuickTable;

require_once('xml5/TPublicPage.php');

/**
 * Public profile page for a given sailor (all seasons).
 *
 * @author Dayan Paez
 * @created 2015-01-20
 */
class SailorPage extends TPublicPage {

  private $sailor;
  private $seasons;
  /**
   * @var Array Indexed by season ID.
   */
  private $regattasBySeasonId;

  /**
   * Creates a new public page for given sailor.
   *
   */
  public function __construct(Member $sailor) {
    parent::__construct(sprintf("%s", $sailor));
    $this->sailor = $sailor;

    $this->seasons = array();
    $this->regattasBySeasonId = array();
    foreach (DB::getAll(DB::T(DB::SEASON)) as $season) {
      $participation = $season->getSailorAttendance($sailor);
      if (count($participation) > 0) {
        $this->seasons[] = $season;
        $this->regattasBySeasonId[$season->id] = $participation;
      }
    }

    $this->fill();
  }

  private function fill() {
    $this->fillHead();
    $this->fillNavigation();
    $this->fillBody();
  }

  private function fillHead() {
    $this->body->set('class', 'sailor-page');
    $this->setDescription(
      sprintf(
        "Career activity for sailor %s.",
        $this->sailor
      )
    );

    $this->setPublicData($this->sailor->getPublicData());
    $this->addMetaKeyword($this->sailor->getName());
    $this->addMetaKeyword($this->sailor->school);
    $years = array();
    foreach ($this->seasons as $season) {
      $years[$season->getYear()] = $season->getYear();
      $this->addMetaKeyword($season);
    }
    foreach ($years as $year) {
      $this->addMetaKeyword($year);

    }
    $this->addSocialPlugins(true);

    // Social
    $url = sprintf('http://%s%s', Conf::$PUB_HOME, $this->sailor->getURL());
    $og = array('type'=>'website', 'url'=>$url);
    $this->setFacebookLike($url);
    $this->setOpenGraphProperties($og);

    $this->body->set('itemscope', 'itemscope');
    $this->body->set('itemtype', 'http://schema.org/Person');
  }

  private function fillNavigation() {
    $this->addMenu(new XA('/', "Home"));
    $this->addMenu(new XA('/schools/', "Schools"));
    $this->addMenu(new XA($this->sailor->school->getURL(), $this->sailor->school->nick_name));
    $this->addMenu(new XA($this->sailor->getURL(), $this->sailor));
  }

  /**
   * Fills the body of the page.
   *
   */
  private function fillBody() {
    foreach ($this->seasons as $season) {
      $this->fillSeason($season);
    }
  }

  /**
   * Fills an individual season's summary.
   *
   */
  private function fillSeason(Season $season) {
    $today = new DateTime();
    $today->setTime(0, 0);
    $tomorrow = new DateTime('tomorrow');
    $tomorrow->setTime(0, 0);

    $regs = $this->regattasBySeasonId[$season->id];
    $total = count($regs);
    $current = array(); // regattas happening NOW
    $past = array();    // past regattas from the current season
    $coming = array();  // upcoming schedule
    $placement = array(); // what place in which regatta, indexed by
                          // regatta ID

    foreach ($regs as $reg) {
      if ($reg->dt_status === null || $reg->dt_status == Regatta::STAT_SCHEDULED)
        continue;
      if ($reg->start_time < $tomorrow && $reg->end_date >= $today) {
        $current[] = $reg;
      }
      if ($reg->end_date < $today) {
        $past[] = $reg;
      }
      if ($reg->start_time >= $tomorrow) {
        $coming[] = $reg;
      }

      $manager = $reg->getRpManager();
      $rps = $manager->getParticipation($this->sailor);
      $team = null;
      $placement[$reg->id] = 'N/A';
      foreach ($rps as $rp) {
        // If a sailor has participated in multiple teams, which
        // should not happen, merely report their place for the first
        // team encountered.
        if ($team === null) {
          $team = $rp->team;
          if ($team->dt_rank !== null) {
            $place = $team->dt_rank;
            $num_teams = count($reg->getTeams());

            $placement[$reg->id] = sprintf('%d/%d', $place, $num_teams);
          }
        }
      }
    }

    // ------------------------------------------------------------
    // SAILOR sailing now
    if (count($current) > 0) {
      usort($current, 'Regatta::cmpTypes');
      $this->addSection($p = new XPort("Sailing now", array(), array('id'=>'sailing')));
      $p->add($tab = new SailorRegattaTable($this->sailor));

      foreach ($current as $row => $reg) {
        $tab->addRegattaRow($reg);
      }
    }
    // ------------------------------------------------------------
    // SAILOR coming soon: ONLY if there are no current ones
    elseif (count($coming) > 0) {
      usort($coming, 'Regatta::cmpTypes');
      $this->addSection($p = new XPort("Coming soon"));
      $p->add($tab = new XQuickTable(
                array('class'=>'coming-regattas'),
                array("Name", "Host", "Type", "Scoring", "Start time")));
      foreach ($coming as $reg) {
        $tab->addRow(
          array(
            new XA($reg->getURL(), $reg->name),
            $reg->getHostVenue(),
            $reg->type,
            $reg->getDataScoring(),
            $reg->start_time->format('m/d/Y @ H:i')));
      }
    }

    // ------------------------------------------------------------
    // SAILOR past regattas
    $season_link = new XA($season->getURL(), $season->fullString());
    $this->addSection($p = new XPort(array("Season history for ", $season_link)));
    $p->set('id', 'history');

    if (count($past) > 0) {
      $p->add($tab = new SailorRegattaTable($this->sailor));

      foreach ($past as $row => $reg) {
        $tab->addRegattaRow($reg);
      }
    }
    else {
      $p->add(
        new XP(
          array('class'=>'notice'),
          sprintf(
            "It appears %s has not participated in any regattas this season.",
            $this->sailor->getName()
          )
        )
      );
    }

    // ------------------------------------------------------------
    // SCHOOL season summary
    $school_link = new XA($this->sailor->school->getURL(), $this->sailor->school->nick_name);
    $conference_link = $this->sailor->school->conference;
    if (DB::g(STN::PUBLISH_CONFERENCE_SUMMARY) !== null) {
      $conference_link = new XA($this->sailor->school->conference->url, $conference_link);
    }
    $table = array(
      "Graduation Year" => $this->sailor->year,
      "School" => $school_link,
      DB::g(STN::CONFERENCE_TITLE) => $conference_link,
      "Number of Regattas" => $total);
    $this->setHeader($this->sailor->getName(), $table, array('itemprop'=>'name'));
  }
}
