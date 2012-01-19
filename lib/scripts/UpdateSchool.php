<?php
/**
 * Update the given school, given as an argument
 *
 * @author Dayan Paez
 * @version 2010-08-27
 * @package scripts
 */
class UpdateSchool {
  public static function run(School $school, Season $season) {
    $path = dirname(__FILE__).'/../../html/schools';
    $R = realpath($path);

    // Create schools directory if necessary
    if ($R === false && !mkdir($path)) {
      throw new RuntimeException("Unable to make directory for all schools.");
    }
    $R = realpath($path);

    // Create directory if one does not exist for that school
    $dirname = sprintf('%s/%s', $R, $school->id);
    if (!file_exists($dirname) && mkdir($dirname) === false)
      throw new RuntimeException("Unable to make school directory: $dirname\n", 4);

    // Do season
    $today = Season::forDate(DB::$NOW);
    $current = false;
    $base = (string)$season;
    // is this current season
    if ((string)$today == (string)$season)
      $current = true;

    require_once('public/SchoolSummaryMaker.php');
    $filename = "$dirname/$base.html";
    $M = new SchoolSummaryMaker($school, $season);
    if (file_put_contents($filename, $M->getPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the school summary: %s\n", $filename), 8);

    // If current, do we also need to create index page?
    if ($current) {
      if (file_exists("$dirname/index.html")) {
	if (fileinode($filename) != fileinode("$dirname/index.html")) {
	  unlink("$dirname/index.html");
	  link($filename, "$dirname/index.html");
	}
      }
      else
	link($filename, "$dirname/index.html");
    }
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  // Arguments
  if (count($argv) < 2) {
    printf("usage: %s <school-id> [season]\n", $_SERVER['PHP_SELF']);
    exit(1);
  }

  // SETUP PATHS and other CONSTANTS
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  // GET School
  $school = DB::getSchool($argv[1]);
  if ($school == null) {
    printf("Invalid school given: %s\n\n", $argv[1]);
    printf("usage: %s <school-id>\n", $_SERVER['PHP_SELF']);
    exit(1);
  }
  // season?
  if (count($argv) == 3) {
    $season = DB::getSeason($argv[2]);
    if ($season == null) {
      printf("Invalid season given: %s\n\n", $argv[2]);
      printf("usage: %s <season>\n", $_SERVER['PHP_SELF']);
      exit(1);
    }
  }
  else
    $season = Season::forDate(DB::$NOW);

  try {
    UpdateSchool::run($school, $season);
    error_log(sprintf("I:0:%s\t(%s): Successful!\n", date('r'), $school->id), 3, LOG_SCHOOL);
  }
  catch (Exception $e) {
    error_log(sprintf("E:%d:L%d:F%s:%s\t(%d): %s\n",
		      $e->getCode(),
		      $e->getLine(),
		      $e->getFile(),
		      date('r'),
		      $argv[1],
		      $e->getMessage()),
	      3, LOG_SCHOOL);
    print_r($e->getTrace());
  }
}
?>