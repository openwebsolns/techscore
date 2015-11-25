<?php
namespace scripts;

use \xml5\TPublic404Page;

/**
 * Creates a custom 404 page with a brief sitemap of the site
 *
 */
class Update404 extends AbstractScript {

  private static $urlsByMode = array(
    TPublic404Page::MODE_GENERAL => '/404.html',
    TPublic404Page::MODE_SCHOOL => '/schools/404.html',
  );

  private function schoolsPage() {
    return new TPublic404Page(TPublic404Page::MODE_SCHOOL);
  }

  private function generalPage() {
    return new TPublic404Page(TPublic404Page::MODE_GENERAL);
  }

  /**
   * Creates the new page summary in the public domain
   *
   */
  public function run($mode) {
    if (!array_key_exists($mode, self::$urlsByMode)) {
      throw new TSScriptException("Invalid mode specified: $mode.");
    }
    self::write(
      self::$urlsByMode[$mode],
      new TPublic404Page($mode)
    );
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    $modes = array();
    foreach ($opts as $opt) {
      if (array_key_exists($opt, self::$urlsByMode)) {
        $modes[$opt] = $opt;
      }
      else {
        throw new TSScriptException("Invalid argument: $opt");
      }
    }
    if (count($modes) == 0) {
      throw new TSScriptException("No modes specified.");
    }
    foreach ($modes as $mode) {
      $this->run($mode);
    }
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------

  public function __construct() {
    parent::__construct();
    $this->cli_opts = implode(' | ', array_keys(self::$urlsByMode));
    $this->cli_usage = "Choose one or more of the possible arguments";
  }

}
