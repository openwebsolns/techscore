<?php
/**
 * Update the given regatta, given as an argument
 *
 * @author Dayan Paez
 * @version 2010-08-27
 */

class UpdateScore {
  public static function run(Regatta $reg) {
    require_once('conf.php');

    $R = realpath(dirname(__FILE__).'/../../html');
    $M = new ReportMaker($reg);
    $season = $reg->get(Regatta::SEASON);
    if (!file_exists("$R/$season") && mkdir("$R/$season") === false)
      throw new RuntimeException(sprintf("Unable to make the season folder: %s\n", $season), 2);

    $dirname = "$R/$season/".$reg->get(Regatta::NICK_NAME);
    if (!file_exists($dirname) && mkdir($dirname) === false)
      throw new RuntimeException("Unable to make regatta directory: $dirname\n", 4);
    
    $filename = "$dirname/index.html";
    if (file_put_contents($filename, $M->getScoresPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the regatta report: %s\n", $filename), 8);

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
    printf("usage: %s <regatta-id>\n", $_SERVER['PHP_SELF']);
    exit(1);
  }
  // SETUP PATHS and other CONSTANTS
  $_SERVER['HTTP_HOST'] = $argv[0];
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  // GET REGATTA
  try {
    $REGATTA = new Regatta($argv[1]);
    UpdateScore::run($REGATTA);
    error_log(sprintf("I/0/%s\t(%d): Successful!\n", date('r'), $REGATTA->id()), 3, LOG_SCORE);
  }
  catch (InvalidArgumentException $e) {
    printf("Invalid regatta ID provided: %s\n", $argv[1]);
    exit(2);
  }
  catch (RuntimeException $e) {
    error_log(sprintf("E/%d/%s\t(%d): %s\n", $e->getCode(), date('r'), $argv[1], $e->getMessage()),
	      3, LOG_SCORE);
  }
}
?>