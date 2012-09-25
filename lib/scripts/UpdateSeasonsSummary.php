<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

/**
 * Creates the page summarizing all the seasons in the database along
 * with the count of regattas for each. This page should be updated
 * every time a season is updated, but not incur too much overhead.
 *
 */
class UpdateSeasonsSummary {
  private $page;

  private function fill() {
    if ($this->page !== null) return;

    require_once('xml5/TPublicPage.php');
    $this->page = new TPublicPage("All seasons");
    $this->page->setDescription("Summary of all sailing seasons.");
    $this->page->addMetaKeyword("seasons");

    // SETUP menus top menu: ICSA Home, Schools, Seasons, *this*
    // season, and About
    $this->page->addMenu(new XA('/', "Home"));
    $this->page->addMenu(new XA('/schools/', "Schools"));
    $this->page->addMenu(new XA('/seasons/', "Seasons"));
    $this->page->addMenu(new XA(Conf::$ICSA_HOME . '/teams/', "ICSA Teams"));
    $this->page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));

    $table = array();
    $current = Season::forDate(DB::$NOW);
    foreach (Season::getActive() as $season) {
      $mes = count($season->getRegattas());
      $mes .= ($mes == 1) ? " regatta" : " regattas";
      if ((string)$current == (string)$season)
        $mes .= " (current)";
      $table[$season->fullString()] = new XA(sprintf('/%s/', $season->id), $mes);
    }
    $this->page->setHeader("All Seasons", $table);
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
   * Creates the new pages in the public domain
   *
   */
  public static function run() {
    $R = realpath(dirname(__FILE__).'/../../html');

    // Do season
    $dirname = "$R/seasons";

    // create folder, if necessary
    if (!file_exists($dirname) && mkdir($dirname) === false)
      throw new RuntimeException(sprintf("Unable to make the all-seasons folder: %s\n", $dirname), 2);

    $M = new UpdateSeasonsSummary();
    if (file_put_contents("$dirname/index.html", $M->getPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the all-seasons summary: %s\n", $dirname), 8);
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  // Arguments
  if (count($argv) != 1) {
    printf("usage: %s\n", $_SERVER['PHP_SELF']);
    exit(1);
  }

  // SETUP PATHS and other CONSTANTS
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  try {
    UpdateSeasonsSummary::run();
  }
  catch (Exception $e) {
    error_log(sprintf("E:%d:L%d:F%s:%s\t(%d): %s\n",
                      $e->getCode(),
                      $e->getLine(),
                      $e->getFile(),
                      date('r'),
                      $argv[1],
                      $e->getMessage()),
              3, Conf::$LOG_SEASON);
    print_r($e->getTrace());
  }
}
?>
