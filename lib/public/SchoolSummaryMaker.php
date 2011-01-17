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
  private $link_fmt = 'http://collegesailing.info/blog/teams/%s';

  /**
   * @var School the school to write about
   */
  protected $school;

  /**
   * @var Season the season to summarize
   */
  protected $season;

  /**
   * @var TPublicPage the public page in which to write content
   */
  protected $page;

  /**
   * Creates a new season page
   *
   * @param Season $season the season
   */
  public function __construct(School $school, Season $season) {
    $this->school = $school;
    $this->season = $season;
  }

  private function getBlogLink() {
    return sprintf($this->link_fmt, str_replace(' ', '-', strtolower($this->school->name)));
  }

  private function fill() {
    if ($this->page !== null) return;

    $school = $this->school;
    $this->page = new TPublicPage($school);

    // SETUP navigation
    $this->page->addNavigation(new Link("..", "Schools", array("class"=>"nav")));
    $this->page->addMenu(new Link($this->getBlogLink(), "ICSA Info"));
    $this->page->addSection($d = new Div());
    $d->addChild(new GenericElement("h2", array(new Text($school))));
    $d->addChild($l = new Itemize());
    $l->addItems(new LItem($school->conference . ' Conference'));

    $burgee = sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $this->school->id);
    if (file_exists($burgee))
      $l->addItems(new LItem(new Image(sprintf('/inc/img/schools/%s.png', $this->school->id))));
    $d->addAttr("align", "center");
    $d->addAttr("id", "reg-details");

    // current season
    $now = new DateTime();
    $season = $this->season;
    $regs = $season->getParticipation($school);
    $total = count($regs);
    $current = array(); // regattas happening NOW
    $past = array();    // past regattas from the current season
    
    $skippers = array(); // associative array of sailor id => num times participating
    $skip_objs = array();
    $crews = array();
    $crew_objs = array();
    // get average placement
    $places = 0;
    $avg_total = 0;
    foreach ($regs as $reg) {
      $reg = new Regatta($reg->id);
      $num = count($reg->getTeams());
      if ($reg->get(Regatta::FINALIZED) !== null) {
	foreach ($reg->getPlaces($school) as $pl) {
	  $places += $pl;
	  $avg_total += $num;
	}
      }
      if ($reg->get(Regatta::START_TIME) <= $now &&
	  $reg->get(Regatta::END_DATE) >= $now) {
	$current[] = $reg;
      }
      if ($reg->get(Regatta::END_DATE) < $now) {
	$past[] = $reg;
      }

      $rpm = $reg->getRpManager();
      foreach ($reg->getTeams($school) as $team) {
	foreach ($reg->getDivisions() as $div) {
	  foreach ($rpm->getRP($team, $div, RP::SKIPPER) as $rp) {
	    if (!isset($skippers[$rp->sailor->id])) {
	      $skippers[$rp->sailor->id] = 0;
	      $skip_objs[$rp->sailor->id] = $rp->sailor;
	    }
	    $skippers[$rp->sailor->id] += count($rp->races_nums);
	  }
	  foreach ($rpm->getRP($team, $div, RP::CREW) as $rp) {
	    if (!isset($crews[$rp->sailor->id])) {
	      $crews[$rp->sailor->id] = 0;
	      $crew_objs[$rp->sailor->id] = $rp->sailor;
	    }
	    $crews[$rp->sailor->id] += 1;
	  }
	}
      }
    }
    $avg = ($avg_total == 0) ? "Not applicable" : ($places / $total);
    // ------------------------------------------------------------
    // SCHOOL sailing now
    if (count($current) > 0) {
      $this->page->addSection($p = new Port("Sailing now", array(), array("id"=>"sailing")));
      $p->addChild($tab = new Table());
      // $tab->addAttr("style", "width: 100%");
      $tab->addHeader(new Row(array(Cell::th("Name"),
				    Cell::th("Host"),
				    Cell::th("Type"),
				    Cell::th("Conference"),
				    Cell::th("Last race"),
				    Cell::th("Place(s)")
				    )));
      foreach ($current as $reg) {
	// borrowed from SeasonSummaryMaker
	$last_race = $reg->getLastScoredRace();
	$last_race = ($last_race === null) ? "--" : (string)$last_race;
	$status = "$last_race";
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->school->id] = $host->school->nick_name;
	  $confs[$host->school->conference->id] = $host->school->conference;
	}
	$places = $reg->getPlaces($school);
	$link = new Link(sprintf('/%s/%s', $season, $reg->get(Regatta::NICK_NAME)), $reg->get(Regatta::NAME));
	$tab->addRow($r = new Row(array(new Cell($link, array("class"=>"left")),
					new Cell(implode("/", $hosts)),
					new Cell(ucfirst($reg->get(Regatta::TYPE))),
					new Cell(implode("/", $confs)),
					new Cell($status),
					new Cell(sprintf('%s/%d', implode(',', $places), count($reg->getTeams())))
					)));
      }
    }

    // ------------------------------------------------------------
    // SCHOOL season summary
    $season_link = new Link('/'.(string)$season, $season->fullString());
    $this->page->addSection($p = new Port("Season summary for ", array($season_link),
					  array("id"=>"summary")));

    $p->addChild(new Div(array(new Span(array(new Text("Number of Regattas:")),
					array("class"=>"prefix")),
			       new Text($total)),
			 array("class"=>"stat")));

    $p->addChild(new Div(array(new Span(array(new Text("Average finish:")),
					array("class"=>"prefix")),
			       new Text(sprintf('%0.2f', $avg))),
			 array("class"=>"stat")));
    // most active sailor?
    arsort($skippers, SORT_NUMERIC);
    arsort($crews, SORT_NUMERIC);
    if (count($skippers) > 0) {
      $txt = array();
      $i = 0;
      foreach ($skippers as $id => $num) {
	if ($i++ >= 2)
	  break;
	$txt[] = sprintf('%s (%d races)', $skip_objs[$id], $num);
      }
      $p->addChild(new Div(array(new Span(array(new Text("Most active skipper:")),
					  array("class"=>"prefix")),
				 new Text(implode(", ", $txt))),
			   array("class"=>"stat")));
    }
    if (count($crews) > 0) {
      $txt = array();
      $i = 0;
      foreach ($crews as $id => $num) {
	if ($i++ >= 2)
	  break;
	$txt[] = sprintf('%s (%d)', $crew_objs[$id], $num);
      }
      $p->addChild(new Div(array(new Span(array(new Text("Most active crew:")),
					  array("class"=>"prefix")),
				 new Text(implode(", ", $txt))),
			   array("class"=>"stat")));
    }

    // ------------------------------------------------------------
    // SCHOOL past regattas
    if (count($past) > 0) {
      $this->page->addSection($p = new Port("Season history for ", array($season_link),
					    array("id"=>"history")));
      $p->addChild($tab = new Table());
      // $tab->addAttr("style", "width: 100%");
      $tab->addHeader(new Row(array(Cell::th("Name"),
				    Cell::th("Host"),
				    Cell::th("Type"),
				    Cell::th("Conference"),
				    Cell::th("Date"),
				    Cell::th("Status"),
				    Cell::th("Place(s)")
				    )));
      foreach ($past as $reg) {
	$date = $reg->get(Regatta::START_TIME);
	$status = ($reg->get(Regatta::FINALIZED) === null) ? "Pending" : "Official";
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->school->id] = $host->school->nick_name;
	  $confs[$host->school->conference->id] = $host->school->conference;
	}
	$places = $reg->getPlaces($school);
	$link = new Link(sprintf('/%s/%s', $season, $reg->get(Regatta::NICK_NAME)), $reg->get(Regatta::NAME));
	$tab->addRow($r = new Row(array(new Cell($link, array("class"=>"left")),
					new Cell(implode("/", $hosts)),
					new Cell(ucfirst($reg->get(Regatta::TYPE))),
					new Cell(implode("/", $confs)),
					new Cell($date->format('m/d/Y')),
					new Cell($status),
					new Cell(sprintf('%s/%d', implode(',', $places), count($reg->getTeams())))
					)));
      }
    }
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