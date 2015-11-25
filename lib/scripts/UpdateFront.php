<?php
namespace scripts;

use \xml5\TPublicFrontPage;
use \TSScriptException;

/**
 * Creates the front page: Includes a brief welcoming message,
 * displayed alongside the list of regattas sailing now, if any. (The
 * welcome message takes up the entire width otherwise).
 *
 * Underneath that, includes a list of upcoming events, in ascending
 * chronological order.
 *
 */
class UpdateFront extends AbstractScript {

  /**
   * Creates the new page summary in the public domain
   *
   */
  public function run() {
    self::write('/index.html', new TPublicFrontPage());
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    if (count($opts) > 0)
      throw new TSScriptException("Invalid argument(s).");
    $this->run();
  }
}
