<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 */

/**
 * Creates the season summary page for the given season. Such a page
 * contains a port with information about current regattas being
 * sailed and past regattas as well.
 *
 */
class SeasonSummaryMaker {
  private $season;
  private $page;

  /**
   * Creates a new season page
   *
   * @param Season $season the season
   */
  public function __construct(Season $season) {
    $this->season = $season;
  }

  private function fill() {
    if ($this->page !== null) return;

    $season = $this->season;
    $this->page = new TPublicPage($season->fullString() . ' ' . $season->getYear());

    // Separate the regattas into three sections: sailing now, sailing
    // soon, and finalized
    $regattas = $season->getRegattas();
    $sailing = array();
    $coming  = array();
    $final   = array();
    foreach ($regattas as $reg) {
      if ($reg->finalized !== null)
	$final[] = $reg;
      elseif ($reg->start_time->format('U') < date('U'))
	$sailing[] = $reg;
      else
	$coming[] = $reg;
    }

    // SETUP navigation
    $this->page->addNavigation(new Link(".", $season->fullString(), array("class"=>"nav")));
    if (count($sailing) > 0)
      $this->page->addMenu(new Link("#now",  "Sailing now"));
    if (count($coming)  > 0)
      $this->page->addMenu(new Link("#soon", "Sailing soon"));
    if (count($final)   > 0)
      $this->page->addMenu(new Link("#all", "Finalized"));

    // SEASON summary
    $this->page->addSection($summary_port = new Port("Season summary"));
    $num_teams    = 0;

    // Sailing NOW
    if (count($sailing) > 0) {
      $this->page->addSection($p = new Port("Sailing now"));
      $p->addAttr("id", "now");
      $p->addChild($tab = new Table());
      $tab->addAttr("style", "width: 100%");
      $tab->addHeader(new Row(array(Cell::th("Name"),
				    Cell::th("Host"),
				    Cell::th("Type"),
				    Cell::th("Conference"),
				    Cell::th("Latest race")
				    )));
      $row = 0;
      foreach ($sailing as $reg) {
	$reg = new Regatta($reg->id);
	$num_teams += count($reg->getTeams());
	$last_race = $reg->getLastScoredRace();
	$last_race = ($last_race === null) ? "--" : (string)$last_race;
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->school->id] = $host->school->nick_name;
	  $confs[$host->school->conference->id] = $host->school->conference;
	}
	$link = new Link($reg->get(Regatta::NICK_NAME), $reg->get(Regatta::NAME));
	$tab->addRow($r = new Row(array(new Cell($link, array("class"=>"left")),
					new Cell(implode("/", $hosts)),
					new Cell(ucfirst($reg->get(Regatta::TYPE))),
					new Cell(implode("/", $confs)),
					new Cell($last_race)
					)));
	$r->addAttr("class", sprintf("row%d", $row++ % 2));
      }
    }

    // Sailing SOON
    if (count($coming) > 0) {
      $this->page->addSection($p = new Port("Sailing soon"));
      $p->addAttr("id", "soon");
      $p->addChild($tab = new Table());
      $tab->addAttr("style", "width: 100%");
      $tab->addHeader(new Row(array(Cell::th("Name"),
				    Cell::th("Host"),
				    Cell::th("Type"),
				    Cell::th("Conference"),
				    Cell::th("On the water")
				    )));
      $row = 0;
      foreach ($coming as $reg) {
	$reg = new Regatta($reg->id);
	$num_teams += count($reg->getTeams());
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->school->id] = $host->school->nick_name;
	  $confs[$host->school->conference->id] = $host->school->conference;
	}
	$link = new Link($reg->get(Regatta::NICK_NAME), $reg->get(Regatta::NAME));
	$tab->addRow($r = new Row(array(new Cell($link, array("class"=>"left")),
					new Cell(implode("/", $hosts)),
					new Cell(ucfirst($reg->get(Regatta::TYPE))),
					new Cell(implode("/", $confs)),
					new Cell($reg->get(Regatta::START_TIME)->format('Y-m-d H:i'))
					)));
	$r->addAttr("class", sprintf("row%d", $row++ % 2));
      }
    }

    // ALL sailing
    if (count($final) > 0) {
      $winning_school = array();

      $this->page->addSection($p = new Port("Finalized regattas"));
      $p->addAttr("id", "all");
      $p->addChild($tab = new Table());
      $tab->addAttr("style", "width: 100%");
      $tab->addHeader(new Row(array(Cell::th("Name"),
				    Cell::th("Host"),
				    Cell::th("Type"),
				    Cell::th("Winner"),
				    Cell::th("Finalized")
				    )));
      $row = 0;
      foreach ($final as $reg) {
	$reg = new Regatta($reg->id);
	$num_teams += count($reg->getTeams());
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->school->id] = $host->school->nick_name;
	  $confs[$host->school->conference->id] = $host->school->conference;
	}

	$link = new Link($reg->get(Regatta::NICK_NAME), $reg->get(Regatta::NAME));
	$tab->addRow($r = new Row(array(new Cell($link, array("class"=>"left")),
					new Cell(implode("/", $hosts)),
					new Cell(ucfirst($reg->get(Regatta::TYPE))),
					new Cell($wt = $reg->getWinningTeam()),
					new Cell($reg->get(Regatta::FINALIZED)->format('F j, Y'))
					)));
	$r->addAttr("class", sprintf("row%d", $row++ % 2));
	if (!isset($winning_school[$wt->school->id]))
	  $winning_school[$wt->school->id] = 0;
	$winning_school[$wt->school->id] += 1;
      }
    }

    // Complete SUMMARY
    $summary_port->addChild(new Div(array(new Span(array(new Text("Number of Regattas:")),
						   array("class"=>"prefix")),
					  new Text(count($sailing) + count($coming) + count($final))),
				    array("class"=>"stat")));
    $summary_port->addChild(new Div(array(new Span(array(new Text("Number of Teams:")),
						   array("class"=>"prefix")),
					  new Text($num_teams)),
				    array("class"=>"stat")));
    // Sort the winning school to determine winningest
    if (isset($winning_school)) {
      asort($winning_school, SORT_NUMERIC);
      $school = Preferences::getSchool(array_shift(array_keys($winning_school)));
      $summary_port->addChild(new Div(array(new Span(array(new Text("Winningest School:")),
						     array("class"=>"prefix")),
					    new Text($school)),
				      array("class"=>"stat")));
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

if (isset($argv[0]) && $argv[0] == basename(__FILE__)) {
  ini_set('include_path', '.:/home/dayan/ts/techscore-web/lib');
  require_once('conf.php');
  $maker = new SeasonSummaryMaker(new Season(new DateTime()));
  file_put_contents("/tmp/season.html", $maker->getPage());
}
?>