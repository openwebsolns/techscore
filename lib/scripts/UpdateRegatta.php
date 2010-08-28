<?php
/**
 * Update the given regatta, given as an argument
 *
 * @author Dayan Paez
 * @version 2010-08-27
 */

// Arguments
if (!isset($argv) || !is_array($argv) || count($argv) != 2) {
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
}
catch (Exception $e) {
  printf("Invalid regatta ID provided: %s\n", $argv[1]);
  exit(2);
}

$R = realpath(dirname(__FILE__).'/../../html');
$M = new ReportMaker($REGATTA);
$season = $REGATTA->get(Regatta::SEASON);
if (!file_exists("$R/$season") && mkdir("$R/$season") === false) {
  printf("Unable to make the season folder: %s\n", $season);
  exit(4);
}
$dirname = "$R/$season/".$REGATTA->get(Regatta::NICK_NAME);
if (!file_exists($dirname) && mkdir($dirname) === false) {
  echo "Unable to make regatta directory: $dirname\n";
  exit(8);
}
$filename = "$dirname/index.html";
if (file_put_contents($filename, $M->getPage()) === false) {
  printf("Unable to make the regatta report: %s\n", $filename);
  exit(16);
}

?>