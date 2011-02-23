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

$_SERVER['HTTP_HOST'] = 'cli';
require_once('conf.php');

$con = Preferences::getConnection();
// get all finalized, non-personal regattas and go to town!
$res = $con->query('select id from regatta where type <> "personal"');
$lst = array();
while ($obj = $res->fetch_object()) {
  $lst[] = $obj->id;
}
$res->free();

foreach ($lst as $id) {
  try {
    $reg = new Regatta($id);
    UpdateRegatta::run($reg, UpdateRequest::ACTIVITY_ROTATION);
    UpdateRegatta::run($reg, UpdateRequest::ACTIVITY_SCORE);
    printf("(%3d) Imported regatta %s\n", $reg->id(), $reg->get(Regatta::NAME));
  }
  catch (Exception $e) {
    printf("(%3d) ERROR regatta %s\n", $reg->id(), $reg->get(Regatta::NAME)); 
  }
}
printf("\nPeak memory usage: %s\n",
       memory_get_peak_usage());
?>
