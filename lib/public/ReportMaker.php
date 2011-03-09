<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-08-24
 */

/**
 * Creates the report page for the given regatta
 *
 */
class ReportMaker {
  public $regatta;
  public $dt_regatta;
  
  private $page;
  private $rotPage;
  private $fullPage;
  private $divPage = array();

  /**
   * Creates a new report for the given regatta
   *
   */
  public function __construct(Regatta $reg) {
    $this->regatta = $reg;
    $this->dt_regatta = DBME::get(DBME::$REGATTA, $reg->id());
  }

  protected function fill() {
    if ($this->page !== null) return;

    $reg = $this->regatta;
    $this->page = new TPublicPage($reg->get(Regatta::NAME));
    $this->prepare2($this->page);

    // Summary
    $stime = $reg->get(Regatta::START_TIME);
    $items = array();
    for ($i = 0; $i < $reg->get(Regatta::DURATION); $i++) {
      $today = new DateTime(sprintf("%s + %d days", $stime->format('Y-m-d'), $i));
      $comms = $reg->getSummary($today);
      if (strlen($comms) > 0) {
	$items[] = new XH4($today->format('l, F j:'));
	$items[] = new XP(array(), $comms);
	/*
	$items[] = new Heading($today->format('l, F j:'));
	$items [] = new Para($comms);
	*/
      }
    }
    if (count($items) > 0) {
      $this->page->addSection($p = new XDiv(array('class'=>'port'), array(new XH3("Summary"))));
      $p->set('id', 'summary');
      foreach ($items as $i)
	$p->add($i);
    }

    $link_schools = PUB_HOME.'/schools';

    // Divisional scores
    // $maker = new ScoresDivisionalDialog($reg);
    $maker = new TScoresTables($this->dt_regatta);
    $this->page->addSection($p = new XDiv(array('class'=>'port'), array(new XH3("Score summary"))));
    foreach ($maker->getSummaryTables() as $elem)
      $p->add($elem);
  }

  protected function fillDivision(Division $div) {
    if (isset($this->divPage[(string)$div])) return;

    $reg = $this->regatta;
    $page = new TPublicPage("Scores for division $div | " . $reg->get(Regatta::NAME));
    $this->divPage[(string)$div] = $page;
    $this->prepare2($page);
    
    $link_schools = PUB_HOME.'/schools';
    // $maker = new ScoresDivisionDialog($reg, $div);
    $maker = new TScoresTables($this->dt_regatta);
    $page->addSection($p = new XDiv(array('class'=>'port'), array(new XH3("Scores for Division $div"))));
    foreach ($maker->getDivisionTables((string)$div) as $elem)
      $p->add($elem);
  }

  protected function fillFull() {
    if ($this->fullPage !== null) return;
    
    $reg = $this->regatta;
    $this->fullPage = new TPublicPage("Full scores | " . $reg->get(Regatta::NAME));
    $this->prepare2($this->fullPage);
    
    $link_schools = PUB_HOME.'/schools';
    
    // Total scores
    // $maker = new ScoresFullDialog($reg);
    $maker = new TScoresTables($this->dt_regatta);
    $this->fullPage->addSection($p = new XDiv(array('class'=>'port'), array(new XH3("Race by race"))));
    foreach ($maker->getFullTables() as $elem)
      $p->add($elem);
  }

  protected function fillRotation() {
    if ($this->rotPage !== null) return;

    $reg = $this->regatta;
    $this->rotPage = new TPublicPage(sprintf("%s Rotations", $reg->get(Regatta::NAME)));
    $this->prepare2($this->rotPage);

    $maker = new RotationDialog($reg);
    foreach ($reg->getRotation()->getDivisions() as $div) {
      $this->rotPage->addSection($p = new XDiv(array('class'=>'port'), array(new XH3("$div Division"))));
      $p->add(new XRawText($maker->getTable($div)->toHTML()));
    }
  }

  protected function prepare(TPublicPage $page) {
    $reg = $this->regatta;
    $page->addNavigation(new Link(".", $reg->get(Regatta::NAME), array("class"=>"nav")));
    
    // Links to season
    $season = $reg->get(Regatta::SEASON);
    $page->addNavigation(new Link("..", $season->fullString(),
				  array("class"=>"nav", "accesskey"=>"u")));

    // Javascript?
    $now = new DateTime('today');
    if ($reg->get(Regatta::START_TIME) <= $now &&
	$reg->get(Regatta::END_DATE)   >= $now) {
      $page->head->addChild(new GenericElement('script', array(new Text("")),
					       array('type'=>'text/javascript',
						     'src'=>'/inc/js/refresh.js')));
    }

    // Regatta information
    $page->addSection($div = new Div());
    $div->addChild(new GenericElement("h2", array(new Text($reg->get(Regatta::NAME)))));
    $div->addAttr("id",    "reg-details");
    
    $stime = $reg->get(Regatta::START_TIME);
    $etime = $reg->get(Regatta::END_DATE);
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
      $schools[$host->school->id] = $host->school->nick_name;

    $type = sprintf('%s Regatta', ucfirst($reg->get(Regatta::TYPE)));
    $div->addChild($l = new Itemize());
    $l->addItems(new LItem(implode("/", $schools)),
		 new LItem($date),
		 new LItem($type),
		 new LItem(implode("/", $reg->getBoats())));
  }

  protected function prepare2(TPublicPage $page) {
    $reg = $this->regatta;
    $page->addNavigation(new XA('.', $reg->get(Regatta::NAME), array('class'=>'nav')));
    
    // Links to season
    $season = $reg->get(Regatta::SEASON);
    $page->addNavigation(new XA('..', $season->fullString(), array('class'=>'nav', 'accesskey'=>'u')));

    // Javascript?
    $now = new DateTime('today');
    if ($reg->get(Regatta::START_TIME) <= $now &&
	$reg->get(Regatta::END_DATE)   >= $now) {
      // $page->head->add(new XScript('text/javascript', '/inc/js/refresh.js'));
    }

    // Regatta information
    $page->addSection($div = new XDiv(array('id'=>'reg-details')));
    $div->add(new XH2($reg->get(Regatta::NAME)));
    
    $stime = $reg->get(Regatta::START_TIME);
    $etime = $reg->get(Regatta::END_DATE);
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
      $schools[$host->school->id] = $host->school->nick_name;

    $type = sprintf('%s Regatta', ucfirst($reg->get(Regatta::TYPE)));
    $div->add(new XUl(array(),
		      array(new XLi(implode("/", $schools)),
			    new XLi($date),
			    new XLi($type),
			    new XLi(implode("/", $reg->getBoats())))));
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