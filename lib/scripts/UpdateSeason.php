<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
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

    require_once('xml5/TPublicPage.php');
    $types = Regatta::getTypes();
    $season = $this->season;
    $name = $season->fullString();
    $this->page = new TPublicPage($name);
    $this->page->head->add(new XMeta('description', sprintf("Summary of ICSA regattas for %s", $name)));

    // 2010-11-14: Separate regattas into "weekends", descending by
    // timestamp, based solely on the start_time, assuming that the
    // week ends on a Sunday.
    require_once('regatta/PublicDB.php');
    $weeks = array();
    $regattas = DB::getAll(DB::$DT_REGATTA, new DBCond('season', (string)$season));
    foreach ($regattas as $reg) {
      $week = $reg->start_time->format('W');
      if (!isset($weeks[$week]))
	$weeks[$week] = array();
      $weeks[$week][] = $reg;
    }

    // SETUP navigation
    $this->page->addNavigation(new XA('.', $season->fullString(), array('class'=>'nav')));
    $this->page->addMenu(new XA('#summary', "Summary"));
    $this->page->addMenu(new XA('#all', "Weekends"));

    // SEASON summary
    $this->page->addSection($summary_port = new XPort("Season summary"));
    $summary_port->set('id', 'summary');
    $num_teams = 0;

    // COMING soon
    $coming_regattas = array();

    // WEEKENDS
    $count = count($weeks);
    if ($count == 0) {
      // Should this section even exist?
      $this->page->addSection(new XP(array(), "There are no regattas to report on yet."));
    }
    // stats
    $total = 0;
    $winning_school  = array();
    $now = date('U');
    $ports = array();
    foreach ($weeks as $week => $list) {
      $title = "Week $count";
      $week_total = 0;
      $p = new XPort($title);
      $count--;
      $p->add(new XTable(array(),
			 array(new XTHead(array(),
					  array(new XTR(array(),
							array(new XTH(array(), "Name"),
							      new XTH(array(), "Host"),
							      new XTH(array(), "Type"),
							      new XTH(array(), "Conference"),
							      new XTH(array(), "Start date"),
							      new XTH(array(), "Status"),
							      new XTH(array(), "Leading"))))),
			       $tab = new XTBody())));
      $row = 0;
      foreach ($list as $reg) {
	if ($reg->status == 'coming')
	  $coming_regattas[] = $reg;
	else {
	  $teams = $reg->getTeams();
	  if (count($teams) == 0)
	    continue;
	  
	  $week_total++;
	  $total++;
	  $status = null;
	  $wt = $teams[0];

	  switch ($reg->status) {
	  case 'finished':
	    $status = "Pending";
	    break;

	  case 'final':
	    $status = new XStrong("Final");
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
	    $confs[$host->conference->id] = $host->conference;
	  }

	  $link = new XA($reg->nick, $reg->name);
	  $path = realpath(sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $wt->school->id));
	  $burg = ($path !== false) ?
	    new XImg(sprintf('/inc/img/schools/%s.png', $wt->school->id), $wt->school, array('height'=>40)) :
	    $wt->school->nick_name;
	  $tab->add(new XTR(array('class' => sprintf("row%d", $row++ % 2)),
			    array(new XTD(array(), $link),
				  new XTD(array(), implode("/", $hosts)),
				  new XTD(array(), $types[$reg->type]),
				  new XTD(array(), implode("/", $confs)),
				  new XTD(array(), $reg->start_time->format('m/d/Y')),
				  new XTD(array(), $status),
				  new XTD(array('title' => $wt), $burg))));
	}
      }
      if ($week_total > 0)
	$ports[] = $p;
    }

    // WRITE coming soon, and weekend summary ports
    if (count($coming_regattas) > 0) {
      $this->page->addSection($p = new XPort("Coming soon"));
      $p->add($tab = new XQuickTable(array(),
				     array("Name",
					   "Host",
					   "Type",
					   "Conference",
					   "Start time")));
      foreach ($coming_regattas as $reg) {
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->id] = $host->nick_name;
	  $confs[$host->conference->id] = $host->conference;
	}
	$tab->addRow(array(new XA(sprintf('/%s/%s', $season, $reg->nick), $reg->name),
			   implode("/", $hosts),
			   $types[$reg->type],
			   implode("/", $confs),
			   $reg->start_time->format('m/d/Y @ H:i')));
      }
    }
    foreach ($ports as $p)
      $this->page->addSection($p);

    // Complete SUMMARY
    $summary_port->add(new XDiv(array('class'=>'stat'),
				array(new XSpan("Number of Regattas:", array('class'=>'prefix')), $total)));
    $summary_port->add(new XDiv(array('class'=>'stat'),
				array(new XSpan("Number of Teams:", array('class'=>'prefix')), $num_teams)));
    
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
	$tied_schools[] = DB::get(DB::$SCHOOL, array_shift($school_codes));
	while (count($school_codes) > 0) {
	  $next_num = array_shift($winning_school);
	  if ($next_num != $tied_number) break;
	  $tied_schools[] = DB::get(DB::$SCHOOL, array_shift($school_codes));
	}
      }
      // 2011-04-09: feedback compiled by Matt Lindblad from users
      // that this stat was "confusing"
      /*
	$summary_port->add(new XDiv(array('class'=>'stat'),
	array(new XSpan("Winningest School(s):", array('class'=>'prefix')),
	implode('/',
	$tied_schools))));
      */
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
    return $this->page->toXML();
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

    // create folder, if necessary
    if (!file_exists($dirname) && mkdir($dirname) === false)
      throw new RuntimeException(sprintf("Unable to make the season folder: %s\n", $dirname), 2);

    $M = new UpdateSeason($season);
    if (file_put_contents("$dirname/index.html", $M->getPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the season summary: %s\n", $dirname), 8);
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
