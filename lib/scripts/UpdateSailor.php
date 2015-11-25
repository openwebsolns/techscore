<?php
namespace scripts;

use \DB;
use \Member;
use \SailorPage;
use \TSScriptException;

/**
 * Updates the given sailor's profile, given as an argument
 *
 * @author Dayan Paez
 * @created 2015-01-18
 */
class UpdateSailor extends AbstractScript {

  private function getPage(Member $sailor) {
    require_once('public/SailorPage.php');

    $page = new SailorPage($sailor);
    return $page;
  }
  
  /**
   * Creates the given season summary for the given sailor
   *
   * @param Member $sailor the sailor whose summary to generate
   */
  public function run(Member $sailor) {
    $dirname = $sailor->getURL();
    if ($dirname == null) {
      self::errln("Skipping summary for $sailor with no URL.", 2);
      return;
    }

    $filename = $dirname . 'index.html';
    $content = $this->getPage($sailor);
    self::write($filename, $content);
    self::errln("Wrote summary for $sailor.", 2);
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);

    // Validate inputs
    if (count($opts) == 0) {
      throw new TSScriptException("No sailor ID provided");
    }
    $id = array_shift($opts);
    if (($sailor = DB::getSailor($id)) === null) {
      throw new TSScriptException("Invalid sailor ID provided: $id");
    }
    if ($sailor->getURL() === null) {
      throw new TSScriptException("Sailor $sailor does not have a URL");
    }
    if (DB::g(STN::SAILOR_PROFILES) === null) {
      throw new TSScriptException("Feature has been disabled.");
    }

    if (count($opts) > 0) {
      throw new TSScriptException("Invalid argument provided");
    }

    $this->run($sailor);
  }

  protected $cli_opts = '<sailor_id>';
  protected $cli_usage = " <sailor_id>  the ID of the sailor to update";
}
