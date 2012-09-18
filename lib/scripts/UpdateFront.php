<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

/**
 * Creates the front page
 *
 */
class UpdateFront {
  private $page;

  private function fill() {
    if ($this->page !== null) return;

    require_once('xml5/TPublicFrontPage.php');
    $this->page = new TPublicFrontPage();

    // Menu
    $this->page->addMenu(new XA('/', "Home"));
    $this->page->addMenu(new XA('/schools/', "Schools"));
    $this->page->addMenu(new XA('/seasons/', "Seasons"));

    // Get current season's coming regattas
    require_once('regatta/PublicDB.php');

    $success = false;
    $seasons = Season::getActive();
    if (count($seasons) == 0) {
      $this->page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));

      // Wow! There is NO information to report!
      $this->page->addSection(new XPort("Nothing to show!", array(new XP(array(), "We are sorry, but there are no regattas in the system! Please come back later. Happy sailing!"))));
      return;
    }

    $this->page->addMenu(new XA('/'.$seasons[0]->id.'/', $seasons[0]->fullString()));
    $this->page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));
    $this->fillSeason($seasons[0]);

    // ------------------------------------------------------------
    // Add links to all seasons
    $num = 0;
    $ul = new XUl(array('id'=>'other-seasons'));
    $seasons = Season::getActive();
    foreach ($seasons as $s) {
      if (count($s->getRegattas()) > 0) {
	$num++;
	$ul->add(new XLi(new XA('/'.$s.'/', $s->fullString())));
      }
    }
    if ($num > 0)
      $this->page->addSection(new XDiv(array('id'=>'submenu-wrapper'),
				       array(new XH3("Other seasons", array('class'=>'nav')), $ul)));

  }

  /**
   * Fills the page with information for the given season, assuming
   * that such a season has anything to report
   *
   */
  private function fillSeason(Season $season) {
    $types = Regatta::getTypes();

    $cond = new DBCond('season', (string)$season);
    $regs = DB::getAll(DB::$DT_REGATTA, new DBBool(array(new DBCond('status', 'coming'), $cond)));

    $row = 0;
    if (count($regs) > 0) {
      $this->page->addSection(new XPort("Coming soon",
					array($tab = new XQuickTable(array('class'=>'season-summary'),
								     array("Name",
									   "Host",
									   "Type",
									   "Conference",
									   "Start time")))));
      foreach ($regs as $reg) {
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->id] = $host->nick_name;
	  $confs[$host->conference->id] = $host->conference;
	}
	$link = new XA(sprintf('/%s/%s', $reg->season, $reg->nick), $reg->name);
	$tab->addRow(array($link,
			   implode("/", $hosts),
			   $types[$reg->type],
			   implode("/", $confs),
			   $reg->start_time->format('m/d/Y @ H:i')),
		     array('class' => sprintf("row%d", $row++ % 2)));
      }
    }

    // get finished ones
    $regs = DB::getAll(DB::$DT_REGATTA, new DBBool(array(new DBCond('status', 'coming', DBCond::NE), $cond)));
    if (count($regs) > 0) {
      $this->page->addSection(new XPort("Regattas for " . $season->fullString(),
					array($tab = new XQuickTable(array('class'=>'season-summary'),
								     array("Name",
									   "Host",
									   "Type",
									   "Conference",
									   "Start date",
									   "Status",
									   "Leading"))),
					array('id'=>'past')));
      foreach ($regs as $reg) {
	$label = null;
	switch ($reg->status) {
	case 'final':
	  $label = new XStrong("Final"); break;
	case 'finished':
	  $label = 'Pending'; break;
	default:
	  $label = $reg->status;
	}

	$teams = $reg->getTeams();
	if (count($teams) > 0) {
	  $winner = $teams[0];
	  $path = realpath(sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $winner->school->id));
	  $status = $winner;
	  if ($path !== false)
	    $status = new XImg(sprintf('/inc/img/schools/%s.png', $winner->school->id), $winner->school,
			       array('height'=>40));
	
	  $hosts = array();
	  $confs = array();
	  foreach ($reg->getHosts() as $host) {
	    $hosts[$host->id] = $host->nick_name;
	    $confs[$host->conference->id] = $host->conference;
	  }
	  $link = new XA(sprintf('/%s/%s', $reg->season, $reg->nick), $reg->name);
	  $tab->addRow(array($link,
			     implode("/", $hosts),
			     $types[$reg->type],
			     implode("/", $confs),
			     $reg->start_time->format('m/d/Y'),
			     $label,
			     new XTD(array('title'=>$winner), $status)),
		       array('class' => sprintf("row%d", $row++ % 2)));
	}
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
    return $this->page->toXML();
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
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  try {
    UpdateFront::run();
    error_log(sprintf("I:0:%s\t: Successful!\n", date('r')), 3, Conf::$LOG_FRONT);
  }
  catch (Exception $e) {
    error_log(sprintf("E:%d:L%d:F%s:%s: %s\n",
		      $e->getCode(),
		      $e->getLine(),
		      $e->getFile(),
		      date('r'),
		      $e->getMessage()),
	      3, Conf::$LOG_FRONT);
    print_r($e->getTrace());
  }
}
?>
