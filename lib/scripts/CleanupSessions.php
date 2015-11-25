<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

use \scripts\AbstractScript;

/**
 * Erase sessions that have expired
 *
 * @author Dayan Paez
 * @created 2013-10-30
 */
class CleanupSessions extends AbstractScript {
  public function run() {
    require_once('TSSessionHandler.php');
    TSSessionHandler::gc(TSSessionHandler::IDLE_TIME);
    self::errln("Removed expired sessions.");
  }
}
// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new CleanupSessions();
  $opts = $P->getOpts($argv);

  foreach ($opts as $opt) {
    throw new TSScriptException("Invalid argument: $opt");
  }
  $P->run();
}
?>