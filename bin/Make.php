<?php
/*
 * Uses the local variables in lib/conf.local.php to update the
 * different system files that comprise the local installation of
 * TechScore.
 *
 * This script should be run whenever conf.local.php to generate the
 * ~~apache.conf file, the crontab file~~, and (eventually) the database
 * updates.
 *
 * @author Dayan Paez
 * @version 2012-04-11
 * @package bin
 */

define('NO_USER', true);
require_once(dirname(dirname(__FILE__)).'/lib/conf.php');

function usage($mes = null) {
  if ($mes !== null)
    echo "$mes\n\n";
  echo "usage: php Make.php <getprop PROP>

Use 'getprop' to retrieve a constant from Conf class.\n";
  exit(1);
}

$targets = array('getprop');
$args = array();
if (!isset($argv) || !is_array($argv) || count($argv) < 1)
  usage("missing argument list");

$base = array_shift($argv);
while (count($argv) > 0) {
  $arg = array_shift($argv);
  if (!in_array($arg, $targets))
    usage("invalid argument: $arg");
  $args[$arg] = 1;
  // Handle properties
  if ($arg == 'getprop') {
    if (count($argv) == 0)
      usage("getprop: missing property to get");
    $prop = array_shift($argv);
    if (!isset(Conf::$$prop))
      usage("getprop: invalid property $prop");
    printf("%s\n", Conf::$$prop);
  }
}

if (count($args) == 0)
  usage("no arguments provided");
