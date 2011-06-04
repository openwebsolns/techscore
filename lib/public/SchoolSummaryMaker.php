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

    $types = Preferences::getRegattaTypeAssoc();
    $school = $this->school;
    $season = $this->season;
    $this->page = new TPublicPage($school);

    // SETUP navigation
    $this->page->addNavigation(new XA("/schools", "Schools", array("class"=>"nav")));
    $this->page->addNavigation(new XA(sprintf("/schools/%s", $school->id), $school->name, array("class"=>"nav")));
    $this->page->addMenu(new XA($this->getBlogLink(), "ICSA Info"));
    // Add links to last 7 seasons
    require_once('mysqli/DB.php');
    DBME::setConnection(Preferences::getConnection());
    $num = 0;
    foreach (DBME::getAll(DBME::$SEASON) as $s) {
      if (file_exists(sprintf('%s/../../html/schools/%s/%s.html', dirname(__FILE__), $school->id, $s))) {
	$this->page->addMenu(new XA($s, $s->fullString()));
	if ($num++ >= 6)
	  break;
      }
    }

    $this->page->addSection($d = new XDiv(array('id'=>'reg-details')));
    $d->add(new XH2($school));
    $d->add($l = new XUl());
    $l->add(new XLI($school->conference . ' Conference'));

    $burgee = sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $this->school->id);
    if (file_exists($burgee))
      $l->add(new XLI(new XImg(sprintf('/inc/img/schools/%s.png', $this->school->id), $this->school->id)));

    // current season
    $now = new DateTime();
    $now->setTime(0, 0);

    $q = DBME::prepGetAll(DBME::$TEAM, new MyCond('school', $school->id));
    $q->fields(array('regatta'), DBME::$TEAM->db_name());
    $regs = DBME::getAll(DBME::$REGATTA, new MyBoolean(array(new MyCond('season', $season),
							     new MyCondIn('id', $q))));
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
    $season_link = new XA('/'.(string)$season, $season->fullString());
    $this->page->addSection($p = new XPort(array("Season summary for ", $season_link)));
    $p->set('id', 'summary');

    $p->add(new XDiv(array('class'=>'stat'),
		     array(new XSpan("Number of Regattas:", array("class"=>"prefix")), $total)));
    $p->add(new XDiv(array('class'=>'stat'),
		    array(new XSpan("Finish percentile:", array("class"=>"prefix")), $avg)));
    
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
      $p->add(new XDiv(array('class'=>'stat'),
		       array(new XSpan("Most active skipper:", array('class'=>'prefix')), implode(", ", $txt))));
    }
    if (count($crews) > 0) {
      $txt = array();
      $i = 0;
      foreach ($crews as $id => $num) {
	if ($i++ >= 2)
	  break;
	$txt[] = sprintf('%s (%d)', $crew_objs[$id], $num);
      }
      $p->add(new XDiv(array('class'=>'stat'),
		       array(new XSpan("Most active crew:", array('class'=>'prefix')), implode(", ", $txt))));
    }

    // ------------------------------------------------------------
    // SCHOOL past regattas
    if (count($past) > 0) {
      $this->page->addSection($p = new XPort(array("Season history for ", $season_link)));
      $p->set('id', 'history');
      
      $p->add(new XTable(array(),
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
	$status = ($reg->finalized === null) ? "Pending" : "Official";
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
				new XTD(array(), $date->format('m/d/Y')),
				new XTD(array(), $status),
				new XTD(array(), sprintf('%s/%d', implode(',', $places), count($teams))))));
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
}
?>
