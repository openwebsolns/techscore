<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 */

/**
 * Creates a custom 404 page with a brief sitemap of the site
 *
 */
class Update404 {
  private $page;

  private function fill() {
    if ($this->page !== null) return;

    $this->page = new TPublicPage('404: Page not found');

    // SETUP navigation
    $season = new Season(new DateTime());
    $this->page->addNavigation(new XA('/', "Home", array('class'=>'nav')));
    $this->page->addMenu(new XA('/schools', "Schools"));
    $this->page->addMenu(new XA(sprintf('/%s', $season), $season->fullString()));

    $this->page->addSection($p = new XPort("404: Page overboard!"));
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

    // latest regatta
    $one = $season->getRegattas(0, 1);
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
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {

  // SETUP PATHS and other CONSTANTS
  $_SERVER['HTTP_HOST'] = $argv[0];
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  try {
    Update404::run();
    error_log(sprintf("I:0:%s: Successful!\n", date('r')), 3, LOG_FRONT);
  }
  catch (Exception $e) {
    error_log(sprintf("E:%d:L%d:F%s:%s: %s\n",
		      $e->getCode(),
		      $e->getLine(),
		      $e->getFile(),
		      date('r'),
		      $e->getMessage()),
	      3, LOG_FRONT);
    print_r($e->getTrace());
  }
}
?>
