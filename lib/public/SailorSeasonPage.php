<?php
/*
 * This file is part of Techscore
 *
 * @author Dayan Paez
 * @version 2015-01-20
 */

require_once('xml5/TPublicPage.php');

/**
 * Public profile page for a given sailor and season.
 *
 * @author Dayan Paez
 * @created 2015-01-20
 */
class SailorSeasonPage extends TPublicPage {

  private $sailor;
  private $season;

  /**
   * Creates a new public page for given sailor.
   *
   */
  public function __construct(Member $sailor, Season $season) {
    parent::__construct(sprintf("%s | %s", $sailor, $season->fullString()));
    $this->sailor = $sailor;
    $this->season = $season;
    $this->fill();
  }

  private function fill() {
    $this->fillHead();
    $this->fillNavigation();
    $this->fillBody();
    $this->fillSeasonLinks();
  }

  private function fillHead() {
    $this->body->set('class', 'sailor-page');
    $this->setDescription(
      sprintf(
        "Summary of activity for sailor %s during the %s season.",
        $this->sailor,
        $this->season->fullString()));

    $this->addMetaKeyword($this->sailor->getName());
    $this->addMetaKeyword($this->sailor->school);
    $this->addMetaKeyword($this->season->getSeason());
    $this->addMetaKeyword($this->season->getYear());
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
    $this->addMenu(new XA($this->season->getUrl(), $this->season->fullString()));
    $this->addMenu(new XA($this->sailor->school->getURL(), $this->sailor->school->nick_name));
    $this->addMenu(new XA($this->sailor->getURL(), $this->sailor));
  }

  /**
   * Fills the season summary
   *
   */
  private function fillBody() {
    $today = new DateTime();
    $today->setTime(0, 0);
    $tomorrow = new DateTime('tomorrow');
    $tomorrow->setTime(0, 0);

    $regs = $this->season->getSailorParticipation($this->sailor);
    $total = count($regs);
    $current = array(); // regattas happening NOW
    $past = array();    // past regattas from the current season
    $coming = array();  // upcoming schedule
    $placement = array(); // what place in which regatta, indexed by
                          // regatta ID

    // get average placement
    $overall_percentage = 0;
    $overall_total = 0;
    $total_races = 0;

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
      foreach ($rps as $rp) {
        $total_races += count($rp->races_nums);
        // If a sailor has participated in multiple teams, which
        // should not happen, merely report their place for the first
        // team encountered.
        if ($team === null) {
          $team = $rp->team;
          if ($team->dt_rank !== null) {
            $place = $team->dt_rank;
            $num_teams = count($reg->getTeams());

            $overall_percentage += (1 - ($place - 1) / $num_teams);
            $overall_total++;
            $placement[$reg->id] = sprintf('%d/%d', $place, $num_teams);
          } else {
            $placement[$reg->id] = 'N/A';
          }
        }
      }
    }

    // ------------------------------------------------------------
    // SAILOR sailing now
    if (count($current) > 0) {
      usort($current, 'Regatta::cmpTypes');
      $this->addSection($p = new XPort("Sailing now", array(), array('id'=>'sailing')));
      $p->add($tab = new XQuickTable(
                array('class'=>'participation-table'),
                array("Name", "Host", "Type", "Scoring", "Last race", "Place(s)")));

      foreach ($current as $row => $reg) {
        $status = null;
        switch ($reg->dt_status) {
        case Regatta::STAT_READY:
          $status = new XEm("No scores yet");
          break;

        default:
          $status = new XStrong(ucwords($reg->dt_status));
        }

        $link = new XA($reg->getURL(), $reg->name);
        $tab->addRow(
          array(
            $link,
            $reg->getHostVenue(),
            $reg->type,
            $reg->getDataScoring(),
            $status,
            $placement[$reg->id]),
          array('class' => 'row' . ($row % 2)));
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
    $season_link = new XA($this->season->getURL(), $this->season->fullString());
    $this->addSection($p = new XPort(array("Season history for ", $season_link)));
    $p->set('id', 'history');

    if (count($past) > 0) {
      $p->add($tab = new XQuickTable(
                array('class'=>'participation-table'),
                array("Name", "Host", "Type", "Scoring", "Date", "Status", "Place(s)")));

      foreach ($past as $row => $reg) {
        $link = new XA(
          $reg->getURL(), new XSpan($reg->name, array('itemprop'=>'name')),
          array('itemprop'=>'url'));

        $tab->addRow(
          array(
            $link,
            $reg->getHostVenue(),
            $reg->type,
            $reg->getDataScoring(),
            new XTime($reg->start_time, 'M d', array('itemprop'=>'startDate')),
            ($reg->finalized === null) ? "Pending" : new XStrong("Official"),
            $placement[$reg->id]),
          array(
            'class' => sprintf('row' . ($row % 2)),
            'itemprop'=>'event',
            'itemscope'=>'itemscope',
            'itemtype'=>'http://schema.org/SportsEvent'));
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

  private function fillSeasonLinks() {
    // ------------------------------------------------------------
    // Add links to previous active seasons
    $ul = new XUl(array('id'=>'other-seasons'));
    $num = 0;
    $root = $this->sailor->getURL();
    foreach (DB::getAll(DB::T(DB::SEASON)) as $s) {
      $regs = $s->getSailorParticipation($this->sailor);
      if (count($regs) > 0) {
        $num++;
        $ul->add(new XLi(new XA($root . $s->shortString() . '/', $s->fullString())));
      }
    }
    if ($num > 0)
      $this->addSection(
        new XDiv(
          array('id'=>'submenu-wrapper'),
          array(new XH3("Other seasons", array('class'=>'nav')),
                $ul)));
  }
}
?>