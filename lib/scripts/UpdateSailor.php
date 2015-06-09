<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

require_once('AbstractScript.php');

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

    $filename = $dirname . 'index.html';
    $content = $this->getPage($sailor);
    self::write($filename, $content);
    self::errln("Wrote summary for $sailor.", 2);
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------

  protected $cli_opts = '<sailor_id>';
  protected $cli_usage = " <sailor_id>  the ID of the sailor to update";
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateSailor();
  $opts = $P->getOpts($argv);

  // Validate inputs
  if (count($opts) == 0)
    throw new TSScriptException("No sailor ID provided");
  $id = array_shift($opts);
  if (($sailor = DB::getSailor($id)) === null)
    throw new TSScriptException("Invalid sailor ID provided: $id");
  if ($sailor->getURL() === null)
    throw new TSScriptException("Sailor $sailor does not have a URL");
  if (DB::g(STN::SAILOR_PROFILES) === null)
    throw new TSScriptException("Feature has been disabled.");

  // Season
  if (count($opts) > 0)
    throw new TSScriptException("Invalid argument provided");

  $P->run($sailor);
}
?>