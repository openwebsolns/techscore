<?php
namespace scripts;

use \TSScriptException;
use \TSSessionHandler;

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
  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    foreach ($opts as $opt) {
      throw new TSScriptException("Invalid argument: $opt");
    }
    $this->run();
  }
}
