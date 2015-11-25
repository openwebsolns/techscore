<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

use \scripts\AbstractScript;

/**
 * Updates internal cache of Twitter settings, such as URL length
 *
 * @author Dayan Paez
 * @created 2013-09-16
 */
class TwitterCheck extends AbstractScript {

  public function run() {
    if (DB::g(STN::TWITTER_CONSUMER_KEY) === null ||
        DB::g(STN::TWITTER_CONSUMER_SECRET) === null ||
        DB::g(STN::TWITTER_OAUTH_TOKEN) === null ||
        DB::g(STN::TWITTER_OAUTH_SECRET) === null) {
      self::errln("Twitter is not enabled.");
      return;
    }

    require_once('twitter/TwitterWriter.php');

    $writer = new TwitterWriter(DB::g(STN::TWITTER_CONSUMER_KEY),
                                DB::g(STN::TWITTER_CONSUMER_SECRET),
                                DB::g(STN::TWITTER_OAUTH_TOKEN),
                                DB::g(STN::TWITTER_OAUTH_SECRET));
    $cfg = $writer->checkConfig();
    DB::s(STN::TWITTER_URL_LENGTH, $cfg['short_url_length']);
    self::errln(sprintf("Set the Twitter URL length to %d.", $cfg['short_url_length']));
  }
}

// Run from the command line
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new TwitterCheck();
  $opts = $P->getOpts($argv);
  if (count($opts) > 0)
    throw new TSScriptException("Invalid argument");
  $P->run();
}
?>