<?php
namespace scripts;

use \TPublicPage;
use \Season;
use \DB;
use \TSScriptException;

use \XA;

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
    if (($lnk = $page->getOrgTeamsLink()) !== null) {
      $page->addMenu($lnk);
    }
    if (($lnk = $page->getOrgLink()) !== null) {
      $page->addMenu($lnk);
    }

    $table = array();
    $current = Season::forDate(DB::T(DB::NOW));
    foreach (Season::getActive() as $season) {
      $mes = count($season->getRegattas());
      $mes .= ($mes == 1) ? " regatta" : " regattas";
      if ((string)$current == (string)$season) {
        $mes .= " (current)";
      }
      $table[$season->fullString()] = new XA($season->getURL(), $mes);
    }
    $page->setHeader("All Seasons", $table);
    return $page;
  }

  /**
   * Creates the new pages in the public domain
   *
   */
  public function run() {
    self::write('/seasons/index.html', $this->getPage());
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    if (count($opts) > 0) {
      throw new TSScriptException("Invalid argument(s)");
    }
    $this->run();
  }
}
