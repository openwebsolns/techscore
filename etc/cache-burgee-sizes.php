<?php
/**
 * Store the size of the burgees
 *
 * @author Dayan Paez
 * @created 2013-10-04
 */

require_once(dirname(__DIR__) . '/lib/conf.php');
require_once('regatta/Regatta.php');

$tmp = '/tmp/burgee.png';

$upd = 0;
foreach (DB::getAll(DB::$BURGEE) as $file) {
  file_put_contents($tmp, base64_decode($file->filedata));
  $props = getimagesize($tmp);
  if ($props === false)
    printf("(EE) Unable to get size for file %s.\n", $file->id);
  else {
    $file->width = $props[0];
    $file->height = $props[1];
    DB::set($file, true);
    $upd++;
  }
}
printf("Updated %d burgees.", $upd);
?>
