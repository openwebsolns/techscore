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
 * Updates the given sailor's profile, given as an argument
 *
 * @author Dayan Paez
 * @created 2015-01-18
 */
class UpdateSailor extends AbstractScript {

  private function getPage(Member $sailor, Season $season) {
    require_once('public/SailorPage.php');

    $page = new SailorPage($sailor, $season);
    return $page;
  }
  
  /**
   * Creates the given season summary for the given sailor
   *
   * @param Member $sailor the sailor whose summary to generate
   * @param Season $season the season
   */
  public function run(Member $sailor, Season $season) {
    $dirname = $sailor->getURL();

    // Do season
    $today = Season::forDate(DB::T(DB::NOW));
    $base = (string)$season;

    // Create season directory
    $fullname = $dirname . $base;

    // is this current season
    $current = false;
    if ((string)$today == (string)$season)
      $current = true;

    $filename = "$fullname/index.html";
    $content = $this->getPage($sailor, $season);
    self::write($filename, $content);
    self::errln("Wrote season $season summary for $sailor.", 2);
    
    // If current, do we also need to create index page?
    if ($current) {
      $filename = $dirname . 'index.html';
      self::write($filename, $content);
      self::errln("Wrote current summary for $sailor.", 2);
    }
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------

  protected $cli_opts = '<sailor_id> [season]';
  protected $cli_usage = " <sailor_id>  the ID of the sailor to update
 season       (optional) the season to update (defaults to current)";
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateSailor();
  $opts = $P->getOpts($argv);

  // Validate inputs
  if (count($opts) == 0)
    throw new TSScriptException("No sailor ID provided");
  $id = array_shift($opts);
  if (($sailor = DB::getSailor($id)) === null)
    throw new TSScriptException("Invalid sailor ID provided: $id");
  if ($sailor->getURL() === null)
    throw new TSScriptException("Sailor $sailor does not have a URL");
  if (DB::g(STN::SAILOR_PROFILES) === null)
    throw new TSScriptException("Feature has been disabled.");

  // Season
  if (count($opts) > 1)
    throw new TSScriptException("Invalid argument provided");
  $season = Season::forDate(DB::T(DB::NOW));
  if (count($opts) > 0) {
    $id = array_shift($opts);
    if (($season = DB::getSeason($id)) === null)
      throw new TSScriptException("Invalid season provided: $id");
  }
  if ($season === null)
    throw new TSScriptException("No current season exists");

  $P->run($sailor, $season);
}
?>