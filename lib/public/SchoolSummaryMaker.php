<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2011-01-03
 */

/**
 * Creates the school's summary page for the given school. Such a page
 * contains a port with information about the school's participation
 * and overall finish; a list of regattas currently particpating in;
 * list of regattas participated in the past; and a link (in the top
 * menu bar) to the school's profile at collegesailin.info.
 *
 * @author Dayan Paez
 * @version 2011-01-03
 */
class SchoolSummaryMaker {

  /**
   * @var String the sprintf-format for generating school's permanent
   * summary page
   */
  private $link_fmt = 'http://collegesailing.info/schools/%s';

  /**
   * @var School the school to write about
   */
  protected $school;

  /**
   * @var TPublicPage the public page in which to write content
   */
  protected $page;

  /**
   * Creates a new season page
   *
   * @param Season $season the season
   */
  public function __construct(School $school) {
    $this->school = $school;
  }

  private function fill() {
    if ($this->page !== null) return;

    $school = $this->school;
    $this->page = new TPublicPage($school);

    // SETUP navigation
    $this->page->addNavigation(new Link("..", "Schools", array("class"=>"nav")));
    $this->page->addMenu(new Link(sprintf($this->link_fmt, $school->id), "ICSA Info"));
    $this->page->addSection($d = new Div());
    $d->addChild(new GenericElement("h2", array(new XText($school))));
    $d->addChild($l = new Itemize());
    $l->addItems(new LItem($school->conference));
    $d->addAttr("align", "center");

    // SCHOOL season summary
    $season = new Season(new DateTime());
    $this->page->addSection($p = new Port(sprintf("Season summary for %s", $season->fullString()),
					  array(), array("id"=>"summary")));

    $regs = $season->getParticipation($school);
    $total = count($regs);
    $p->addChild(new Div(array(new Span(array(new Text("Number of Regattas:")),
					array("class"=>"prefix")),
			       new Text($total)),
			 array("class"=>"stat")));

    // get average placement
    $place = 0;
    $total = 0;
    foreach ($regs as $reg) {
      $reg = new Regatta($reg->id);
      if ($reg->get(Regatta::FINALIZED) !== null) {
	foreach ($reg->getPlaces($school) as $pl) {
	  $places += $pl;
	  $total ++;
	}
      }
    }
    $avg = ($total == 0) ? "Not applicable" : ($places / $total);
    $p->addChild(new Div(array(new Span(array(new Text("Average finish:")),
					array("class"=>"prefix")),
			       new Text($avg)),
			 array("class"=>"stat")));
    
  }

  /**
   * Generates and returns the HTML code for the season. Note that the
   * report is only generated once per report maker
   *
   * @return String
   */
  public function getPage() {
    $this->fill();
    return $this->page->toHTML();
  }
}
?>