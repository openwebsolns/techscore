<?php
/**
 * Update the given regatta, given as an argument
 *
 * @author Dayan Paez
 * @version 2010-08-27
 * @see UpdateScores.php
 */

class UpdateRotation {
  public static function run(Regatta $reg) {
    require_once('conf.php');
    $M = new ReportMaker($reg);
    if (!$M->hasRotation()) {
      printf("Regatta %s (%d) does not have a rotation!", $reg->get(Regatta::NAME), $argv[1]);
      exit(2);
    }

    $R = realpath(dirname(__FILE__).'/../../html');
    $season = $reg->get(Regatta::SEASON);
    if (!file_exists("$R/$season") && mkdir("$R/$season") === false) {
      printf("Unable to make the season folder: %s\n", $season);
      exit(4);
    }
    $dirname = "$R/$season/".$reg->get(Regatta::NICK_NAME);
    if (!file_exists($dirname) && mkdir($dirname) === false) {
      echo "Unable to make regatta directory: $dirname\n";
      exit(8);
    }
    $filename = "$dirname/rotations.html";
    if (file_put_contents($filename, $M->getRotationPage()) === false) {
      printf("Unable to make the regatta rotation: %s\n", $filename);
      exit(16);
    }
  }
}

// Arguments
if (!isset($argv) || !is_array($argv) || count($argv) != 2) {
  printf("usage: %s <regatta-id>\n", $_SERVER['PHP_SELF']);
  exit(1);
}

// ------------------------------------------------------------
// Run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  // SETUP PATHS and other CONSTANTS
  $_SERVER['HTTP_HOST'] = $argv[0];
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  // GET REGATTA
  try {
    $REGATTA = new Regatta((int)$argv[1]);
    UpdateRotation::run($REGATTA);
  }
  catch (Exception $e) {
    printf("Invalid regatta ID provided: %s\n", $argv[1]);
    exit(2);
  }
}
?>