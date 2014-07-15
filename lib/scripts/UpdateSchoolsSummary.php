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
 * The page that summarizes the schools in the system.
 *
 * 2014-06-23: Use this page for the conference summary page
 *
 * @author Dayan Paez
 * @version 2011-02-08
 * @package www
 */
class UpdateSchoolsSummary extends AbstractScript {

  /**
   * Creates and writes the page to file
   *
   * This same page is used for both school and conference "landing"
   * pages.
   *
   * @param boolean $as_conference true to write "conference-mode"
   */
  public function run($as_conference = false) {
    $are_conferences_published = (DB::g(STN::PUBLISH_CONFERENCE_SUMMARY) !== null);

    require_once('xml5/TPublicPage.php');
    $page = new TPublicPage("All Schools");
    $page->body->set('class', 'school-summary-page');
    $desc = "Listing of schools, with regatta participation.";
    if (($n = DB::g(STN::ORG_NAME)) !== null)
      $desc = sprintf("Listing of schools in %s, with regatta participation.", $n);
    $page->setDescription($desc);
    $page->addMetaKeyword("schools");

    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    if (($lnk = $page->getOrgTeamsLink()) !== null)
      $page->addMenu($lnk);
    if (($lnk = $page->getOrgLink()) !== null)
      $page->addMenu($lnk);

    $confs = DB::getAll(DB::$CONFERENCE);
    $num_schools = 0;
    // ------------------------------------------------------------
    // Summary of each conference
    // count the number of regattas this school has teams in
    foreach ($confs as $conf) {
      $title = $conf . " " . DB::g(STN::CONFERENCE_TITLE);
      if ($are_conferences_published)
        $title = new XA($conf->url, $title);
      $page->addSection($p = new XPort($title));
      $p->set('id', $conf);
      $p->add($tab = new XQuickTable(array('class'=>'schools-table'), array("Mascot", "School", "City", "State")));

      foreach ($conf->getSchools() as $i => $school) {
        $num_schools++;
        $link = $school->getURL();

        $burg = $school->drawSmallBurgee("");
        $tab->addRow(array(new XTD(array('class'=>'burgeecell'), $burg),
                           new XTD(array('class'=>'schoolname'), new XA($link, $school->name)),
                           $school->city,
                           $school->state),
                     array('class'=>'row'.($i%2)));
      }
    }

    $header = DB::g(STN::CONFERENCE_TITLE) . "s";
    if (($n = DB::g(STN::ORG_NAME)) !== null)
      $header = sprintf("%s %s", $n, $header);
    $page->setHeader($header, array(sprintf("# of %ss", DB::g(STN::CONFERENCE_TITLE)) => count($confs), "# of Schools" => $num_schools));

    // Write to file!
    $f = '/schools/index.html';
    $mes = "Wrote schools summary page";
    if ($as_conference) {
      $f = sprintf('/%s/index.html', DB::g(STN::CONFERENCE_URL));
      $mes = sprintf("Wrote %ss summary page", DB::g(STN::CONFERENCE_TITLE));
    }
    self::write($f, $page);
    self::errln($mes);
  }

  protected $cli_opts = '-c';
  protected $cli_usage = ' -c, --conf  write conference summary page instead';
}

if (isset($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateSchoolsSummary();
  $opts = $P->getOpts($argv);
  $as_conference = false;
  while (count($opts) > 0) {
    $arg = array_shift($opts);
    switch ($arg) {
    case '-c':
    case '--conf':
      $as_conference = true;
      break;

    default:
      throw new TSScriptException("Invalid argument: " . $opt);
    }
  }
  $P->run($as_conference);
}
?>
