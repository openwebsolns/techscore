<?php
namespace pub;

use \data\RotationTable;
use \data\DivisionScoresTableCreator;
use \data\CombinedScoresTableCreator;
use \data\FleetScoresTableCreator;
use \data\FullScoresTableCreator;
use \data\PenaltiesTableCreator;
use \data\RegistrationsTable;
use \data\TeamRotationTable;
use \data\TeamRankingTableCreator;
use \data\TeamSummaryRankingTableCreator;
use \data\TeamRacesTable;
use \data\TeamRegistrationsTable;
use \data\TeamScoresGrid;
use \rotation\descriptors\AggregatedRotationDescriptor;
use \rotation\descriptors\RotationDescriptor;

use \Conf;
use \DB;
use \DateTime;
use \Division;
use \FullRegatta;
use \InvalidArgumentException;
use \Regatta;
use \STN;
use \TPublicPage;
use \TSEditor;

use \XA;
use \XDiv;
use \XElem;
use \XEm;
use \XH4;
use \XH5;
use \XLi;
use \XP;
use \XPort;
use \XScript;
use \XSpan;
use \XText;
use \XUl;
use \XWarning;

require_once('xml5/TPublicPage.php');

/**
 * Creates the report page for the given regatta, which will be used
 * in the public facing side
 *
 */
class ReportMaker {
  public $regatta;

  private $page;
  private $rotPage;
  private $combinedPage;
  private $fullPage;
  private $allracesPage;
  private $sailorsPage;
  private $noticesPage;
  private $registrationsPage;
  private $divPage = array();

  private $rotationDescriptor;

  /**
   * Creates a new report for the given regatta
   *
   */
  public function __construct(FullRegatta $reg) {
    $this->regatta = $reg;
  }

  /**
   * Fills the front page of a regatta.
   *
   * When there are no scores, the front page shall include a brief
   * message. If there are rotations, a link to view rotations.
   *
   * 2013-02-19: Team racing regattas: include ranking table, and
   * possible message
   */
  protected function fill() {
    if ($this->page !== null) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $this->page = new TPublicPage($reg->name . " | " . $season->fullString());
    $this->prepare($this->page);
    $this->page->setDescription(sprintf("Summary report for %s's %s.", $season->fullString(), $reg->name));

    // Daily summaries
    $stime = $reg->start_time;
    $summaries = array();
    for ($i = 0; $i < $reg->getDuration(); $i++) {
      $today = new DateTime(sprintf("%s + %d days", $stime->format('Y-m-d'), $i));
      $comms = $reg->getSummary($today);
      if (strlen($comms) > 0)
        $summaries[$today->format('l, F j:')] = $comms;
    }
    if (count($summaries) > 0) {
      // Use DPEditor goodness
      require_once('xml5/TSEditor.php');
      $DPE = new TSEditor();

      $this->page->addSection($p = $this->newXPort("Summary"));
      $p->set('id', 'summary');
      foreach ($summaries as $h => $i) {
        $p->add(new XH4($h));
        $p->add(new XDiv(array(), $DPE->parse($i)));
      }
    }

    // Scores, if any
    if ($reg->hasFinishes()) {
      if ($reg->scoring == Regatta::SCORING_TEAM) {

        $maker = new TeamRankingTableCreator($reg, true);
        
        if ($reg->finalized === null) {
          $this->page->addSection($p = $this->newXPort("Rankings"));
          $p->add(new XP(array(), array(new XEm("Note:"), " Preliminary results; teams ranked by winning percentage.")));
        }
        else
          $this->page->addSection($p = $this->newXPort("Final Results"));
        $p->add($maker->getRankTable());
        $legend = $maker->getLegendTable();
        if ($legend !== null) {
          $p->add($legend);
        }
      }
      else {
        $maker = new FleetScoresTableCreator($reg, true);
        $this->page->addSection($p = $this->newXPort("Score summary"));
        $p->add($maker->getScoreTable());
        $legend = $maker->getLegendTable();
        if ($legend !== null) {
          $p->add($legend);
        }

        // SVG history diagram
        if (count($reg->getScoredRaces(($reg->scoring == Regatta::SCORING_COMBINED) ? Division::A() : null)) > 1) { 
          $this->page->addSection($p = $this->newXPort("Score history", false));
          $p->set('id', 'history-port');
          $p->add(new XDiv(array('id'=>'history-expl'),
                           array(new XP(array(), "The following chart shows the relative rank of the teams as of the race indicated. Note that the races are ordered by number, then division, which may not represent the order in which the races were actually sailed."),
                                 new XP(array(), "The first place team as of a given race will always be at the top of the chart. The spacing from one team to the next shows relative gains/losses made from one race to the next. You may hover over the data points to display the total score as of that race."))));
          $p->add($sub = new XDiv(array('class'=>'chart-container'),
                                  array(new XElem('object', array('data'=>'history.svg', 'type'=>'image/svg+xml'),
                                                  array(new XP(array('class'=>'notice'),
                                                               array("Your browser does not support embedded SVG elements. ",
                                                                     new XA('history.svg', "View the history chart."))))))));
        }
      }
    }
    else {
      $this->page->addSection($p = $this->newXPort("No scores have been entered"));
      $p->add($xp = new XP(array('class'=>'notice'), "No scores have been entered yet for this regatta."));
      $rot = $reg->getRotationManager();
      if ($rot->isAssigned() || $reg->scoring == Regatta::SCORING_TEAM) {
        $xp->add(" ");
        $xp->add(new XA('rotations/', "View rotations."));
      }

      // Notice items?
      $docs = $reg->getDocuments();
      if (count($docs) > 0) {
        $DPE = null;
        $this->page->addSection($p = $this->newXPort("Notices", false));
        foreach ($docs as $doc) {
          $p->add($d = new XDiv(array('class'=>'notice-item'),
                                array(new XH4(new XA(sprintf('notices/%s', $doc->url), $doc->name), array('class'=>'notice-title')))));
          if ($doc->description !== null) {
            if ($DPE === null) {
              // Use DPEditor goodness
              require_once('xml5/TSEditor.php');
              $DPE = new TSEditor();
            }
            $d->add(new XDiv(array('class'=>'notice-description'), $DPE->parse($doc->description)));
          }
          if (in_array($doc->filetype, array('image/jpeg', 'image/png', 'image/gif')))
            $d->add(new XP(array('class'=>'notice-preview'), $doc->asImg('notices/' . $doc->url, sprintf("[Preview for %s]", $doc->name))));
        }
      }
    }
  }

  protected function fillDivision(Division $div) {
    if (isset($this->divPage[(string)$div])) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $page = new TPublicPage("Scores for division $div | " . $reg->name  . " | " . $season->fullString());
    $this->divPage[(string)$div] = $page;
    $this->prepare($page, (string)$div);
    $page->setDescription(sprintf("Scores for Division %s for %s's %s.",
                                  $div, $season->fullString(), $reg->name));

    $maker = new DivisionScoresTableCreator($reg, $div, true);
    $page->addSection($p = $this->newXPort("Scores for Division $div"));
    try {
      $p->add($maker->getScoreTable());
      $legend = $maker->getLegendTable();
      if ($legend !== null) {
        $p->add($legend);
      }
    } catch (InvalidArgumentException $e) {
      $p->add(new XP(array('class'=>'notice'), "No scores have been entered yet for Division $div."));
      return;
    }
      
    // SVG history diagram
    if (count($reg->getScoredRaces($div)) > 1) {
      $page->addSection($p = $this->newXPort("Score history", false));
      $p->set('id', 'history-port');
      $p->add(new XDiv(array('id'=>'history-expl'),
                       array(new XP(array(), "The following chart shows the relative rank of the teams as of the race indicated."),
                             new XP(array(), "The first place team as of a given race will always be at the top of the chart. The spacing from one team to the next shows relative gains/losses made from one race to the next. You may hover over the data points to display the total score as of that race."))));
      $p->add($sub = new XDiv(array('class'=>'chart-container'),
                              array(new XElem('object', array('data'=>'history.svg', 'type'=>'image/svg+xml'),
                                              array(new XP(array('class'=>'notice'),
                                                           "Your browser does not support SVG elements."))))));
    }
  }

  protected function fillFull() {
    if ($this->fullPage !== null) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $this->fullPage = new TPublicPage("Full scores | " . $reg->name . " | " . $season->fullString());
    $this->prepare($this->fullPage, 'full-scores');
    if ($reg->scoring == Regatta::SCORING_TEAM) {
      $this->fullPage->setDescription(sprintf("Scoring grids for all rounds in %s's %s.", $season->fullString(), $reg->name));


      $maker = new TeamSummaryRankingTableCreator($reg, true);
      $this->fullPage->addSection($p = $this->newXPort("Ranking summary"));
      if ($reg->finalized === null)
        $p->add(new XP(array(), array(new XEm("Note:"), " Preliminary results; order may not be accurate due to unbroken ties and incomplete round robins.")));
      $p->add($maker->getRankTable());
      $legend = $maker->getLegendTable();
      if ($legend !== null) {
        $p->add($legend);
      }

      $maker = new PenaltiesTableCreator($reg, true);
      $table = $maker->getPenaltiesTable();
      if ($table !== null) {
        $this->fullPage->addSection($p = $this->newXPort("Penalties", false));
        $p->add($table);
      }
      
      $rounds = array();
      foreach ($reg->getScoredRounds() as $round)
        array_unshift($rounds, $round);
      foreach ($rounds as $round) {
        if (count($round->getSeeds()) > 0) {
          try {
            $grid = new TeamScoresGrid($reg, $round);
            $this->fullPage->addSection($p = $this->newXPort($round, false));
            $p->add($grid);
          } catch (InvalidArgumentException $e) {
          }
        }
      }
    }
    else {
      $this->fullPage->setDescription(sprintf("Full scores table for %s's %s.", $season->fullString(), $reg->name));

      // Total scores
      $maker = new FullScoresTableCreator($reg, true);
      $this->fullPage->addSection($p = $this->newXPort("Race by race"));
      $p->add($maker->getScoreTable());
      $legend = $maker->getLegendTable();
      if ($legend !== null) {
        $p->add($legend);
      }
    }
  }

  protected function fillRotation() {
    if ($this->rotPage !== null) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $this->rotPage = new TPublicPage(sprintf("%s Rotations | %s", $reg->name, $season->fullString()));
    $this->prepare($this->rotPage, 'rotations');
    $this->rotPage->setDescription(sprintf("Sail rotations in all races for %s's %s.", $season->fullString(), $reg->name));

    if ($reg->scoring == Regatta::SCORING_TEAM) {
      $this->rotPage->head->add(new XScript('text/javascript', '/inc/js/tr-rotation-select.js'));

      $covered = array();
      foreach ($this->regatta->getRounds() as $round) {
        if (!isset($covered[$round->id])) {
          $covered[$round->id] = $round;
          $label = (string)$round;
          if ($round->round_group !== null) {
            foreach ($round->round_group->getRounds() as $i => $other) {
              if ($i > 0) {
                $label .= ", " . $other;
                $covered[$other->id] = $other;
              }
            }
          }

          $this->rotPage->addSection($p = $this->newXPort($label, false));
          $p->add(new TeamRotationTable($reg, $round, true));
        }
      }
    }
    else {
      $rotationManager = $reg->getRotationManager();
      $fleetRotation = $rotationManager->getFleetRotation();
      if ($fleetRotation !== null) {
        $descriptor = $this->getRotationDescriptor();
        $this->rotPage->addSection($p = $this->newXPort("Description"));
        $p->add(new XP(array(), $descriptor->describe($fleetRotation)));
      }
      foreach ($rotationManager->getDivisions() as $div) {
        $this->rotPage->addSection($p = $this->newXPort("$div Division", $div == Division::A()));
        $p->add(new RotationTable($reg, $div, true));
      }
    }
  }

  protected function fillAllRaces() {
    if ($this->allracesPage !== null) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $this->allracesPage = new TPublicPage(sprintf("%s Rotations | %s", $reg->name, $season->fullString()));
    $this->prepare($this->allracesPage, 'all');
    $this->allracesPage->setDescription(sprintf("Sail rotations in all races for %s's %s.", $season->fullString(), $reg->name));

    $this->allracesPage->head->add(new XScript('text/javascript', '/inc/js/tr-allraces-select.js'));
    $this->allracesPage->addSection($p = $this->newXPort("All races"));
    if (count($reg->getRaces()) == 0) {
      $p->add(new XWarning("There are no races for this regatta."));
    }
    else {
      $p->add(new TeamRacesTable($reg, true));
    }
  }

  protected function fillSailors() {
    if ($this->sailorsPage !== null) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $this->sailorsPage = new TPublicPage(sprintf("%s Sailors | %s", $reg->name, $season->fullString()));
    $this->prepare($this->sailorsPage, 'sailors');
    $this->sailorsPage->setDescription(sprintf("Sailors participating in %s's %s.", $season->fullString(), $reg->name));

    $rounds = $reg->getScoredRounds();
    if (count($rounds) == 0) {
      $this->sailorsPage->addSection($p = $this->newXPort("Sailors", false));
      $p->add(new XP(array('class'=>'notice'), "There are no scored races yet in this regatta."));
    }
    else {
      $this->sailorsPage->addSection(new XP(array('class'=>'notice'), "Note that only races that have been scored are shown."));
      foreach ($rounds as $round) {
        $this->sailorsPage->addSection($p = $this->newXPort($round, false));
        $p->add(new TeamRegistrationsTable($reg, $round, true));
      }
    }
  }

  protected function fillNotices() {
    if ($this->noticesPage !== null) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $this->noticesPage = new TPublicPage(sprintf("%s Notice Board | %s", $reg->name, $season->fullString()));
    $this->prepare($this->noticesPage, 'notices');
    $this->noticesPage->setDescription(sprintf("Notice board and supporting documents for %s's %s.", $season->fullString(), $reg->name));

    $this->noticesPage->addSection($p = $this->newXPort("Notice board"));
    $docs = $reg->getDocuments();
    if (count($docs) == 0) {
      $p->add(new XP(array('class'=>'notice'), "No notices have been posted at this time."));
    }
    else {
      $DPE = null;
      foreach ($docs as $doc) {
        $p->add($d = new XDiv(array('class'=>'notice-item'),
                              array(new XH4(new XA($doc->url, $doc->name), array('class'=>'notice-title')))));


        $races = $reg->getDocumentRaces($doc);
        if (count($races) > 0) {
          $d->add(new XDiv(array('class'=>'notice-races'),
                           array(new XH5("Races"),
                                 $ul = new XUl(array('class'=>'notice-races-list')))));
          $list = array();
          foreach ($races as $race) {
            $div = (string)$race->division;
            if (!isset($list[$div]))
              $list[$div] = array();
            $list[$div][] = $race->number;
          }

          foreach ($list as $div => $nums) {
            $ul->add($li = new XLi(""));
            if ($reg->getEffectiveDivisionCount() > 1) {
              $li->add(new XSpan("Division " . $div . ":", array('class'=>'notice-division')));
              $li->add(" ");
            }
            $li->add(new XSpan(str_replace(",", ", ", DB::makeRange($nums)), array('class'=>'notice-race-nums')));
          }
        }


        if ($doc->description !== null) {
          if ($DPE === null) {
            // Use DPEditor goodness
            require_once('xml5/TSEditor.php');
            $DPE = new TSEditor();
          }
          $d->add(new XDiv(array('class'=>'notice-description'), $DPE->parse($doc->description)));
        }
        if (in_array($doc->filetype, array('image/jpeg', 'image/png', 'image/gif')))
          $d->add(new XP(array('class'=>'notice-preview'), $doc->asImg($doc->url, sprintf("[Preview for %s]", $doc->name))));
      }
    }
  }

  protected function fillRegistrations() {
    if ($this->registrationsPage !== null) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $this->registrationsPage = new TPublicPage(sprintf("%s Sailors | %s", $reg->name, $season->fullString()));
    $this->prepare($this->registrationsPage, 'sailors');
    $this->registrationsPage->setDescription(sprintf("Record of participation for %s's %s.", $season->fullString(), $reg->name));

    $this->registrationsPage->addSection($p = $this->newXPort("Registrations"));
    $p->add(new RegistrationsTable($reg, true));
  }

  protected function fillCombined() {
    if ($this->combinedPage !== null) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $this->combinedPage = new TPublicPage(sprintf("Scores for all Divisions | %s | %s", $reg->name, $season->fullString()));
    $this->prepare($this->combinedPage, 'divisions');
    $this->combinedPage->setDescription(sprintf("Scores and ranks across all divisions for %s's %s.",
                                                $season->fullString(), $reg->name));

    $maker = new CombinedScoresTableCreator($reg, true);
    $this->combinedPage->addSection($p = $this->newXPort("Scores for all divisions"));
    $p->add($maker->getScoreTable());
    $legend = $maker->getLegendTable();
    if ($legend !== null) {
      $p->add($legend);
    }
  }

  protected function newXPort($title, $inc_sponsor = true, Array $attrs = array()) {
    $port = new XPort($title, $attrs);
    if ($inc_sponsor && DB::g(STN::REGATTA_SPONSORS) && $this->regatta->sponsor !== null) {
      $file = $this->regatta->sponsor->regatta_logo;
      $link = $file->asImg(
        sprintf('/inc/img/%s', $file->id),
        $this->regatta->sponsor,
        array('class'=>'sp'));
      if ($this->regatta->sponsor->url !== null)
        $link = new XA($this->regatta->sponsor->url, $link);
      $port->add(new XDiv(
                   array('class'=>'sp-block'),
                   array(new XSpan("Sponsored by ", array('class'=>'sp-leadin')), $link)));
    }
    return $port;
  }

  /**
   * Prepares the basic elements common to all regatta public pages
   * such as the navigation menu and the regatta description.
   *
   */
  protected function prepare(TPublicPage $page, $sub = null) {
    $reg = $this->regatta;
    $page->addMenu(new XA('/', "Home"));
    $page->addMetaKeyword($reg->name);
    $page->addMetaKeyword('results');
    $page->addSocialPlugins(true);

    $page->body->set('itemscope', 'itemscope');
    $page->body->set('itemtype', 'http://schema.org/SportsEvent');

    // Menu
    // Links to season
    $season = $reg->getSeason();
    $url = $season->getURL();
    $page->addMenu(new XA($url, $season->fullString()));
    $page->addMetaKeyword($season->getSeason());
    $page->addMetaKeyword($season->getYear());

    $url = $reg->getURL();

    // Metadata
    $page_url = sprintf('http://%s%s', Conf::$PUB_HOME, $url);
    if ($sub !== null)
      $page_url .= $sub . '/';
    $page->setFacebookLike($page_url);
    $opengraph = array('url'=>$page_url, 'type'=>'event', 'og:event:start_time'=>$reg->start_time->format('Y-m-d\TH:iP'));

    $page->addMenu(new XA($url, "Report", array('itemprop'=>'url')));
    if ($reg->hasFinishes()) {
      $page->addMenu(new XA($url.'full-scores/', "Full Scores"));
      if (!$reg->isSingleHanded()) {
        if ($reg->scoring == Regatta::SCORING_STANDARD) {
          foreach ($reg->getDivisions() as $div)
            $page->addMenu(new XA($url . $div.'/', "Division $div"));
        }
        elseif ($reg->scoring == Regatta::SCORING_COMBINED) {
          $page->addMenu(new XA($url . 'divisions/', "All Divisions"));
        }
        $page->addMenu(new XA($url . 'sailors/', "Sailors"));
      }
      if ($reg->scoring == Regatta::SCORING_TEAM) {
        $page->addMenu(new XA($url . 'all/', "All Races"));
      }
      // Winning?
      $tms = $reg->getRankedTeams();
      if ($reg->finalized !== null && $tms[0]->school->hasBurgee('square')) {
        $imgurl = sprintf('http://%s/inc/img/schools/%s-sq.png', Conf::$PUB_HOME, $tms[0]->school->id);
        $page->setTwitterImage($imgurl);
        $opengraph['image'] = $imgurl;
      }
    }

    $page->setOpenGraphProperties($opengraph);

    $rot = $reg->getRotationManager();
    if ($rot->isAssigned() || $reg->scoring == Regatta::SCORING_TEAM)
      $page->addMenu(new XA($url.'rotations/', "Rotations"));
    if (count($reg->getDocuments()) > 0)
      $page->addMenu(new XA($url.'notices/', "Notice Board"));

    // Regatta information
    $stime = $reg->start_time;
    $etime = $reg->end_date;
    if ($stime->format('Y-m-d') == $etime->format('Y-m-d')) // same day
      $date = $stime->format('F j, Y');
    elseif ($stime->format('Y-m') == $etime->format('Y-m')) // same month
      $date = sprintf("%s-%s", $stime->format('F j'), $etime->format('j, Y'));
    elseif ($stime->format('Y') == $etime->format('Y')) // same year
      $date = sprintf('%s - %s', $stime->format('F j'), $etime->format('F j, Y'));
    else // different year
      $date = $stime->format('F j, Y') . ' - ' . $etime->format('F y, Y');

    $type = sprintf('%s Regatta', $reg->type);

    $boats = array();
    foreach ($reg->getBoats() as $boat)
      $boats[] = (string)$boat;

    $table = array("Host" => new XSpan($reg->getHostVenue(), array('itemprop'=>'location')),
                   "Date" => new XElem('time', array('datetime'=>$reg->start_time->format('Y-m-d\TH:i'),
                                                     'itemprop'=>'startDate'),
                                       array(new XText($date))),
                   "Type" => new XSpan($type, array('itemprop'=>'description')),
                   "Boat" => implode("/", $boats),
                   "Scoring" => $reg->getDataScoring());
    $page->setHeader($reg->name, $table, array('itemprop'=>'name'));
  }

  /**
   * Generates and returns the HTML code for the given regatta. Note that
   * the report is only generated once per report maker
   *
   * @return TPublicPage
   */
  public function getScoresPage() {
    $this->fill();
    return $this->page;
  }

  /**
   * Generates and returns the HTML code for the full scores
   *
   * @return TPublicPage
   */
  public function getFullPage() {
    $this->fillFull();
    return $this->fullPage;
  }

  /**
   * Generates and returns the HTML code for the given division
   *
   * @param Division $div
   * @return TPublicPage
   */
  public function getDivisionPage(Division $div) {
    $this->fillDivision($div);
    return $this->divPage[(string)$div];
  }

  /**
   * Generates the rotation page, if applicable
   *
   * @return TPublicPage
   * @throws InvalidArgumentException should there be no rotation available
   */
  public function getRotationPage() {
    $this->fillRotation();
    return $this->rotPage;
  }

  /**
   * Generates the all races page
   *
   * @return TPublicPage
   */
  public function getAllRacesPage() {
    $this->fillAllRaces();
    return $this->allracesPage;
  }

  public function getSailorsPage() {
    $this->fillSailors();
    return $this->sailorsPage;
  }

  public function getNoticesPage() {
    $this->fillNotices();
    return $this->noticesPage;
  }

  public function getRegistrationsPage() {
    $this->fillRegistrations();
    return $this->registrationsPage;
  }

  /**
   * Generates the combined division page
   *
   * @return TPublicPage
   * @throws InvalidArgumentException if the regatta is not combined scoring
   */
  public function getCombinedPage() {
    $this->fillCombined();
    return $this->combinedPage;
  }

  private function getRotationDescriptor() {
    if ($this->rotationDescriptor == null) {
      $this->rotationDescriptor = new AggregatedRotationDescriptor();
    }
    return $this->rotationDescriptor;
  }

  public function setRotationDescriptor(RotationDescriptor $descriptor) {
    $this->rotationDescriptor = $descriptor;
  }
}
