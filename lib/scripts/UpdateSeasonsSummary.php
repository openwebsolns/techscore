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
 * Creates the page summarizing all the seasons in the database along
 * with the count of regattas for each. This page should be updated
 * every time a season is updated, but not incur too much overhead.
 *
 */
class UpdateSeasonsSummary extends AbstractScript {

  private function getPage() {
    require_once('xml5/TPublicPage.php');
    $page = new TPublicPage("All seasons");
    $page->setDescription("Summary of all sailing seasons.");
    $page->addMetaKeyword("seasons");

    // SETUP menus top menu: Org Home, Schools, Seasons, *this*
    // season, and About
    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    if (($lnk = $page->getOrgTeamsLink()) !== null)
      $page->addMenu($lnk);
    if (($lnk = $page->getOrgLink()) !== null)
      $page->addMenu($lnk);

    $table = array();
    $current = Season::forDate(DB::$NOW);
    foreach (Season::getActive() as $season) {
      $mes = count($season->getRegattas());
      $mes .= ($mes == 1) ? " regatta" : " regattas";
      if ((string)$current == (string)$season)
        $mes .= " (current)";
      $table[$season->fullString()] = new XA(sprintf('/%s/', $season->id), $mes);
    }
    $page->setHeader("All Seasons", $table);
    return $page;
  }

  /**
   * Creates the new pages in the public domain
   *
   */
  public function run() {
    self::writeXml('/seasons/index.html', $this->getPage());
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateSeasonsSummary();
  $opts = $P->getOpts($argv);
  if (count($opts) > 0)
    throw new TSScriptException("Invalid argument(s)");
  $P->run();
}
?>
