<?php
/**
 * Update the given school, given as an argument
 *
 * @author Dayan Paez
 * @version 2010-08-27
 * @package scripts
 */
class UpdateSchool {
  public static function run(School $school) {
    $R = realpath(dirname(__FILE__).'/../../html/schools');

    // Do season
    $filename = sprintf("%s/%s.html", $R, $school->id);
    $M = new SchoolSummaryMaker($school);
    if (file_put_contents($filename, $M->getPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the school summary: %s\n", $filename), 8);
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  // Arguments
  if (count($argv) != 2) {
    printf("usage: %s <school-id>\n", $_SERVER['PHP_SELF']);
    exit(1);
  }

  // SETUP PATHS and other CONSTANTS
  $_SERVER['HTTP_HOST'] = $argv[0];
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  // GET School
  $school = Preferences::getSchool($argv[1]);
  if ($school == null) {
    printf("Invalid school given: %s\n\n", $argv[1]);
    printf("usage: %s <school-id>\n", $_SERVER['PHP_SELF']);
    exit(1);
  }

  try {
    UpdateSchool::run($school);
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