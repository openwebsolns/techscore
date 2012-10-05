<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

require_once('AbstractScript.php');

/**
 * Creates a custom 404 page with a brief sitemap of the site
 *
 */
class Update404 extends AbstractScript {

  private function schoolsPage() {
    require_once('xml5/TPublicPage.php');
    $page = new TPublicPage('404: School page not found');
    $page->setDescription("School page not found. Possible linking or URL error.");

    // SETUP navigation, get latest season
    $seasons = DB::getAll(DB::$SEASON, new DBCond('start_date', DB::$NOW, DBCond::LE));
    $season = $seasons[0];

    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    if ($season !== null)
      $page->addMenu(new XA(sprintf('/%s', $season), $season->fullString()));
    $page->addMenu(new XA(Conf::$ICSA_HOME . '/teams/', "ICSA Teams"));
    $page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home", array('class'=>'nav')));

    $page->setHeader("404: School page not found");
    $page->addSection($p = new XPort("Page overboard!"));
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
    return $page;
  }

  private function generalPage() {
    require_once('xml5/TPublicPage.php');
    $page = new TPublicPage('404: Page not found');
    $page->setDescription("Page not found. Possible linking or URL error.");

    // SETUP navigation
    $season = Season::forDate(DB::$NOW);
    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    if ($season !== null)
      $page->addMenu(new XA(sprintf('/%s', $season), $season->fullString()));
    $page->addMenu(new XA(Conf::$ICSA_HOME . '/teams/', "ICSA Teams"));
    $page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));

    $page->setHeader("404: File not found");
    $page->addSection($p = new XPort("Page overboard!"));
    $p->add(new XP(array(), "We're sorry, but the page you are looking cannot be found. Thar be two possible reasons for this:"));
    $p->add(new XUL(array(),
                    array(new XLi("the page never joined the crew on this here vessel, or"),
                          new XLi("it has since walked the plank."))));

    // site EXPLANATION
    $page->addSection($p = new XPort("How to navigate this site"));
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
                                     array(new XLi(array(sprintf("Regatta, e.g. %s ", $one->name),
                                                         new XA(sprintf('/%s/%s', $season, $one->nick),
                                                                sprintf('/%s/%s', $season, $one->nick),
                                                                array('class'=>'tt')))))))));
    }
    return $page;
  }

  /**
   * Creates the new page summary in the public domain
   *
   */
  public function run($general = false, $schools = false) {
    if ($general !== false)
      self::writeXml('/404.html', $this->generalPage());

    if ($schools !== false)
      self::writeXML('/schools/404.html', $this->schoolsPage());
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------

  protected $cli_opts = 'general | schools';
  protected $cli_usage = "Choose one or more of the possible arguments";
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new Update404();
  $opts = $P->getOpts($argv);
  $gen = false;
  $sch = false;
  foreach ($opts as $opt) {
    switch ($opt) {
    case 'general': $gen = true; break;
    case 'schools': $sch = true; break;
    default:
      throw new TSScriptException("Invalid argument: $opt");
    }
  }
  if (!$gen && !$sch)
    throw new TSScriptException("No file specified.");
  $P->run($gen, $sch);
}
?>
