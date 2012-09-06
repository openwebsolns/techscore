<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-08-24
 * @package scripts
 */

/**
 * Creates the report page for the given regatta, which will be used
 * in the public facing side
 *
 */
class ReportMaker {
  public $regatta;
  public $dt_regatta;
  
  private $page;
  private $rotPage;
  private $fullPage;
  private $divPage = array();

  private $summary = array();

  /**
   * Creates a new report for the given regatta
   *
   */
  public function __construct(Regatta $reg) {
    $this->regatta = $reg;
    $this->dt_regatta = DB::get(DB::$DT_REGATTA, $reg->id);
  }

  /**
   * Fills the front page of a regatta.
   *
   * When there are no scores, the front page shall include a brief
   * message. If there are rotations, a link to view rotations.
   *
   */
  protected function fill() {
    if ($this->page !== null) return;

    $reg = $this->regatta;
    $this->page = new TPublicPage($reg->name);
    $this->prepare($this->page);

    // Summary
    if (count($this->summary) > 0) {
      $this->page->addSection($p = new XPort("Summary"));
      $p->set('id', 'summary');
      foreach ($this->summary as $h => $i) {
	$p->add(new XH4($h));
	$p->add(new XP(array(), $i));
      }
    }

    $link_schools = '/schools';

    // Divisional scores, if any
    if ($reg->hasFinishes()) {
      require_once('tscore/ScoresDivisionalDialog.php');
      $maker = new ScoresDivisionalDialog($reg);
      $this->page->addSection($p = new XPort("Score summary"));
      foreach ($maker->getTable('', '/schools') as $elem)
	$p->add($elem);
    }
    else {
      $this->page->addSection($p = new XPort("No scores have been entered"));
      $p->add($xp = new XP(array('class'=>'notice'), "No scores have been entered yet for this regatta."));
      $rot = $reg->getRotation();
      if ($rot->isAssigned()) {
	$xp->add(" ");
	$xp->add(new XA('rotations/', "View rotations."));
      }
    }
  }

  protected function fillDivision(Division $div) {
    if (isset($this->divPage[(string)$div])) return;

    $reg = $this->regatta;
    $page = new TPublicPage("Scores for division $div | " . $reg->name);
    $this->divPage[(string)$div] = $page;
    $this->prepare($page);
    
    require_once('tscore/ScoresDivisionDialog.php');
    $maker = new ScoresDivisionDialog($reg, $div);
    $page->addSection($p = new XPort("Scores for Division $div"));
    foreach ($maker->getTable('', '/schools') as $elem)
      $p->add($elem);
  }

  protected function fillFull() {
    if ($this->fullPage !== null) return;
    
    $reg = $this->regatta;
    $this->fullPage = new TPublicPage("Full scores | " . $reg->name);
    $this->prepare($this->fullPage);
    
    $link_schools = '/schools';
    
    // Total scores
    require_once('tscore/ScoresFullDialog.php');
    $maker = new ScoresFullDialog($reg);
    $this->fullPage->addSection($p = new XPort("Race by race"));
    foreach ($maker->getTable('', '/schools') as $elem)
      $p->add($elem);
  }

  protected function fillRotation() {
    if ($this->rotPage !== null) return;

    $reg = $this->regatta;
    $this->rotPage = new TPublicPage(sprintf("%s Rotations", $reg->name));
    $this->prepare($this->rotPage);

    require_once('tscore/RotationDialog.php');
    $maker = new RotationDialog($reg);
    foreach ($reg->getRotation()->getDivisions() as $div) {
      $this->rotPage->addSection($p = new XPort("$div Division"));
      $p->add(new XRawText($maker->getTable($div)->toXML()));
    }
  }

  /**
   * Prepares the basic elements common to all regatta public pages
   * such as the navigation menu and the regatta description.
   *
   */
  protected function prepare(TPublicPage $page) {
    $reg = $this->regatta;
    $page->addNavigation(new XA($reg->getURL(), $reg->name, array('class'=>'nav')));

    // Add description
    $desc = "";
    $stime = $reg->start_time;
    $this->summary = array();
    for ($i = 0; $i < $reg->getDuration(); $i++) {
      $today = new DateTime(sprintf("%s + %d days", $stime->format('Y-m-d'), $i));
      $comms = $reg->getSummary($today);
      if (strlen($comms) > 0) {
	$this->summary[$today->format('l, F j:')] = $comms;
	$desc .= $comms;
      }
    }
    $meta_desc = "";
    $desc = explode(" ", $desc);
    while (count($desc) > 0 && strlen($meta_desc) < 150)
      $meta_desc .= (' ' . array_shift($desc));
    if (count($desc) > 0)
      $meta_desc .= '...';
    if (strlen($meta_desc) > 1)
      $page->head->add(new XMeta('description', $meta_desc));
    
    // Links to season
    $season = $reg->getSeason();
    $url = sprintf('/%s/', $season->id);
    $page->addNavigation(new XA($url, $season->fullString(), array('class'=>'nav', 'accesskey'=>'u')));

    // Javascript?
    $now = new DateTime('today');
    if ($reg->start_time <= $now &&
	$reg->end_date   >= $now) {
      // $page->head->add(new XScript('text/javascript', '/inc/js/refresh.js'));
    }

    // Regatta information
    $page->addSection($div = new XDiv(array('id'=>'reg-details')));
    $div->add(new XH2($reg->name));
    
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

    $hosts = $reg->getHosts();
    $schools = array();
    foreach ($hosts as $host)
      $schools[$host->id] = $host->nick_name;

    $types = Regatta::getTypes();
    $type = sprintf('%s Regatta', $types[$reg->type]);

    $boats = array();
    foreach ($reg->getBoats() as $boat)
      $boats[] = (string)$boat;

    $div->add(new XUl(array(),
		      array(new XLi(implode("/", $schools)),
			    new XLi($date),
			    new XLi($type),
			    new XLi(implode("/", $boats)))));

    // Menu
    $url = $reg->getURL();
    $rot = $reg->getRotation();
    if ($rot->isAssigned())
      $page->addMenu(new XA($url.'rotations/', "Rotations"));
    $page->addMenu(new XA($url, "Report"));
    if ($reg->hasFinishes()) {
      $page->addMenu(new XA($url.'full-scores/', "Full Scores"));
      if (!$reg->isSingleHanded()) {
	foreach ($reg->getDivisions() as $div)
	  $page->addMenu(new XA($url.$div.'/', "$div Scores"));
      }
    }
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
}
?>