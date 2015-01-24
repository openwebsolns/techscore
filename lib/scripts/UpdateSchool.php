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
 * Update the given school, given as an argument
 *
 * @author Dayan Paez
 * @version 2010-08-27
 * @package scripts
 */
class UpdateSchool extends AbstractScript {

  /**
   * Creates the given season summary for the given school
   *
   * @param School $school the school whose summary to generate
   * @param Season $season the season
   */
  public function run(School $school, Season $season) {
    $dirname = $school->getURL();

    // Do season
    $today = Season::forDate(DB::T(DB::NOW));
    $base = (string)$season;

    // Create season directory
    $fullname = $dirname . $base;

    // is this current season
    $current = false;
    if ((string)$today == (string)$season)
      $current = true;

    require_once('public/SchoolReportMaker.php');
    $maker = new SchoolReportMaker($school, $season);
    $filename = "$fullname/index.html";
    $content = $maker->getMainPage();
    self::write($filename, $content);
    self::errln("Wrote season $season summary for $school.", 2);
    
    // If current, do we also need to create index page?
    if ($current) {
      $filename = $dirname . 'index.html';
      self::write($filename, $content);
      self::errln("Wrote current summary for $school.", 2);
    }
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------

  protected $cli_opts = '<school_id> [season]';
  protected $cli_usage = " <school_id>  the ID of the school to update
 season       (optional) the season to update (defaults to current)";
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateSchool();
  $opts = $P->getOpts($argv);

  // Validate inputs
  if (count($opts) == 0)
    throw new TSScriptException("No school ID provided");
  $id = array_shift($opts);
  if (($school = DB::getSchool($id)) === null)
    throw new TSScriptException("Invalid school ID provided: $id");

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

  $P->run($school, $season);
}
?>
