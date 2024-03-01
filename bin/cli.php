<?php
/*
 * CLI entry to run scripts.
 *
 * @author Dayan Paez
 * @created 2015-11-24
 */

if (!isset($argv) || !is_array($argv)) {
  echo "Must be run from the command line.";
  exit(128);
}

// Turn off user if requested via environment variable
define('NO_USER', !empty($_SERVER['TECHSCORE_NO_USER']));

require_once(dirname(__DIR__).'/lib/conf.php');

$base = array_shift($argv);
if (count($argv) == 0) {
  printf(
    "Usage: %s <classname> [<args>]\n\n  <classname> can be fully specified (default namespace: 'scripts')\n",
    $base
  );
  exit(129);
}

$classname = $argv[0];
if (!class_exists($classname)) {
  $classname = '\\scripts\\' . $classname;
}
if (!class_exists($classname)) {
  printf("Class '%s' not found\n", $classname);
  exit(130);
}

// Hand-off
$P = new $classname();
$P->runCli($argv);
