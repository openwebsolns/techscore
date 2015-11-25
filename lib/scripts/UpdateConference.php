<?php
namespace scripts;

use \xml5\ConferencePage;

use \DB;
use \Conference;
use \InvalidArgumentException;
use \Season;
use \TSScriptException;

/**
 * Update the given conference page, given as an argument
 *
 * @author Dayan Paez
 * @version 2014-06-23
 * @package scripts
 */
class UpdateConference extends AbstractScript {

  /**
   * Creates the given season summary for the given conference
   *
   * @param Conference $conference the conference whose summary to generate
   * @param Season $season the season
   * @throws InvalidArgumentException
   */
  public function run(Conference $conference, Season $season) {
    $dirname = $conference->getURL();
    if ($dirname === null) {
      throw new InvalidArgumentException(sprintf("Conference %s does not have a URL.", $conference));
    }

    // Do season
    $today = Season::forDate(DB::T(DB::NOW));
    $base = (string) $season;

    // Create season directory
    $fullname = $dirname . $base;

    // is this current season
    $current = false;
    if ((string) $today == (string) $season) {
      $current = true;
    }

    $filename = "$fullname/index.html";
    $content = new ConferencePage($conference, $season);
    self::write($filename, $content);
    self::errln("Wrote season $season summary for $conference.", 2);
    
    // If current, do we also need to create index page?
    if ($current) {
      $filename = $dirname . 'index.html';
      self::write($filename, $content);
      self::errln("Wrote current summary for $conference.", 2);
    }
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);

    // Validate inputs
    if (count($opts) == 0) {
      throw new TSScriptException("No conference ID provided");
    }
    $id = array_shift($opts);
    if (($conference = DB::getConference($id)) === null) {
      throw new TSScriptException("Invalid conference ID provided: $id");
    }

    // Season
    if (count($opts) > 1) {
      throw new TSScriptException("Invalid argument provided");
    }
    $season = Season::forDate(DB::T(DB::NOW));
    if (count($opts) > 0) {
      $id = array_shift($opts);
      if (($season = DB::getSeason($id)) === null) {
        throw new TSScriptException("Invalid season provided: $id");
      }
    }
    if ($season === null) {
      throw new TSScriptException("No current season exists");
    }

    $this->run($conference, $season);
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------

  protected $cli_opts = '<conference_id> [season]';
  protected $cli_usage = " <conference_id>  the ID of the conference to update
 season       (optional) the season to update (defaults to current)";
}
