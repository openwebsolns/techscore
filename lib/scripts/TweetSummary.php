<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

use \scripts\AbstractScript;

/**
 * Used from a CRON task to send updates to Twitter
 *
 * @author Dayan Paez
 * @created 2013-09-16
 */
class TweetSummary extends AbstractScript {

  private $dry_run = false;

  public function setDryRun($flag = true) {
    $this->dry_run = ($flag !== false);
  }

  public function run($event) {
    if (DB::g(STN::TWITTER_CONSUMER_KEY) === null ||
        DB::g(STN::TWITTER_CONSUMER_SECRET) === null ||
        DB::g(STN::TWITTER_OAUTH_TOKEN) === null ||
        DB::g(STN::TWITTER_OAUTH_SECRET) === null) {
      self::errln("Twitter is not enabled.");
      return;
    }

    require_once('twitter/TweetFactory.php');
    $factory = new TweetFactory();
    try {
      $mes = $factory->create($event);
      if ($mes === null) {
        self::errln("No tweet to send.");
        return;
      }

      $pre = "DRY-RUN Tweet: ";
      if (!$this->dry_run) {
        $pre = "Tweet: ";
        require_once('twitter/TwitterWriter.php');

        $writer = new TwitterWriter(DB::g(STN::TWITTER_CONSUMER_KEY),
                                    DB::g(STN::TWITTER_CONSUMER_SECRET),
                                    DB::g(STN::TWITTER_OAUTH_TOKEN),
                                    DB::g(STN::TWITTER_OAUTH_SECRET));
        $writer->tweet($mes);
      }
      self::errln($pre . $mes);
    } catch (InvalidArgumentException $e) {
      throw new TSScriptException($e->getMessage());
    }
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------
  public function __construct() {
    parent::__construct();
    $this->cli_opts = '[-n] <event>';
    $this->cli_usage = "  -n, --dry-run   Do not actually tweet

<event> must be one of the appropriate constants from TweetFactory.
";
  }
}

// Run from the command line
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new TweetSummary();
  $opts = $P->getOpts($argv);

  $evt = null;
  while (count($opts) > 0) {
    $arg = array_shift($opts);
    switch ($arg) {
    case '-n':
    case '--dry-run':
      $P->setDryRun(true);
      break;

    default:
      if ($evt !== null)
        throw new TSScriptException("Only one event allowed at a time.");
      $evt = $arg;
    }
  }
  if ($evt === null)
    throw new TSScriptException("No event provided.");
  $P->run($evt);
}
?>