<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 */

/**
 * Creates the front page
 *
 */
class UpdateFront {
  private $page;

  private function fill() {
    if ($this->page !== null) return;
    
    $this->page = new TPublicFrontPage();

    // Get current season's coming regattas
    require_once('mysqli/DB.php');
    DBME::setConnection(Preferences::getConnection());

    $success = false;
    $seasons = DBME::getAll(DBME::$SEASON, new MyCond('start_date', date('Y-m-d'), MyCond::LE));
    foreach ($seasons as $season) {
      if (($success = $this->fillSeason($season))) {
	$this->page->addMenu(new Link("/$season", $season->fullString()));
	break;
      }
    }
    if (!$success) {
      // Wow! There is NO information to report!
      $this->page->addSection(new Port("Nothing to show!", array(new Para("We are sorry, but there are no regattas in the system! Please come back later. Happy sailing!"))));
    }
  }

  private function fillSeason(Dt_Season $season) {
    $cond = new MyCond('season', (string)$season);
    $regs = DBME::getAll(DBME::$REGATTA, new MyBoolean(array(new MyCond('status', 'coming'), $cond)));

    $current_season_is_active = false;
    $row = 0;
    if (count($regs) > 0) {
      $current_season_is_active = true;
      $this->page->addSection(new Port("Coming soon", array($tab = new Table()), array("id"=>"coming")));
      $tab->addHeader(new Row(array(Cell::th("Name"),
				    Cell::th("Host"),
				    Cell::th("Type"),
				    Cell::th("Conference"),
				    Cell::th("Start date")
				    )));
      foreach ($regs as $reg) {
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->id] = $host->nick_name;
	  $confs[$host->conference] = $host->conference;
	}
	$link = new Link(sprintf('/%s/%s', $reg->season, $reg->nick), $reg->name);
	$tab->addRow($r = new Row(array(new Cell($link, array("class"=>"left")),
					new Cell(implode("/", $hosts)),
					new Cell(ucfirst($reg->type)),
					new Cell(implode("/", $confs)),
					new Cell($reg->start_time->format('m/d/Y')))));
	$r->addAttr("class", sprintf("row%d", $row++ % 2));
      }
    }

    // get finished ones
    $regs = DBME::getAll(DBME::$REGATTA, new MyBoolean(array(new MyCond('status', 'coming', MyCond::NE), $cond)));
    if (count($regs) > 0) {
      $current_season_is_active = true;
      $this->page->addSection(new Port("Past regattas", array($tab = new Table()), array("id"=>"past")));
      $tab->addHeader(new Row(array(Cell::th("Name"),
				    Cell::th("Host"),
				    Cell::th("Type"),
				    Cell::th("Conference"),
				    Cell::th("Start date"),
				    Cell::th("Winner")
				    )));
      foreach ($regs as $reg) {
	$label = ($reg->status == 'final') ? 'Final' : 'Pending';
	$teams = $reg->getTeams();
	if (count($teams) > 0) {
	  $winner = $teams[0];
	  $path = realpath(sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $winner->school->id));
	  $status = $winner;
	  if ($path !== null)
	    $status = new Image(sprintf('/inc/img/schools/%s.png', $winner->school->id),
				array('alt'=>$winner->school, 'height'=>'40px'));
	
	  $hosts = array();
	  $confs = array();
	  foreach ($reg->getHosts() as $host) {
	    $hosts[$host->id] = $host->nick_name;
	    $confs[$host->conference] = $host->conference;
	  }
	  $link = new Link(sprintf('/%s/%s', $reg->season, $reg->nick), $reg->name);
	  $tab->addRow($r = new Row(array(new Cell($link, array("class"=>"left")),
					  new Cell(implode("/", $hosts)),
					  new Cell(ucfirst($reg->type)),
					  new Cell(implode("/", $confs)),
					  new Cell($reg->start_time->format('m/d/Y')),
					  new Cell($status, array('title'=>$label))
					  )));
	  $r->addAttr("class", sprintf("row%d", $row++ % 2));
	}
      }
    }
    return $current_season_is_active;
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
  public static function run() {
    $R = realpath(dirname(__FILE__).'/../../html');
    $M = new UpdateFront();
    if (file_put_contents("$R/index.html", $M->getPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the front page: %s\n", $filename), 8);
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  // SETUP PATHS and other CONSTANTS
  $_SERVER['HTTP_HOST'] = 'cli';
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  try {
    UpdateFront::run();
    error_log(sprintf("I:0:%s\t: Successful!\n", date('r')), 3, LOG_FRONT);
  }
  catch (Exception $e) {
    error_log(sprintf("E:%d:L%d:F%s:%s\t(%d): %s\n",
		      $e->getCode(),
		      $e->getLine(),
		      $e->getFile(),
		      date('r'),
		      $argv[1],
		      $e->getMessage()),
	      3, LOG_FRONT);
    print_r($e->getTrace());
  }
}
?>
