<?php
/**
 * Super script to generate entire public HTML site. Not to be run at
 * any old time, unless you NICE it up quite...nicely
 *
 * @author Dayan Paez
 * @version 2011-10-17
 * @package scripts
 */
class GenerateSite {

  /**
   * @var boolean true to print out information about what's happening
   */
  public static $verbose = false;

  const REGATTAS = 1;
  const SEASONS  = 2;
  const SCHOOLS  = 4;
  const E404     = 8;
  const SCHOOL_SUMMARY = 16;
  const FRONT    = 32;
  const ALL      = 63;

  /**
   * Generate the codez
   *
   */
  public static function run($do = self::ALL) {
    require_once('regatta/PublicDB.php');
    require_once('xml5/TS.php');

    if ($do & self::SCHOOLS) {
      // Schools
      self::log("\n* Generating schools\n");
      require_once('UpdateSchool.php');
      require_once('UpdateBurgee.php');
      
      if ($seasons === null)
	$seasons = self::getSeasons();
      
      foreach (DB::getConferences() as $conf) {
	self::log(sprintf("  - Conference: %s\n", $conf));
	foreach ($conf->getSchools() as $school) {
	  self::log(sprintf("    - School: (%8s) %s\n", $school->id, $school));
	  foreach ($seasons as $season) {
	    UpdateSchool::run($school, $season);
	    self::log(sprintf("      - %s\n", $season->fullString()));
	  }
	  UpdateBurgee::update($school);
	  self::log("      - Updated burgee\n");
	}
      }
    }

    if ($do & self::REGATTAS) {
      // Go through all the regattas
      self::log("* Generating regattas\n\n");
      require_once('UpdateRegatta.php');
      foreach (DB::getAll(DB::$DT_REGATTA) as $reg) {
	UpdateRegatta::run(DB::getRegatta($reg->id), UpdateRequest::ACTIVITY_SCORE);
	self::log(sprintf("  - (%4d) %s\n", $reg->id, $reg->name));
      }
    }

    $seasons = null;
    if ($do & self::SEASONS) {
      // Go through all the seasons
      self::log("\n* Generating seasons\n");
      require_once('UpdateSeason.php');
      $seasons = self::getSeasons();
      foreach ($seasons as $season) {
	UpdateSeason::run($season);
	self::log(sprintf("  - %s\n", $season->fullString()));
      }
    }

    if ($do & self::SCHOOL_SUMMARY) {
      // School summary page
      require_once('UpdateSchoolsSummary.php');
      UpdateSchoolsSummary::run();
      self::log("\n* Generated schools summary page\n");
    }

    if ($do & self::E404) {
      // 404 page
      require_once('Update404.php');
      Update404::run();
      self::log("\n* Generated 404 page\n");
    }

    if ($do & self::FRONT) {
      // Front page!
      require_once('UpdateFront.php');
      UpdateFront::run();
      self::log("\n* Generated front page\n");
    }
  }

  private static function log($mes) {
    if (self::$verbose)
      echo $mes;
  }

  private static function getSeasons() {
    return DB::getAll(DB::$SEASON);
  }

  public static function usage($name = 'GenerateSite') {
    printf("usage: %s [-vhRSC4MFA]

 -h  Print this message
 -v  Be verbose about what you are doing

 -R  Generate regattas
 -S  Generate seasons
 -C  Generate schools (C as in college)
 -M  Generate schools summary page
 -4  Generate 404 page
 -F  Generate front page (consider using UpdateFront if only desired update)
 -A  Generate ALL\n", $name);

  }
}

// Run from the command line
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  ini_set('include_path', '.:'.realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  $opts = getopt('vhRSC4MFA');

  // Help?
  if (isset($opts['h'])) {
    GenerateSite::usage($argv[0]);
    exit(1);
  }
  
  if (isset($opts['v'])) {
    GenerateSite::$verbose = true;
    unset($opts['v']);
  }
  if (isset($opts['A'])) {
    GenerateSite::run();
    exit(0);
  }

  $do = 0;
  foreach ($opts as $opt => $val) {
    switch ($opt) {
    case 'R': $do |= GenerateSite::REGATTAS; break;
    case 'S': $do |= GenerateSite::SEASONS; break;
    case 'C': $do |= GenerateSite::SCHOOLS; break;
    case 'M': $do |= GenerateSite::SCHOOL_SUMMARY; break;
    case 'F': $do |= GenerateSite::FRONT; break;
    case '4': $do |= GenerateSite::E404; break;
    default:
      printf("Invalid option provided: %s\n", $opt);
      GenerateSite::usage($argv[0]);
      exit(2);
    }
  }
  GenerateSite::run($do);
}
?>