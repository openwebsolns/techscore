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
 * sailed and past regattas as well. For speed sake, this function
 * uses the dt_* tables, which contain summarized versions of only the
 * public regattas and utilizies the new MySQL-DP-0.5 libraries.
 *
 */
class UpdateSeason {
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
    $this->page = new TPublicPage(ucfirst($season->getSeason()) . ' ' . $season->getYear());
    $this->page->head->addChild(new GenericElement('script',
						   array(new Text("")),
						   array('type'=>'text/javascript',
							 'src'=>'/inc/js/refresh.js')));

    // 2010-11-14: Separate regattas into "weekends", descending by
    // timestamp, based solely on the start_time, assuming that the
    // week ends on a Sunday.
    require_once('mysqli/DB.php');
    DBME::setConnection(Preferences::getConnection());
    $weeks = array();
    $regattas = DBME::getAll(DBME::$REGATTA, new MyCond('season', (string)$season));
    foreach ($regattas as $reg) {
      $week = $reg->start_time->format('W');
      if (!isset($weeks[$week]))
	$weeks[$week] = array();
      $weeks[$week][] = $reg;
    }

    // SETUP navigation
    $this->page->addNavigation(new Link(".", $season->fullString(), array("class"=>"nav")));
    $this->page->addMenu(new Link("#summary", "Summary"));
    $this->page->addMenu(new Link("#all", "Weekends"));

    // SEASON summary
    $this->page->addSection($summary_port = new Port("Season summary", array(), array("id"=>"summary")));
    $num_teams    = 0;

    // WEEKENDS
    $count = count($weeks);
    if ($count == 0) {
      // Should this section even exist?
      $this->page->addSection(new Para("There are no regattas to report on yet."));
    }
    // stats
    $total = 0;
    $winning_school  = array();
    $now = date('U');
    foreach ($weeks as $week => $list) {
      $title = "Week $count";
      $this->page->addSection($p = new Port($title));
      $count--;
      $p->addChild($tab = new Table());
      // $tab->addAttr("style", "width: 100%");
      $tab->addHeader(new Row(array(Cell::th("Name"),
				    Cell::th("Host"),
				    Cell::th("Type"),
				    Cell::th("Conference"),
				    Cell::th("Start date"),
				    Cell::th("Status")
				    )));
      $row = 0;
      foreach ($list as $reg) {
	$total++;
	$status = null;
	$teams = $reg->getTeams();
	switch ($reg->status) {
	case 'coming':
	  $status = "Coming soon";
	  break;

	case 'finished':
	  $status = "Pending";
	  break;

	case 'final':
	  $wt = $teams[0];
	  $status = "Winner: " . $wt;
	  if (!isset($winning_school[$wt->school->id]))
	    $winning_school[$wt->school->id] = 0;
	  $winning_school[$wt->school->id] += 1;
	  break;

	default:
	  $status = "In progress: " . $reg->status;
	}
	
	$num_teams += count($teams);
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->id] = $host->nick_name;
	  $confs[$host->conference] = $host->conference;
	}
	$link = new Link($reg->nick, $reg->name);
	$tab->addRow($r = new Row(array(new Cell($link, array("class"=>"left")),
					new Cell(implode("/", $hosts)),
					new Cell(ucfirst($reg->type)),
					new Cell(implode("/", $confs)),
					new Cell($reg->start_time->format('m/d/Y')),
					new Cell($status)
					)));
	$r->addAttr("class", sprintf("row%d", $row++ % 2));
      }
    }

    // Complete SUMMARY
    $summary_port->addChild(new Div(array(new Span(array(new Text("Number of Regattas:")),
						   array("class"=>"prefix")),
					  new Text($total)),
				    array("class"=>"stat")));
    $summary_port->addChild(new Div(array(new Span(array(new Text("Number of Teams:")),
						   array("class"=>"prefix")),
					  new Text($num_teams)),
				    array("class"=>"stat")));
    // Sort the winning school to determine winningest, and only print
    // this stat if there is a something to have won. Also, print all
    // the tied teams for winningest spot.
    arsort($winning_school, SORT_NUMERIC);
    if (count($winning_school) > 0) {
      $school_codes = array_keys($winning_school);
      if ($winning_school[$school_codes[0]] != 0) {
	// tied teams
	$tied_number = array_shift($winning_school);
	$tied_schools = array();
	$tied_schools[] = DBME::get(DBME::$SCHOOL, array_shift($school_codes));
	while (count($school_codes) > 0) {
	  $next_num = array_shift($winning_school);
	  if ($next_num != $tied_number) break;
	  $tied_schools[] = DBME::get(DBME::$SCHOOL, array_shift($school_codes));
	}
      }
      $summary_port->addChild(new Div(array(new Span(array(new Text("Winningest School(s):")),
						     array("class"=>"prefix")),
					    new Text(implode(', ', $tied_schools))),
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

  // ------------------------------------------------------------
  // Static component used to write the summary page to file
  // ------------------------------------------------------------
  
  /**
   * Creates the new page summary in the public domain
   *
   */
  public static function run(Season $season) {
    $R = realpath(dirname(__FILE__).'/../../html');

    // Do season
    $dirname = "$R/$season";
    $M = new UpdateSeason($season);
    if (file_put_contents("$dirname/index.html", $M->getPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the season summary: %s\n", $filename), 8);
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  // Arguments
  if (count($argv) != 2) {
    printf("usage: %s <season>\n", $_SERVER['PHP_SELF']);
    exit(1);
  }

  // SETUP PATHS and other CONSTANTS
  $_SERVER['HTTP_HOST'] = $argv[0];
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  // GET Season
  $season = Season::parse($argv[1]);
  if ($season == null) {
    printf("Invalid season given: %s\n\n", $argv[1]);
    printf("usage: %s <season>\n", $_SERVER['PHP_SELF']);
    exit(1);
  }

  try {
    UpdateSeason::run($season);
    error_log(sprintf("I:0:%s\t(%s): Successful!\n", date('r'), $season), 3, LOG_SEASON);
  }
  /*
    catch (InvalidArgumentException $e) {
    printf("Invalid regatta ID provided: %s\n", $argv[1]);
    exit(2);
    }
  */
  catch (Exception $e) {
    error_log(sprintf("E:%d:L%d:F%s:%s\t(%d): %s\n",
		      $e->getCode(),
		      $e->getLine(),
		      $e->getFile(),
		      date('r'),
		      $argv[1],
		      $e->getMessage()),
	      3, LOG_SEASON);
    print_r($e->getTrace());
  }
}
?>
