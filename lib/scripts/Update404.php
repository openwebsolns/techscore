<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

/**
 * Creates a custom 404 page with a brief sitemap of the site
 *
 */
class Update404 {
  private $page;
  private $mode;

  /**
   * Specify 'schools' to create the schools 404 page
   *
   * @param String $mode either 'schools' or anything else
   */
  public function __construct($mode = 'general') {
    $this->mode = $mode;
  }

  private function fillSchools() {
    require_once('xml5/TPublicPage.php');
    $this->page = new TPublicPage('404: School page not found');
    $this->page->addDescription("School page not found. Possible linking or URL error.");

    // SETUP navigation, get latest season
    $seasons = DB::getAll(DB::$SEASON, new DBCond('start_date', DB::$NOW, DBCond::LE));
    $season = $seasons[0];

    $this->page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home", array('class'=>'nav')));
    $this->page->addMenu(new XA('/schools/', "Schools"));
    $this->page->addMenu(new XA('/seasons/', "Seasons"));
    if ($season !== null)
      $this->page->addMenu(new XA(sprintf('/%s', $season), $season->fullString()));
    $this->page->addMenu(new XA(Conf::$ICSA_HOME . '/teams/', "ICSA Teams"));
    $this->page->addMenu(new XA('http://www.collegesailing.org/about/', "About"));

    $this->page->setHeader("404: School page not found");
    $this->page->addSection($p = new XPort("Page overboard!"));
    $p->add(new XP(array(), "We're sorry, but the page you are requesting, or the school you seek, cannot be found. This can happen if:"));
    $p->add(new XUL(array(),
		    array(new XLi("the URL misspelled the ID of the school,"),
			  new XLi("or the school is not recognized by ICSA."))));
    $p->add(new XP(array(),
		   array("Make sure the ID of the school is in upper case, as in ",
			 new XA('/schools/MIT', '/schools/MIT', array('class'=>'tt')), " vs ",
			 new XSpan("/schools/mit", array('class'=>'tt')), ".")));

    $p->add(new XP(array(),
		   array("Also make sure the season (if any) is spelled correctly. This should be in lower case and one of ",
			 new XSpan("f", array('class'=>'tt')),
			 " for Fall or ",
			 new XSpan("s", array('class'=>'tt')),
			 " for Spring; followed by the last two digits of the year.")));

    $p->add(new XP(array(),
		   array("Of course, your best bet is to visit ",
			 new XA('/schools', "the schools directory"),
			 " to view all the schools in the system.")));

    $p->add(new XP(array(), new XStrong("Happy sailing!")));
  }

  private function fill() {
    if ($this->page !== null) return;

    if ($this->mode == 'schools') {
      $this->fillSchools();
      return;
    }

    require_once('xml5/TPublicPage.php');
    $this->page = new TPublicPage('404: Page not found');
    $this->page->addDescription("Page not found. Possible linking or URL error.");

    // SETUP navigation
    $season = Season::forDate(DB::$NOW);
    $this->page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));
    $this->page->addMenu(new XA('/schools/', "Schools"));
    $this->page->addMenu(new XA('/seasons/', "Seasons"));
    if ($season !== null)
      $this->page->addMenu(new XA(sprintf('/%s', $season), $season->fullString()));
    $this->page->addMenu(new XA(Conf::$ICSA_HOME . '/teams/', "ICSA Teams"));
    $this->page->addMenu(new XA('http://www.collegesailing.org/about/', "About"));

    $this->page->setHeader("404: File not found");
    $this->page->addSection($p = new XPort("Page overboard!"));
    $p->add(new XP(array(), "We're sorry, but the page you are looking cannot be found. Thar be two possible reasons for this:"));
    $p->add(new XUL(array(),
		    array(new XLi("the page never joined the crew on this here vessel, or"),
			  new XLi("it has since walked the plank."))));

    // site EXPLANATION
    $this->page->addSection($p = new XPort("How to navigate this site"));
    $p->add(new XP(array(),
		   array("We try to make our sites easy to navigate. Starting at our ",
			 new XA('/', "home page"),
			 ", you can navigate by following the examples below:")));
    $p->add($ul = new XUL());
    $ul->add(new XLi(array("Schools ", new XA('/schools', '/schools', array('class'=>'tt')),
			   new XUL(array(),
				   array(new XLi(array("School ID, e.g. ",
						       new XA('/schools/MIT', '/schools/MIT',
							      array('class'=>'tt')),
						       new XUL(array(),
							       array(new XLi(array("Fall 2010 summary ",
										   new XA('/schools/MIT/f10',
											  '/schools/MIT/f10',
											  array('class'=>'tt')))))))))))));

    if ($season === null)
      return;

    // latest regatta
    $res = $season->getRegattas();
    if (count($res) > 0) {
      $one = $res[0];
      unset($res);
      $ul->add(new XLi(array($season->fullString() . " ",
			     new XA(sprintf('/%s', $season),
				    sprintf('/%s', $season),
				    array('class'=>'tt')),
			     new XUl(array(),
				     array(new XLi(array(sprintf("Regatta, e.g. %s ", $one[0]->name),
							 new XA(sprintf('/%s/%s', $season, $one[0]->nick),
								sprintf('/%s/%s', $season, $one[0]->nick),
								array('class'=>'tt')))))))));
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

    $M = new Update404();
    if (file_put_contents("$R/404.html", $M->getPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the 404 page: %s/404.html\n", $R), 8);
  }

  /**
   * Creates a new 404 page for schools
   *
   */
  public static function runSchool() {
    $R = realpath(dirname(__FILE__).'/../../cache');

    $M = new Update404('schools');
    if (file_put_contents("$R/404-schools.html", $M->getPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the 404 page: %s/404-schools.html\n", $R), 8);
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {

  // SETUP PATHS and other CONSTANTS
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  $mode = 'general';
  if (count($argv) > 1) {
    if ($argv[1] != 'schools') {
      printf("usage: %s [schools]\n", $argv[0]);
      exit(1);
    }
    $mode = 'schools';
  }

  try {
    if ($mode == 'schools')
      Update404::runSchool();
    else
      Update404::run();
    error_log(sprintf("I:0:%s: Successful (%s)!\n", date('r'), $mode), 3, Conf::$LOG_FRONT);
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
