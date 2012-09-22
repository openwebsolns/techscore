<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

/**
 * Creates the front page: Includes a brief welcoming message,
 * displayed alongside the list of regattas sailing now, if any. (The
 * welcome message takes up the entire width otherwise).
 *
 * Underneath that, includes a list of upcoming events, in ascending
 * chronological order.
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

    $types = Regatta::getTypes();
    $this->page->addMenu(new XA('/'.$seasons[0]->id.'/', $seasons[0]->fullString()));
    $this->page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));

    // Add welcome message
    $this->page->addSection($div = new XDiv(array('id'=>'welcome'),
					    array($h1 = new XH1(""),
						  new XP(array(),
							 array("This is the home for real-time results of College Sailing regattas. This site includes scores and participation records for all fleet-racing events within ICSA. An archive of ",
							       new XA('/seasons/', "all previous seasons"),
							       " is also available.")),
						  new XP(array(),
							 array("To follow a specific school, use our ",
							       new XA('/schools/', "listing of schools"),
							       " organized by ICSA Conference. Each school's participation is summarized by season.")),
						  new XP(array(),
							 array("For more information about college sailing, ICSA, the teams, and our sponsors, please visit the ",
							       new XA(Conf::$ICSA_HOME, "ICSA site."))))));
    $h1->add(new XSpan("", array('id'=>'left-fill')));
    $h1->add(new XSpan("Welcome"));
    $h1->add(new XSpan("", array('id'=>'right-fill')));

    // ------------------------------------------------------------
    // Are there any regattas in progress? Such regattas must exist in
    // Dt_Regatta, be happening now according to date, and have a
    // status not equal to 'SCHEDULED' (which usually indicates that a
    // regatta is not yet ready, and might possibly never be scored).
    $now = new DateTime('tomorrow');
    $now->setTime(0, 0, 0);
    $in_prog = DB::getAll(DB::$DT_REGATTA, new DBBool(array(new DBCond('start_time', $now, DBCond::LE),
							    new DBCond('end_date', $now, DBCond::GE),
							    new DBCond('status', Dt_Regatta::STAT_SCHEDULED, DBCond::NE))));
    if (count($in_prog) > 0) {
      $div->set('class', 'float');
      $this->page->addSection(new XDiv(array('id'=>'in-progress'),
				       array(new XH3("In progress"),
					     $tab = new XQuickTable(array('class'=>'season-summary'),
								    array("Name",
									  "Type",
									  "Status",
									  "Leading")))));
      foreach ($in_prog as $i => $reg) {
	$stat = new XStrong($reg->status);
	if ($reg->status == Dt_Regatta::STAT_READY) {
	  $stat = new XEm("No score yet");
	  $lead = new XEm("—");
	}
	else {
	  $tms = $reg->getTeams();
	  if ($tms[0]->school->burgee !== null)
	    $lead = new XImg(sprintf('/inc/img/schools/%s.png', $tms[0]->school->id), $tms[0], array('height'=>40));
	  else
	    $lead = (string)$tms[0];
	}
	$tab->addRow(array(new XA(sprintf('/%s/%s/', $reg->season->id, $reg->nick), $reg->name),
			   $types[$reg->type], $stat, $lead), array('class'=>'row'.($i % 2)));
      }
    }

    // ------------------------------------------------------------
    // Fill list of coming soon regattas
    DB::$DT_REGATTA->db_set_order(array('start_time'=>true));
    $regs = DB::getAll(DB::$DT_REGATTA, new DBCond('start_time', $now, DBCond::GE));
    DB::$DT_REGATTA->db_set_order();
    if (count($regs) > 0) {
      $this->page->addSection($p = new XPort("Upcoming schedule"));
      $p->add($tab = new XQuickTable(array('class'=>'coming-regattas'),
				     array("Name",
					   "Host",
					   "Type",
					   "Start time")));
      foreach ($regs as $reg) {
	$hosts = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->id] = $host->nick_name;
	}
	$tab->addRow(array(new XA(sprintf('/%s/%s', $reg->season->id, $reg->nick), $reg->name),
			   implode("/", $hosts),
			   $types[$reg->type],
			   $reg->start_time->format('m/d/Y @ H:i')));
      }
    }
    
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
