<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package scripts
 */

require_once('AbstractScript.php');

/**
 * Merges unregistered sailors automatically.
 *
 * The following criteria are used:
 *
 *  - first_name (insensitive)
 *  - last_name (insensitive)
 *  - year
 *  - school
 *  - gender (optional)
 *
 * @author Dayan Paez
 * @created 2014-09-14
 */
class MergeUnregisteredSailors extends AbstractScript {

  private $dry_run = false;
  private $use_gender = false;

  /**
   * Sets dry run flag
   *
   * @param boolean $flag true to turn on
   */
  public function setDryRun($flag = false) {
    $this->dry_run = ($flag !== false);
  }

  /**
   * Set whether to ues gender in criteria
   *
   * @param boolean $flag true to turn on
   */
  public function useGender($flag = false) {
    $this->use_gender = ($flag !== false);
  }

  /**
   * Entry point of application.
   *
   * Runs a merge operation, if the feature is so enabled. It creates
   * one merge log for the entire operation.
   *
   * @param Array:School the list of schools
   */
  public function run($schools) {
    // Allowed?
    if (DB::g(STN::AUTO_MERGE_SAILORS) === null) {
      self::errln("Auto-merging is not allowed.");
      return;
    }

    // Create log
    $log = new Merge_Log();
    $log->started_at = DB::$NOW;
    $log->error = 'Interrupted';
    DB::set($log);

    foreach ($schools as $school) {
      $this->runSchool($school, $log);
    }

    $log->error = null;
    $log->ended_at = DB::$NOW;
    DB::set($log);
  }

  /**
   * Automatically merge sailors from given school
   *
   * @param School $school
   * @param Merge_Log $log optional log in which to record changes
   */
  public function runSchool(School $school, Merge_Log $log = null) {
    // Fetch all would be unregistered sailors
    $registered = array();
    foreach ($school->getSailors() as $sailor)
      $registered[] = $sailor;

    if (count($registered) == 0) {
      self::errln("No registered sailors for " . $school);
      return;
    }

    $unregistered = $school->getUnregisteredSailors();
    if (count($unregistered) == 0) {
      self::errln("No unregistered sailors for " . $school);
      return;
    }

    foreach ($unregistered as $sailor) {
      self::err(sprintf("Testing unregistered: %s (%d)...", $sailor, $sailor->id), 2);
      $replacement = $this->matchSailor($sailor, $registered);
      if ($replacement !== null) {
        self::errln(sprintf("Replacing with %s (%d)", $replacement, $replacement->id));
        if (!$this->dry_run) {
          $affected = array();
          RpManager::replaceTempActual($sailor, $replacement, true, $affected);

          if ($log !== null) {
            $sailor_log = $this->newMergeSailorLog($sailor, $replacement, $log);
            DB::set($sailor_log);
            foreach ($affected as $regatta) {
              $entry = new Merge_Regatta_Log();
              $entry->merge_sailor_log = $sailor_log;
              $entry->regatta = $regatta;
              DB::set($entry);
              self::errln(sprintf("Changed entry for regatta %s, %s", $regatta->getSeason(), $regatta->name), 3);
            }
          }
        }
      }
      else {
        self::errln("No replacement found.", 2);
      }
    }
  }

  /**
   * Create (without persisting) Merge_Sailor_Log
   *
   * @param Sailor $unregistered the original sailor
   * @param Sailor $replacement the replacement
   * @param Merge_Log $log the parent log
   * @return Merge_Sailor_Log
   */
  private function newMergeSailorLog(Sailor $unregistered, Sailor $replacement, Merge_Log $log) {
    $entry = new Merge_Sailor_Log();
    $entry->merge_log = $log;
    $entry->school = $unregistered->school;
    $entry->first_name = $unregistered->first_name;
    $entry->last_name = $unregistered->last_name;
    $entry->year = $unregistered->year;
    $entry->gender = $unregistered->gender;
    $entry->regatta_added = $unregistered->regatta_added;
    $entry->registered_sailor = $replacement;
    return $entry;
  }

  /**
   * Find the sailor in the given list, according to parameters
   *
   * The match will be performed based on first_name, last_name,
   * school, year, and (optionally) gender.
   *
   * @param Sailor $needle the sailor to search
   * @param Array:Sailor $haystack the list of sailors to match
   * @return Sailor|null the matched sailor from list, if any
   */
  private function matchSailor(Sailor $needle, Array $haystack) {
    $fn = strtolower($needle->first_name);
    $ln = strtolower($needle->last_name);
    foreach ($haystack as $other) {
      self::err(sprintf("Testing %s...", $other), 3);
      if (strtolower($other->first_name) == $fn &&
          strtolower($other->last_name) == $ln &&
          $other->school == $needle->school &&
          ($needle->year === null || $other->year == $needle->year) &&
          (!$this->use_gender || $other->gender == $needle->gender)) {

        self::errln("match found!", 3);
        return $other;
      }
      self::errln("no match", 3);
    }
    return null;
  }

  protected $cli_opts = '[--gender] [-n] [school_id, ...]';
  protected $cli_usage = ' --gender        Include gender in criteria
  -n, --dry-run  Do not perform merge

Specify one or more school IDs to update, or blank for all.';
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');
  require_once('regatta/MergeLog.php');


  $P = new MergeUnregisteredSailors();
  $opts = $P->getOpts($argv);
  $schools = array();
  foreach ($opts as $opt) {
    if ($opt == '-n' || $opt == '--dry-run')
      $P->setDryRun(true);
    elseif ($opt == '--gender')
      $P->useGender(true);
    else {
      $school = DB::getSchool($opt);
      if ($school === null)
        throw new TSScriptException("Invalid school ID provided: $opt");
      $schools[] = $school;
    }
  }
  if (count($schools) == 0)
    $schools = DB::getAll(DB::$SCHOOL);

  $P->run($schools);
}
?>