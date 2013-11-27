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
 * @author Dayan Paez
 * @version 2011-02-08
 * @package www
 */
class UpdateSchoolsSummary extends AbstractScript {

  public function run() {
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
      $page->addSection($p = new XPort($conf . " " . DB::g(STN::CONFERENCE_TITLE)));
      $p->set('id', $conf);
      $p->add($tab = new XQuickTable(array('class'=>'schools-table'), array("Mascot", "School", "City", "State")));

      foreach ($conf->getSchools() as $i => $school) {
        $num_schools++;
        $link = sprintf('/schools/%s', $school->id);

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
    self::writeXml($f, $page);
    self::errln("Wrote schools summary page");
  }
}

if (isset($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateSchoolsSummary();
  $opts = $P->getOpts($argv);
  if (count($opts) > 0)
    throw new TSScriptException("Invalid argument");
  $P->run();
}
?>
