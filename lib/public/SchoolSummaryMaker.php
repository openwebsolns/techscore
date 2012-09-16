<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2011-01-03
 * @package scripts
 */

require_once('xml5/TPublicPage.php');

/**
 * Creates the school's summary page for the given school. Such a page
 * contains a port with information about the school's participation
 * and overall finish; a list of regattas currently particpating in;
 * list of regattas participated in the past; and a link (in the top
 * menu bar) to the school's profile at the ICSA site.
 *
 * @author Dayan Paez
 * @version 2011-01-03
 */
class SchoolSummaryMaker {

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
    return null;
    // Turned off until we can figure out a consistent way of doing
    $link_fmt = 'http://collegesailing.info/blog/teams/%s';
    return sprintf($link_fmt, str_replace(' ', '-', strtolower($this->school->name)));
  }

  private function fill() {
    if ($this->page !== null) return;

    require_once('regatta/PublicDB.php');

    $types = Regatta::getTypes();
    $school = $this->school;
    $season = $this->season;
    $this->page = new TPublicPage($school);

    // SETUP navigation
    $this->page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));
    $this->page->addMenu(new XA('/schools/', "Schools"));
    $this->page->addMenu(new XA('/seasons/', "Seasons"));
    $this->page->addMenu(new XA(sprintf("/schools/%s/", $school->id), $school->nick_name));
    if (($link = $this->getBlogLink()) !== null)
      $this->page->addMenu(new XA($link, "ICSA Info"));
    $this->page->addMenu(new XA(Conf::$ICSA_HOME . '/teams/', "ICSA Teams"));

    $burgee = sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $this->school->id);
    if (file_exists($burgee))
      $this->page->addSection(new XP(array('class'=>'burgee'), new XImg(sprintf('/inc/img/schools/%s.png', $this->school->id), $this->school->id)));

    // current season
    $now = new DateTime();
    $now->setTime(0, 0);

    $q = DB::prepGetAll(DB::$DT_TEAM, new DBCond('school', $school->id));
    $q->fields(array('regatta'), DB::$DT_TEAM->db_name());
    $regs = DB::getAll(DB::$DT_REGATTA, new DBBool(array(new DBCond('season', $season),
							  new DBCondIn('id', $q))));
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
      $teams = $reg->getTeams();
      $num = count($teams);
      if ($reg->finalized !== null) {
	foreach ($teams as $pl => $team) {
	  if ($team->school->id == $school->id) {
	    // track placement
	    $places += (1 - (1 + $pl) / (1 + $num));
	    $avg_total++;

	    // track participation
	    $sk = $team->getRP(null, 'skipper');
	    $cr = $team->getRP(null, 'crew');
	    foreach ($sk as $rp) {
	      if (!isset($skippers[$rp->sailor->id])) {
		$skippers[$rp->sailor->id] = 0;
		$skip_objs[$rp->sailor->id] = $rp->sailor;
	      }
	      $skippers[$rp->sailor->id]++;
	    }
	    foreach ($cr as $rp) {
	      if (!isset($crews[$rp->sailor->id])) {
		$crews[$rp->sailor->id] = 0;
		$crew_objs[$rp->sailor->id] = $rp->sailor;
	      }
	      $crews[$rp->sailor->id]++;
	    }
	  }
	}
      }
      if ($reg->start_time <= $now &&
	  $reg->end_date >= $now) {
	$current[] = $reg;
      }
      if ($reg->end_date < $now) {
	$past[] = $reg;
      }
    }
    $avg = "Not applicable";
    if ($avg_total > 0)
      $avg = sprintf('%3.1f%%', 100 * ($places / $avg_total));
    
    // ------------------------------------------------------------
    // SCHOOL sailing now
    if (count($current) > 0) {
      $this->page->addSection($p = new XPort("Sailing now", array(), array('id'=>'sailing')));
      $p->add(new XTable(array(),
			 array(new XTHead(array(),
					  array(new XTR(array(),
							array(new XTH(array(), "Name"),
							      new XTH(array(), "Host"),
							      new XTH(array(), "Type"),
							      new XTH(array(), "Conference"),
							      new XTH(array(), "Last race"),
							      new XTH(array(), "Place(s)"))))),
			       $tab = new XTBody())));
      $row = 0;
      foreach ($current as $reg) {
	// borrowed from UpdateSeason
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
	
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->id] = $host->nick_name;
	  $confs[$host->conference->id] = $host->conference;
	}
	$link = new XA(sprintf('/%s/%s', $season, $reg->nick), $reg->name);
	$tab->add(new XTR(array('class' => sprintf("row%d", $row++ % 2)),
			  array(new XTD(array('class'=>'left'), $link),
				new XTD(array(), implode("/", $hosts)),
				new XTD(array(), $types[$reg->type]),
				new XTD(array(), implode("/", $confs)),
				new XTD(array(), $reg->start_time->format('m/d/Y')),
				new XTD(array(), $status))));
      }
    }

    // ------------------------------------------------------------
    // SCHOOL season summary
    $table = array("Conference" => $school->conference,
		   "Number of Regattas" => $total,
		   "Finish percentile" => $avg);
    $season_link = new XA('/'.(string)$season.'/', $season->fullString());

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
      $table["Most active skipper"] = implode(", ", $txt);
    }
    if (count($crews) > 0) {
      $txt = array();
      $i = 0;
      foreach ($crews as $id => $num) {
	if ($i++ >= 2)
	  break;
	$txt[] = sprintf('%s (%d races)', $crew_objs[$id], $num);
      }
      $table["Most active crew"] = implode(", ", $txt);
    }
    $this->page->setHeader($school, $table);

    // ------------------------------------------------------------
    // SCHOOL past regattas
    if (count($past) > 0) {
      $this->page->addSection($p = new XPort(array("Season history for ", $season_link)));
      $p->set('id', 'history');
      
      $p->add(new XTable(array('class'=>'participation-table'),
			 array(new XTHead(array(),
					  array(new XTR(array(),
							array(new XTH(array(), "Name"),
							      new XTH(array(), "Host"),
							      new XTH(array(), "Type"),
							      new XTH(array(), "Conference"),
							      new XTH(array(), "Date"),
							      new XTH(array(), "Status"),
							      new XTH(array(), "Place(s)"))))),
			       $tab = new XTBody())));

      $row = 0;
      foreach ($past as $reg) {
	$date = $reg->start_time;
	$status = ($reg->finalized === null) ? "Pending" : new XStrong("Official");
	$hosts = array();
	$confs = array();
	foreach ($reg->getHosts() as $host) {
	  $hosts[$host->id] = $host->nick_name;
	  $confs[$host->conference->id] = $host->conference;
	}
	$places = array();
	$teams = $reg->getTeams();
	foreach ($teams as $rank => $team) {
	  if ($team->school->id == $school->id)
	    $places[] = ($rank + 1);
	}
	$link = new XA(sprintf('/%s/%s', $season, $reg->nick), $reg->name);
	$tab->add(new XTR(array('class' => sprintf("row%d", $row++ % 2)),
			  array(new XTD(array('class'=>'left'), $link),
				new XTD(array(), implode("/", $hosts)),
				new XTD(array(), $types[$reg->type]),
				new XTD(array(), implode("/", $confs)),
				new XTD(array(), $date->format('M d')),
				new XTD(array(), $status),
				new XTD(array(), sprintf('%s/%d', implode(',', $places), count($teams))))));
      }
    }

    // ------------------------------------------------------------
    // Add links to all seasons
    $ul = new XUl(array('id'=>'other-seasons'));
    $num = 0;
    $root = sprintf('/schools/%s', $school->id);
    foreach (DB::getAll(DB::$SEASON) as $s) {
      $regs = DB::getAll(DB::$DT_REGATTA,
			 new DBBool(array(new DBCond('season', $s->id),
					  new DBCondIn('id', DB::prepGetAll(DB::$DT_TEAM, new DBCond('school', $school), array('regatta'))))));
      if (count($regs) > 0) {
	$num++;
	$ul->add(new XLi(new XA($root . '/' . $s->id, $s->fullString())));
      }
    }
    if ($num > 0)
      $this->page->addSection(new XDiv(array('id'=>'submenu-wrapper'),
				       array(new XH3("Other seasons", array('class'=>'nav')),
					     $ul)));
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
}
?>
