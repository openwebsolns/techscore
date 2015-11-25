<?php
namespace scripts;

use \DB;
use \School;
use \SchoolReportMaker;
use \Season;
use \TSScriptException;

/**
 * Update the given school, given as an argument
 *
 * @author Dayan Paez
 * @version 2010-08-27
 * @package scripts
 */
class UpdateSchool extends AbstractScript {

  /**
   * Creates the given season summary for the given school
   *
   * @param School $school the school whose summary to generate
   * @param Season $season the season
   */
  public function run(School $school, Season $season) {
    $dirname = $school->getURL();

    // Do season
    $today = Season::forDate(DB::T(DB::NOW));
    $base = (string)$season;

    // Create season directory
    $fullname = $dirname . $base;

    // is this current season
    $current = false;
    if ((string)$today == (string)$season)
      $current = true;

    require_once('public/SchoolReportMaker.php');
    $maker = new SchoolReportMaker($school, $season);
    $filename = "$fullname/index.html";
    $content = $maker->getMainPage();
    self::write($filename, $content);
    self::errln("Wrote season $season summary for $school.", 2);
    
    // If current, do we also need to create index page?
    if ($current) {
      $filename = $dirname . 'index.html';
      self::write($filename, $content);
      self::errln("Wrote current summary for $school.", 2);
    }
  }

  /**
   * Creates the roster page for the given school in given season
   *
   * @param School $school the school whose summary to generate
   * @param Season $season the season
   */
  public function runRoster(School $school, Season $season) {
    $dirname = $school->getURL();
    $base = (string)$season;

    // Create season directory
    $fullname = $dirname . $base;

    require_once('public/SchoolReportMaker.php');
    $maker = new SchoolReportMaker($school, $season);
    $filename = $fullname . '/roster/index.html';
    $content = $maker->getRosterPage();
    self::write($filename, $content);
    self::errln("Wrote season $season roster for $school.", 2);
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);

    // Validate inputs
    if (count($opts) == 0) {
      throw new TSScriptException("No school ID provided");
    }
    $id = array_shift($opts);
    if (($school = DB::getSchool($id)) === null) {
      throw new TSScriptException("Invalid school ID provided: $id");
    }

    // Season
    $season = null;
    $page_type_chosen = false;
    $pages = array(
      'main' => false,
      'roster' => false,
    );

    while (count($opts) > 0) {
      $opt = array_shift($opts);
      if (array_key_exists($opt, $pages)) {
        $pages[$opt] = true;
        $page_type_chosen = true;

        if ($season === null) {
          $season = Season::forDate(DB::T(DB::NOW));
        }
      } else {
        if ($season !== null) {
          throw new TSScriptException("Invalid argument provided: $opt");
        }
        $season = DB::getSeason($opt);
        if ($season === null) {
          throw new TSScriptException("Invalid season provided: $opt");
        }
      }
    }

    if ($season === null) {
      $season = Season::forDate(DB::T(DB::NOW));
      if ($season === null) {
        throw new TSScriptException("No current season exists, and none provided.");
      }
    }

    if (!$page_type_chosen) {
      $pages['main'] = true;
    }

    if ($pages['main']) {
      $this->run($school, $season);
    }
    if ($pages['roster']) {
      $this->runRoster($school, $season);
    }
  }

  protected $cli_opts = '<school_id> [season] [type]';
  protected $cli_usage = " <school_id>  the ID of the school to update
 season       (optional) the season to update (defaults to current)
 type         (optional, default=main) either: \"main\" or \"roster\"";
}
