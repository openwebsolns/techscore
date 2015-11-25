<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

use \scripts\AbstractScript;

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
    $f = '/schools/index.html';
    $page = $this->maker->getSchoolsPage();
    self::write($f, $page);
    self::errln("Wrote schools summary page");
  }

  /**
   * Creates the conference summary page only
   *
   */
  public function runConferences() {
    $f = sprintf('/%s/index.html', DB::g(STN::CONFERENCE_URL));
    $page = $this->maker->getConferencesPage();
    self::write($f, $page);
    self::errln(sprintf("Wrote %ss summary page", DB::g(STN::CONFERENCE_TITLE)));
  }

  /**
   * Creates the sailor summary page only
   *
   */
  public function runSailors() {
    if (DB::g(STN::SAILOR_PROFILES) === null) {
      self::errln("Sailor profile feature disabled.");
      return;
    }

    $f = '/sailors/index.html';
    $page = $this->maker->getSailorsPage();
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

    require_once('public/SchoolsSummaryReportMaker.php');
    $this->maker = new SchoolsSummaryReportMaker();
  }

  private $maker;
}

if (isset($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateSchoolsSummary();
  $opts = $P->getOpts($argv);
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

  if ($bitmask === 0)
    $P->run();
  if ($bitmask & 1)
    $P->runSchools();
  if ($bitmask & 2)
    $P->runConferences();
  if ($bitmask & 4)
    $P->runSailors();
}
?>
