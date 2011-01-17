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
  private $regatta;
  private $page;
  private $rotPage;
  private $hasRotation;

  /**
   * Creates a new report for the given regatta
   *
   */
  public function __construct(Regatta $reg) {
    $this->regatta = $reg;
    $rot = $this->regatta->getRotation();
    $this->hasRotation = count($rot->getRaces()) > 0;
  }

  private function fill() {
    if ($this->page !== null) return;

    $reg = $this->regatta;
    $this->page = new TPublicPage($reg->get(Regatta::NAME));
    $this->prepare($this->page);

    // ADD JS
    $this->page->addHead(new GenericElement("script", array(new Text()),
					    array("type"=>"text/javascript",
						  "src" =>"/inc/js/report.js")));
    $this->page->body->addAttr("onload", "collapse()");

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

    // Total scores
    $maker = new ScoresFullDialog($reg);
    $this->page->addSection($p = new Port("Race by race"));
    foreach ($maker->getTable('/inc', $link_schools) as $elem)
      $p->addChild($elem);

    // Individual division scores (do not include if singlehanded as
    // this is redundant)
    if (!$reg->isSingleHanded()) {
      foreach ($reg->getDivisions() as $div) {
	$maker = new ScoresDivisionDialog($reg, $div);
	$this->page->addSection($p = new Port("Scores for $div"));
	foreach ($maker->getTable('/inc', $link_schools) as $elem) {
	  $p->addChild($elem);
	}
      }
    }
  }

  private function fillRotation() {
    if ($this->rotPage !== null) return;
    if (!$this->hasRotation)
      throw new InvalidArgumentException("There is no rotation!");

    $reg = $this->regatta;
    $this->rotPage = new TPublicPage(sprintf("%s Rotations", $reg->get(Regatta::NAME)));
    $this->prepare($this->rotPage);

    $maker = new RotationDialog($reg);
    foreach ($reg->getRotation()->getDivisions() as $div) {
      $this->rotPage->addSection($p = new Port("$div Division"));
      $p->addChild($maker->getTable($div));
    }
  }

  private function prepare(TPublicPage $page) {
    $reg = $this->regatta;
    $page->addNavigation(new Link(".", $reg->get(Regatta::NAME), array("class"=>"nav")));
    
    // Links to season
    $season = $reg->get(Regatta::SEASON);
    $page->addNavigation(new Link("..", $season->fullString(),
				  array("class"=>"nav", "accesskey"=>"u")));

    // Menu
    if ($this->hasRotation)
      $page->addMenu(new Link("rotations", "Rotations"));
    $page->addMenu(new Link(".", "Report"));

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
   * @return String
   */
  public function getScoresPage() {
    $this->fill();
    return $this->page->toHTML();
  }

  /**
   * Generates the rotation page, if applicable
   *
   * @return String
   * @throws InvalidArgumentException should there be no rotation available
   */
  public function getRotationPage() {
    $this->fillRotation();
    return $this->rotPage->toHTML();
  }

  /**
   * Returns whether or not a rotation page is available
   *
   * @return boolean
   */
  public function hasRotation() {
    return $this->hasRotation;
  }
}
?>