<?php
/**
 * Cache the URLs for existing public regattas
 *
 * @author Dayan Paez
 * @created 2013-10-02
 */

require_once(dirname(__DIR__) . '/lib/conf.php');
require_once('regatta/Regatta.php');

$upd = 0;
foreach (DB::getAll(DB::$REGATTA, new DBCond('private', null)) as $i => $reg) {
  $reg->setPublicPages($reg->calculatePublicPages());

  if ($i > 0 && $i % 50 == 0)
    printf("%s\n", $i);
  $upd++;
}
printf("\nUpdated %d regattas.\n", $upd);
?>