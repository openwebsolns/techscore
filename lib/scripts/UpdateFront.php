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

    // Get current season's coming regattas
    require_once('regatta/PublicDB.php');
    DBME::setConnection(DB::connection());

    $success = false;
    $seasons = DB::getAll(DB::$SEASON, new DBCond('start_date', new DateTime(), DBCond::LE));
    foreach ($seasons as $season) {
      if (($success = $this->fillSeason($season))) {
	$this->page->addMenu(new XA("/$season", $season->fullString()));
	break;
      }
    }
    if (!$success) {
      // Wow! There is NO information to report!
      $this->page->addSection(new XPort("Nothing to show!", array(new XP(array(), "We are sorry, but there are no regattas in the system! Please come back later. Happy sailing!"))));
    }
  }

  private function fillSeason(Season $season) {
    $types = Regatta::getTypes();

    $cond = new DBCond('season', (string)$season);
    $regs = DBME::getAll(DBME::$REGATTA, new DBBool(array(new DBCond('status', 'coming'), $cond)));

    $current_season_is_active = false;
    $row = 0;
    if (count($regs) > 0) {
      $current_season_is_active = true;
      $this->page->addSection(new XPort("Coming soon", array($tab = new XTable()), array('id'=>'coming')));
      $tab->add(new XTHead(array(),
			   array(new XTR(array(),
					 array(new XTH(array(), "Name"),
					       new XTH(array(), "Host"),
					       new XTH(array(), "Type"),
					       new XTH(array(), "Conference"),
					       new XTH(array(), "Start time"))))));
      $tab->add($bod = new XTBody());
      foreach ($regs as $reg) {
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->id] = $host->nick_name;
	  $confs[$host->conference->id] = $host->conference;
	}
	$link = new XA(sprintf('/%s/%s', $reg->season, $reg->nick), $reg->name);
	$bod->add(new XTR(array('class' => sprintf("row%d", $row++ % 2)),
			  array(new XTD(array('class'=>'left'), $link),
				new XTD(array(), implode("/", $hosts)),
				new XTD(array(), $types[$reg->type]),
				new XTD(array(), implode("/", $confs)),
				new XTD(array(), $reg->start_time->format('m/d/Y @ H:i')))));
      }
    }

    // get finished ones
    $regs = DBME::getAll(DBME::$REGATTA, new DBBool(array(new DBCond('status', 'coming', DBCond::NE), $cond)));
    if (count($regs) > 0) {
      $current_season_is_active = true;
      $this->page->addSection(new XPort("All regattas", array($tab = new XTable()), array('id'=>'past')));
      $tab->add(new XTHead(array(),
			   array(new XTR(array(),
					 array(new XTH(array(), "Name"),
					       new XTH(array(), "Host"),
					       new XTH(array(), "Type"),
					       new XTH(array(), "Conference"),
					       new XTH(array(), "Start date"),
					       new XTH(array(), "Status"),
					       new XTH(array(), "Leading"))))));
      $tab->add($bod = new XTBody());
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
	  $bod->add(new XTR(array('class' => sprintf("row%d", $row++ % 2)),
			    array(new XTD(array('class'=>'left'), $link),
				  new XTD(array(), implode("/", $hosts)),
				  new XTD(array(), $types[$reg->type]),
				  new XTD(array(), implode("/", $confs)),
				  new XTD(array(), $reg->start_time->format('m/d/Y')),
				  new XTD(array(), $label),
				  new XTD(array('title'=>$winner), $status))));
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
