<?php
namespace scripts;

use \pub\SchoolsSummaryReportMaker;

use \DB;
use \STN;
use \TSScriptException;

/**
 * The page that summarizes the schools in the system.
 *
 * 2014-06-23: Use this page for the conference summary page
 *
 * @author Dayan Paez
 * @version 2011-02-08
 * @package www
 */
class UpdateSchoolsSummary extends AbstractScript {

  /**
   * Creates all versions of schools summary pages
   *
   */
  public function run() {
    $this->runSchools();
    $this->runConferences();
    $this->runSailors();
  }

  /**
   * Creates and writes the page to file
   *
   */
  public function runSchools() {
    $maker = $this->getSchoolsSummaryReportMaker();
    $f = '/schools/index.html';
    $page = $maker->getSchoolsPage();
    self::write($f, $page);
    self::errln("Wrote schools summary page");
  }

  /**
   * Creates the conference summary page only
   *
   */
  public function runConferences() {
    $maker = $this->getSchoolsSummaryReportMaker();
    $f = sprintf('/%s/index.html', DB::g(STN::CONFERENCE_URL));
    $page = $maker->getConferencesPage();
    self::write($f, $page);
    self::errln(sprintf("Wrote %ss summary page", DB::g(STN::CONFERENCE_TITLE)));
  }

  /**
   * Creates the sailor summary page only
   *
   */
  public function runSailors() {
    if (!$this->shouldRunSailors()) {
      self::errln("Sailor profile feature disabled.");
      return;
    }

    $maker = $this->getSchoolsSummaryReportMaker();
    $f = '/sailors/index.html';
    $page = $maker->getSailorsPage();
    self::write($f, $page);
    self::errln("Wrote sailors summary page");
  }

  public function __construct() {
    parent::__construct();
    $this->cli_opts = '[schools] [conferences] [sailors]';
    $this->cli_usage = sprintf('
 schools      write /schools/ URL
 conferences  write /%s/ URL
 sailors      write /sailors/ URL',
                               DB::g(STN::CONFERENCE_URL));
  }

  public function setSchoolSummaryReportMaker(SchoolsSummaryReportMaker $maker) {
    $this->maker = $maker;
  }

  private function getSchoolsSummaryReportMaker() {
    if ($this->maker === null) {
      $this->maker = new SchoolsSummaryReportMaker();
    }
    return $this->maker;
  }

  public function setRunSailors($flag) {
    $this->shouldRunSailors = ($flag !== false);
  }

  private function shouldRunSailors() {
    if ($this->shouldRunSailors === null) {
      $this->shouldRunSailors = DB::g(STN::SAILOR_PROFILES) !== null;
    }
    return $this->shouldRunSailors;
  }

  private $maker;
  private $shouldRunSailors;

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    $bitmask = 0;
    while (count($opts) > 0) {
      $arg = array_shift($opts);
      switch ($arg) {
      case 'schools':
        $bitmask |= 1;
        break;

      case 'conferences':
        $bitmask |= 2;
        break;

      case 'sailors':
        $bitmask |= 4;
        break;

      default:
        throw new TSScriptException("Invalid argument: " . $arg);
      }
    }

    if ($bitmask === 0) {
      $this->run();
    }
    if ($bitmask & 1) {
      $this->runSchools();
    }
    if ($bitmask & 2) {
      $this->runConferences();
    }
    if ($bitmask & 4) {
      $this->runSailors();
    }
  }
}
