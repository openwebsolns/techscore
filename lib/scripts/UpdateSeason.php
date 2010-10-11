<?php
/**
 * Update the given season, given as an argument
 *
 * @author Dayan Paez
 * @version 2010-08-27
 */

class UpdateSeason {
  public static function run(Season $season) {
    $R = realpath(dirname(__FILE__).'/../../html');

    // Do season
    $dirname = "$R/$season";
    $M = new SeasonSummaryMaker($season);
    if (file_put_contents("$dirname/index.html", $M->getPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the season summary: %s\n", $filename), 8);
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  // Arguments
  if (count($argv) != 2) {
    printf("usage: %s <season>\n", $_SERVER['PHP_SELF']);
    exit(1);
  }

  // SETUP PATHS and other CONSTANTS
  $_SERVER['HTTP_HOST'] = $argv[0];
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  // GET Season
  $season = Season::parse($argv[1]);
  if ($season == null) {
    printf("Invalid season given: %s\n\n", $argv[1]);
    printf("usage: %s <season>\n", $_SERVER['PHP_SELF']);
    exit(1);
  }

  try {
    UpdateSeason::run($season);
    error_log(sprintf("I/0/%s\t(%d): Successful!\n", date('r'), $REGATTA->id()), 3, LOG_SEASON);
  }
  catch (InvalidArgumentException $e) {
    printf("Invalid regatta ID provided: %s\n", $argv[1]);
    exit(2);
  }
  catch (RuntimeException $e) {
    error_log(sprintf("E/%d/%s\t(%d): %s\n", $e->getCode(), date('r'), $argv[1], $e->getMessage()),
	      3, LOG_SEASON);
  }
}
?>