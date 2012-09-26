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
ini_set('output_buffering', '16384');
error_reporting(E_ALL | E_STRICT);

require_once('conf.php');

array_shift($argv);
foreach ($argv as $id) {
  try {
    $reg = DB::getRegatta($id);
    try {
      if ($reg->type != Regatta::TYPE_PERSONAL) {
        UpdateRegatta::sync($reg);
        UpdateRegatta::syncTeams($reg);
        UpdateRegatta::syncRP($reg);
        printf("(%3d) Imported regatta %s\n", $reg->id, $reg->name);
      }
    }
    catch (Exception $e) {
      printf("(%3d) ERROR (%s): %s\n", $reg->id, $reg->name, $e->getMessage());
    }
  }
  catch (Exception $e) {
    printf("(%3d) ERROR (does it exist?)\n", $id);
  }
}
printf("\nPeak memory usage: %s\n",
       memory_get_peak_usage());
?>
