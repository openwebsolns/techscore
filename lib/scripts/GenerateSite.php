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
  const CONFERENCES = 128;
  const SAILORS  = 256;
  const FILES    = 512;
  const ALL      = 1023;

  /**
   * Generate the codez
   *
   */
  public function run($do = self::ALL) {
    require_once('xml5/TS.php');

    $seasons = DB::getAll(DB::T(DB::SEASON));
    if ($do & self::REGATTAS) {
      // Go through all the regattas
      require_once('UpdateRegatta.php');
      $P = new UpdateRegatta();
      self::errln("* Generating regattas");

      foreach ($seasons as $season) {
        self::errln(sprintf("  - %s", $season->fullString()));
        foreach ($season->getRegattas() as $reg) {
          if (count($reg->getDivisions()) > 0) {
            self::err(sprintf("    - (%4d) %s...", $reg->id, $reg->name));
            $P->run($reg, array(UpdateRequest::ACTIVITY_DETAILS));
            self::errln("done");
          }
        }
      }
    }

    $conferences = DB::getConferences();
    if ($do & self::SCHOOLS) {
      // Schools
      self::errln("* Generating schools");
      require_once('UpdateSchool.php');
      $P = new UpdateSchool();

      $andRosters = (DB::g(STN::SAILOR_PROFILES) !== null);
      foreach ($conferences as $conf) {
        self::errln(sprintf("  - %s: %s", DB::g(STN::CONFERENCE_TITLE), $conf));
        foreach ($conf->getSchools() as $school) {
          self::errln(sprintf("    - School: (%8s) %s", $school->id, $school));
          foreach ($seasons as $season) {
            $P->run($school, $season);
            if ($andRosters) {
              $P->runRoster($school, $season);
            }
            self::errln(sprintf("      - %s", $season->fullString()));
          }
        }
      }
    }

    if ($do & self::CONFERENCES && DB::g(STN::PUBLISH_CONFERENCE_SUMMARY)) {
      // Conferences
      self::errln("* Generating conferences");
      require_once('UpdateConference.php');
      $P = new UpdateConference();

      foreach ($conferences as $conf) {
        self::errln(sprintf("  - %s: %s", DB::g(STN::CONFERENCE_TITLE), $conf));
        foreach ($seasons as $season) {
          $P->run($conf, $season);
          self::errln(sprintf("    - %s", $season->fullString()));
        }
      }
    }

    if ($do & self::SAILORS && DB::g(STN::SAILOR_PROFILES)) {
      // Sailors
      self::errln("* Generating sailors");
      require_once('UpdateSailor.php');
      $P = new UpdateSailor();

      foreach ($conferences as $conf) {
        self::errln(sprintf("  - %s: %s", DB::g(STN::CONFERENCE_TITLE), $conf));
        foreach ($conf->getSchools() as $school) {
          self::errln(sprintf("    - School: (%8s) %s", $school->id, $school));
          foreach ($seasons as $season) {
            self::errln(sprintf("      - Season: %s", $season->fullString()));
            foreach ($school->getSailorsInSeason($season) as $sailor) {
              if ($sailor->getURL() !== null) {
                $P->run($sailor, $season);
                self::errln(sprintf("        - %s", $sailor));
              }
            }
          }
        }
      }
    }

    if ($do & self::BURGEES) {
      // Schools
      self::errln("* Generating burgees");
      require_once('UpdateBurgee.php');
      $P = new UpdateBurgee();
      foreach (DB::getSchools() as $school) {
        $P->run($school);
        self::errln(sprintf("      - %s", $school));
      }
    }

    if ($do & self::SEASONS) {
      // Go through all the seasons
      self::errln("* Generating seasons");
      require_once('UpdateSeason.php');
      $P = new UpdateSeason();
      foreach ($seasons as $season) {
        $P->run($season);
        self::errln(sprintf("  - %s", $season->fullString()));
      }
      // Also season summary
      require_once('UpdateSeasonsSummary.php');
      $P = new UpdateSeasonsSummary();
      $P->run();
      self::errln("  - Seasons summary");
    }

    if ($do & self::SCHOOL_SUMMARY) {
      // School summary page
      require_once('UpdateSchoolsSummary.php');
      $P = new UpdateSchoolsSummary();
      $P->run();
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

    if ($do & self::FILES) {
      // Files
      require_once('UpdateFile.php');
      $P = new UpdateFile();
      foreach (DB::getAll(DB::T(DB::PUB_FILE_SUMMARY)) as $file) {
        $P->run($file->id);
      }
      $P->runInitJs();
    }
  }

  // ------------------------------------------------------------
  // CLI API
  // ------------------------------------------------------------

  protected $cli_opts = '[-RSCPM4FG] [-A]';
  protected $cli_usage = ' -R  Generate regattas
 -S  Generate seasons
 -C  Generate schools (C as in college and confusing)
 -D  Generate conferences (D as in district) NB: only if allowed
 -B  Generate burgees
 -P  Generate sailors (P for participants; if available)
 -M  Generate schools summary page
 -4  Generate 404 page
 -F  Generate front page (consider using UpdateFront if only desired update)
 -G  Generate files

 -A  Generate ALL';
}

// Run from the command line
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new GenerateSite();
  $opts = $P->getOpts($argv);

  $do = 0;
  foreach ($opts as $opt) {
    if ($opt == '-A') {
      $do = GenerateSite::ALL; 
      break;
    }
    switch ($opt) {
    case '-R': $do |= GenerateSite::REGATTAS; break;
    case '-S': $do |= GenerateSite::SEASONS; break;
    case '-C': $do |= GenerateSite::SCHOOLS; break;
    case '-D': $do |= GenerateSite::CONFERENCES; break;
    case '-P': $do |= GenerateSite::SAILORS; break;
    case '-M': $do |= GenerateSite::SCHOOL_SUMMARY; break;
    case '-F': $do |= GenerateSite::FRONT; break;
    case '-4': $do |= GenerateSite::E404; break;
    case '-B': $do |= GenerateSite::BURGEES; break;
    case '-G': $do |= GenerateSite::FILES; break;
    default:
      throw new TSScriptException("Invalid option provided: $opt");
    }
  }
  $P->run($do);
}
?>
