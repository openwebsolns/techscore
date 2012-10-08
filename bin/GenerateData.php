<?php
/**
 * Scours the regatta database, updating the information about each of
 * the finalized regatta's and compiling that data into the dt_*
 * tables for easier retrieval by update scripts, etc.
 *
 * This script is meant to be run from the command line
 *
 * @author Dayan Paez
 * @version 2011-01-18
 */

ini_set('include_path', '.:'.realpath(dirname(__FILE__).'/../lib'));
ini_set('memory_limit', '128M');
error_reporting(E_ALL | E_STRICT);

require_once('conf.php');
require_once('regatta/Regatta.php');

foreach (DB::getAll(DB::$REGATTA) as $reg) {
  $reg->setData();
  printf("%4d: %s\n", $reg->id, $reg->name);
}
printf("\nPeak memory usage: %s\n", memory_get_peak_usage());
?>
