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
    $season = null;
    if (count($seasons) > 0)
      $season = $seasons[0];

    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    if ($season !== null)
      $page->addMenu(new XA(sprintf('/%s', $season), $season->fullString()));
    if (($lnk = $page->getOrgTeamsLink()) !== null)
      $page->addMenu($lnk);
    if (($lnk = $page->getOrgLink()) !== null)
      $page->addMenu($lnk);

    $page->setHeader("404: School page not found");
    $page->addSection($p = new XPort("Page overboard!"));
    $cont = DB::get(DB::$TEXT_ENTRY, Text_Entry::SCHOOL_404);
    if ($cont !== null)
      $p->add(new XRawText($cont->html));
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
    if (($n = DB::g(STN::ORG_NAME)) !== null && ($u = DB::g(STN::ORG_TEAMS_URL)) !== null)
      $page->addMenu(new XA($u, sprintf("%s Teams", $n)));
    if ($n !== null && ($u = DB::g(STN::ORG_URL)) !== null)
      $page->addMenu(new XA($u, sprintf("%s Home", $n)));

    $page->setHeader("404: File not found");
    $page->addSection($p = new XPort("Page overboard!"));
    $cont = DB::get(DB::$TEXT_ENTRY, Text_Entry::GENERAL_404);
    if ($cont !== null)
      $p->add(new XRawText($cont->html));
    return $page;
  }

  /**
   * Creates the new page summary in the public domain
   *
   */
  public function run($general = false, $schools = false) {
    if ($general !== false)
      self::write('/404.html', $this->generalPage());

    if ($schools !== false)
      self::write('/schools/404.html', $this->schoolsPage());
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
