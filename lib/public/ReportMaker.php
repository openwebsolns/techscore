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
  }

  protected function fill() {
    if ($this->page !== null) return;

    $reg = $this->regatta;
    $this->page = new TPublicPage($reg->get(Regatta::NAME));
    $this->prepare($this->page);

    // Summary
    $stime = $reg->get(Regatta::START_TIME);
    $items = array();
    for ($i = 0; $i < $reg->get(Regatta::DURATION); $i++) {
      $today = new DateTime(sprintf("%s + %d days", $stime->format('Y-m-d'), $i));
      $comms = $reg->getSummary($today);
      if (strlen($comms) > 0) {
	$items[] = new Heading($today->format('l, F j:'));
	$items [] = new Para($comms);
      }
    }
    if (count($items) > 0) {
      $this->page->addSection($p = new Port("Summary"));
      $p->addAttr("id", "summary");
      foreach ($items as $i)
	$p->addChild($i);
    }

    $link_schools = PUB_HOME.'/schools';

    // Divisional scores
    $maker = new ScoresDivisionalDialog($reg);
    $this->page->addSection($p = new Port("Score summary"));
    foreach ($maker->getTable('/inc', $link_schools) as $elem)
      $p->addChild($elem);
  }

  protected function fillDivision(Division $div) {
    if (isset($this->divPage[(string)$div])) return;

    $reg = $this->regatta;
    $page = new TPublicPage("Scores for division $div | " . $reg->get(Regatta::NAME));
    $this->divPage[(string)$div] = $page;
    $this->prepare($page);
    
    $link_schools = PUB_HOME.'/schools';
    $maker = new ScoresDivisionDialog($reg, $div);
    $page->addSection($p = new Port("Scores for Division $div"));
    foreach ($maker->getTable('/inc', $link_schools) as $elem) {
      $p->addChild($elem);
    }
  }

  protected function fillFull() {
    if ($this->fullPage !== null) return;
    
    $reg = $this->regatta;
    $this->fullPage = new TPublicPage("Full scores | " . $reg->get(Regatta::NAME));
    $this->prepare($this->fullPage);
    
    $link_schools = PUB_HOME.'/schools';
    
    // Total scores
    $maker = new ScoresFullDialog($reg);
    $this->fullPage->addSection($p = new Port("Race by race"));
    foreach ($maker->getTable('/inc', $link_schools) as $elem)
      $p->addChild($elem);
  }

  protected function fillRotation() {
    if ($this->rotPage !== null) return;

    $reg = $this->regatta;
    $this->rotPage = new TPublicPage(sprintf("%s Rotations", $reg->get(Regatta::NAME)));
    $this->prepare($this->rotPage);

    $maker = new RotationDialog($reg);
    foreach ($reg->getRotation()->getDivisions() as $div) {
      $this->rotPage->addSection($p = new Port("$div Division"));
      $p->addChild($maker->getTable($div));
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
    $div->addAttr("align", "center");
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