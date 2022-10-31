<?php
namespace scripts;

use \Account;
use \Conf;
use \DB;
use \Outbox;
use \RuntimeException;
use \School;
use \Season;
use \TSScriptException;

/**
 * This script, to be run from the command line as part of a scheduled
 * task, will pause email sending to accounts whose email messages have bounced.
 *
 * @author Dayan Paez
 * @version 2022-10-31
 * @package scripts
 */
class ProcessBouncedEmails extends AbstractScript {

  private $sent = 0;

  /**
   * Send queued messages
   *
   */
  public function run() {
    if (Conf::$EMAIL_BOUNCE_HANDLER === null) {
      self::errln("No bounce handler configured.");
      return;
    }

    foreach (Conf::$EMAIL_BOUNCE_HANDLER->handle() as $account) {
      self::out(sprintf('%s (%s)', $account->getName(), $account->email));
    }
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    if (count($opts) > 0) {
      throw new TSScriptException("Invalid argument");
    }
    $this->run();
  }
}
