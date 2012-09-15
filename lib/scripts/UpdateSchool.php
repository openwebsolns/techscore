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

    // Create season directory
    $fullname = "$dirname/$base";
    if (!file_exists($fullname) && mkdir($fullname) === false)
      throw new RuntimeException("Unable to make school's season directory: $dirname\n", 4);

    // is this current season
    if ((string)$today == (string)$season)
      $current = true;

    require_once('public/SchoolSummaryMaker.php');
    $filename = "$fullname/index.html";
    $M = new SchoolSummaryMaker($school, $season);
    $content = $M->getPage();
    if (file_put_contents($filename, $content) === false)
      throw new RuntimeException(sprintf("Unable to make the school summary: %s\n", $filename), 8);

    // If current, do we also need to create index page?
    if ($current) {
      if (file_put_contents("$dirname/index.html", $content) === false)
	throw new RuntimeException(sprintf("Unable to make the school summary for current season: %s\n", $filename), 8);
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
  }
  else
    $season = Season::forDate(DB::$NOW);
  if ($season == null) {
    echo "Invalid season provided.\n\n";
    printf("usage: %s <season>\n", $_SERVER['PHP_SELF']);
    exit(1);
  }

  try {
    UpdateSchool::run($school, $season);
    error_log(sprintf("I:0:%s\t(%s): Successful!\n", date('r'), $school->id), 3, Conf::$LOG_SCHOOL);
  }
  catch (Exception $e) {
    error_log(sprintf("E:%d:L%d:F%s:%s\t(%d): %s\n",
		      $e->getCode(),
		      $e->getLine(),
		      $e->getFile(),
		      date('r'),
		      $argv[1],
		      $e->getMessage()),
	      3, Conf::$LOG_SCHOOL);
    print_r($e->getTrace());
  }
}
?>