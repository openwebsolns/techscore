<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-08-24
 * @package scripts
 */

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
  private $divPage = array();

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

      $this->page->addSection($p = new XPort("Summary"));
      $p->set('id', 'summary');
      foreach ($summaries as $h => $i) {
        $p->add(new XH4($h));
        $DPE->parse($i);
        $p->add(new XDiv(array(), array(new XRawText($DPE->toXML()))));
      }
    }

    // Scores, if any
    if ($reg->hasFinishes()) {
      if ($reg->scoring == Regatta::SCORING_TEAM) {
        $this->page->head->add(new XScript('text/javascript', '/inc/js/tr-full-link.js'));

        require_once('tscore/TeamRankingDialog.php');
        $maker = new TeamRankingDialog($reg);
        
        if ($reg->finalized === null) {
          $this->page->addSection($p = new XPort("Rankings"));
          $p->add(new XP(array(), array(new XEm("Note:"), " Preliminary results; teams ranked by winning percentage.")));
        }
        else
          $this->page->addSection($p = new XPort("Final Results"));
        foreach ($maker->getTable(true) as $elem)
          $p->add($elem);
      }
      else {
        require_once('tscore/ScoresDivisionalDialog.php');
        $maker = new ScoresDivisionalDialog($reg);
        $this->page->addSection($p = new XPort("Score summary"));
        foreach ($maker->getTable(true) as $elem)
          $p->add($elem);

        // SVG history diagram
        if (count($reg->getScoredRaces(($reg->scoring == Regatta::SCORING_COMBINED) ? Division::A() : null)) > 1) { 
          $this->page->addSection($p = new XPort("Score history"));
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
      $this->page->addSection($p = new XPort("No scores have been entered"));
      $p->add($xp = new XP(array('class'=>'notice'), "No scores have been entered yet for this regatta."));
      $rot = $reg->getRotation();
      if ($rot->isAssigned() || $reg->scoring == Regatta::SCORING_TEAM) {
        $xp->add(" ");
        $xp->add(new XA('rotations/', "View rotations."));
      }

      // Notice items?
      $docs = $reg->getDocuments();
      if (count($docs) > 0) {
        $DPE = null;
        $this->page->addSection($p = new XPort("Notices"));
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

    require_once('tscore/ScoresDivisionDialog.php');
    $maker = new ScoresDivisionDialog($reg, $div);
    $page->addSection($p = new XPort("Scores for Division $div"));
    $elems = $maker->getTable(true);
    if (count($elems) == 0)
      $p->add(new XP(array('class'=>'notice'), "No scores have been entered yet for Division $div."));
    else {
      foreach ($elems as $elem)
        $p->add($elem);

      // SVG history diagram
      if (count($reg->getScoredRaces($div)) > 1) {
        $page->addSection($p = new XPort("Score history"));
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
  }

  protected function fillFull() {
    if ($this->fullPage !== null) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $this->fullPage = new TPublicPage("Full scores | " . $reg->name . " | " . $season->fullString());
    $this->prepare($this->fullPage, 'full-scores');
    if ($reg->scoring == Regatta::SCORING_TEAM) {
      $this->fullPage->head->add(new XScript('text/javascript', '/inc/js/tr-full-select.js'));
      $this->fullPage->setDescription(sprintf("Scoring grids for all rounds in %s's %s.", $season->fullString(), $reg->name));

      require_once('tscore/TeamRankingDialog.php');
      $maker = new TeamRankingDialog($reg);
      $this->fullPage->addSection($p = new XPort("Ranking summary"));
      if ($reg->finalized === null)
        $p->add(new XP(array(), array(new XEm("Note:"), " Preliminary results; order may not be accurate due to unbroken ties and incomplete round robins.")));
      foreach ($maker->getSummaryTable(true) as $elem)
        $p->add($elem);
      
      require_once('tscore/ScoresGridDialog.php');
      $maker = new ScoresGridDialog($reg);
      $rounds = array();
      foreach ($reg->getScoredRounds() as $round)
        array_unshift($rounds, $round);
      foreach ($rounds as $round) {
        if (count($round->getSeeds()) > 0) {
          $this->fullPage->addSection($p = new XPort($round));
          $p->add($maker->getRoundTable($round));
        }
      }
    }
    else {
      $this->fullPage->setDescription(sprintf("Full scores table for %s's %s.", $season->fullString(), $reg->name));

      // Total scores
      require_once('tscore/ScoresFullDialog.php');
      $maker = new ScoresFullDialog($reg);
      $this->fullPage->addSection($p = new XPort("Race by race"));
      foreach ($maker->getTable(true) as $elem)
        $p->add($elem);
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
      require_once('tscore/TeamRotationDialog.php');
      $maker = new TeamRotationDialog($reg);

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

          $this->rotPage->addSection($p = new XPort($label));
          foreach ($maker->getTable($round, true) as $tab)
            $p->add($tab);
        }
      }
    }
    else {
      require_once('tscore/RotationDialog.php');
      $maker = new RotationDialog($reg);
      foreach ($reg->getRotation()->getDivisions() as $div) {
        $this->rotPage->addSection($p = new XPort("$div Division"));
        $p->add(new XRawText($maker->getTable($div, true)->toXML()));
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
    require_once('tscore/TeamRacesDialog.php');
    $maker = new TeamRacesDialog($reg);
    $this->allracesPage->addSection($p = new XPort("All races"));
    foreach ($maker->getTable(true) as $elem)
      $p->add($elem);
  }

  protected function fillSailors() {
    if ($this->sailorsPage !== null) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $this->sailorsPage = new TPublicPage(sprintf("%s Sailors | %s", $reg->name, $season->fullString()));
    $this->prepare($this->sailorsPage, 'sailors');
    $this->sailorsPage->setDescription(sprintf("Sailors participating in %s's %s.", $season->fullString(), $reg->name));

    require_once('tscore/TeamRegistrationsDialog.php');
    $maker = new TeamRegistrationsDialog($reg);
    $rounds = $reg->getScoredRounds();
    if (count($rounds) == 0) {
      $this->sailorsPage->addSection($p = new XPort("Sailors"));
      $p->add(new XP(array('class'=>'notice'), "There are no scored races yet in this regatta."));
    }
    else {
      $this->sailorsPage->addSection(new XP(array('class'=>'notice'), "Note that only races that have been scored are shown."));
      foreach ($rounds as $round) {
        $this->sailorsPage->addSection($p = new XPort($round));
        $p->add($maker->getRoundTable($round));
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

    $this->noticesPage->addSection($p = new XPort("Notice board"));
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

  protected function fillCombined() {
    if ($this->combinedPage !== null) return;

    $reg = $this->regatta;
    $season = $reg->getSeason();
    $this->combinedPage = new TPublicPage(sprintf("Scores for all Divisions | %s | %s", $reg->name, $season->fullString()));
    $this->prepare($this->combinedPage, 'divisions');
    $this->combinedPage->setDescription(sprintf("Scores and ranks across all divisions for %s's %s.",
                                                $season->fullString(), $reg->name));

    require_once('tscore/ScoresCombinedDialog.php');
    $maker = new ScoresCombinedDialog($reg);
    $this->combinedPage->addSection($p = new XPort("Scores for all divisions"));
    foreach ($maker->getTable(true) as $elem)
      $p->add($elem);
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
    $url = sprintf('/%s/', $season->id);
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
        elseif ($reg->scoring == Regatta::SCORING_COMBINED)
          $page->addMenu(new XA($url . 'divisions/', "All Divisions"));
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

    $rot = $reg->getRotation();
    if ($rot->isAssigned() || $reg->scoring == Regatta::SCORING_TEAM)
      $page->addMenu(new XA($url.'rotations/', "Rotations"));
    if ($reg->scoring == Regatta::SCORING_TEAM && $reg->hasFinishes())
      $page->addMenu(new XA($url.'sailors/', "Sailors"));

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
}
?>