<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

require_once('AbstractScript.php');

/**
 * Super script to generate entire public HTML site. Not to be run at
 * any old time, unless you NICE it up quite...nicely
 *
 * @author Dayan Paez
 * @version 2011-10-17
 * @package scripts
 */
class GenerateSite extends AbstractScript {

  const REGATTAS = 1;
  const SEASONS  = 2;
  const SCHOOLS  = 4;
  const E404     = 8;
  const SCHOOL_SUMMARY = 16;
  const FRONT    = 32;
  const BURGEES  = 64;
  const ALL      = 127;

  /**
   * Generate the codez
   *
   */
  public function run($do = self::ALL) {
    require_once('regatta/PublicDB.php');
    require_once('xml5/TS.php');

    $seasons = DB::getAll(DB::$SEASON);
    if ($do & self::REGATTAS) {
      // Go through all the regattas
      require_once('UpdateRegatta.php');
      self::errln("* Generating regattas");

      foreach ($seasons as $season) {
        self::errln(sprintf("  - %s", $season->fullString()));
        foreach ($season->getRegattas() as $reg) {
          if (count($reg->getDivisions()) > 0) {
            self::err(sprintf("    - (%4d) %s...", $reg->id, $reg->name));
            UpdateRegatta::run($reg, array(UpdateRequest::ACTIVITY_DETAILS));
            self::errln("done");
          }
        }
      }
    }

    if ($do & self::SCHOOLS) {
      // Schools
      self::errln("* Generating schools");
      require_once('UpdateSchool.php');

      foreach (DB::getConferences() as $conf) {
        self::errln(sprintf("  - Conference: %s", $conf));
        foreach ($conf->getSchools() as $school) {
          self::errln(sprintf("    - School: (%8s) %s", $school->id, $school));
          foreach ($seasons as $season) {
            UpdateSchool::run($school, $season);
            self::errln(sprintf("      - %s", $season->fullString()));
          }
        }
      }
    }

    if ($do & self::BURGEES) {
      // Schools
      self::errln("* Generating burgees");
      require_once('UpdateBurgee.php');
      $P = new UpdateBurgee();
      foreach (DB::getAll(DB::$SCHOOL) as $school) {
        $P->run($school);
        self::errln(sprintf("      - %s", $school));
      }
    }

    if ($do & self::SEASONS) {
      // Go through all the seasons
      self::errln("* Generating seasons");
      require_once('UpdateSeason.php');
      foreach ($seasons as $season) {
        UpdateSeason::run($season);
        self::errln(sprintf("  - %s", $season->fullString()));
      }
      // Also season summary
      require_once('UpdateSeasonsSummary.php');
      UpdateSeasonsSummary::run();
      self::errln("  - Seasons summary");
    }

    if ($do & self::SCHOOL_SUMMARY) {
      // School summary page
      require_once('UpdateSchoolsSummary.php');
      UpdateSchoolsSummary::run();
      self::errln("* Generated schools summary page");
    }

    if ($do & self::E404) {
      // 404 page
      require_once('Update404.php');
      $P = new Update404();
      $P->run(true, true);
      self::errln("* Generated 404 pages");
    }

    if ($do & self::FRONT) {
      // Front page!
      require_once('UpdateFront.php');
      $P = new UpdateFront();
      $P->run();
      self::errln("* Generated front page");
    }
  }

  // ------------------------------------------------------------
  // CLI API
  // ------------------------------------------------------------

  protected $cli_opts = '[-RSC4MFA]';
  protected $cli_usage = ' -R  Generate regattas
 -S  Generate seasons
 -C  Generate schools (C as in college)
 -B  Generate burgees
 -M  Generate schools summary page
 -4  Generate 404 page
 -F  Generate front page (consider using UpdateFront if only desired update)

 -A  Generate ALL';
}

// Run from the command line
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new GenerateSite();
  $opts = $P->getOpts($argv);

  if (isset($opts['A'])) {
    $P->run();
    exit(0);
  }

  $do = 0;
  foreach ($opts as $opt) {
    switch ($opt) {
    case '-R': $do |= GenerateSite::REGATTAS; break;
    case '-S': $do |= GenerateSite::SEASONS; break;
    case '-C': $do |= GenerateSite::SCHOOLS; break;
    case '-M': $do |= GenerateSite::SCHOOL_SUMMARY; break;
    case '-F': $do |= GenerateSite::FRONT; break;
    case '-4': $do |= GenerateSite::E404; break;
    case '-B': $do |= GenerateSite::BURGEES; break;
    default:
      throw new TSScriptException("Invalid option provided: $opt");
    }
  }
  $P->run($do);
}
?>
