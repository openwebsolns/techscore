<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package scripts
 */

use \scripts\AbstractScript;

/**
 * Pulls information from the organization database and updates the local
 * sailor database with the data.
 *
 * 2011-09-12: Sets active flag to true
 *
 * @author Dayan Paez
 * @version 2010-03-02
 */
class SyncDB extends AbstractScript {

  /**
   * @var Array Errors encountered
   */
  private $errors;

  /**
   * @var Array Warnings issued
   */
  private $warnings;

  /**
   * @var boolean true to store entries in *_season logs
   */
  private $log_activation;

  /**
   * Creates a new SyncDB object
   *
   */
  public function __construct() {
    parent::__construct();
    $this->errors = array();
    $this->warnings = array();
    $this->log_activation = false;
    $this->cli_opts = '[--log] schools | sailors';
    $this->cli_usage = "Provide at least one argument to run.

 --log   Save activation for each entry.
         This is most suitable when run as a cron task.";
  }

  /**
   * Whether to save an entry for each activated object.
   *
   * @param boolean $flag true (default) to track activity
   */
  public function setLogActivation($flag = true) {
    $this->log_activation = ($flag !== false);
  }

  /**
   * Fetch the errors
   *
   * @return Array<String> error messages
   */
  public function errors() { return $this->errors; }

  /**
   * Updates the information in the database about the given sailor
   *
   * @param Sailor $sailor the sailor
   * @param Sync_Log $log
   * @param Season $season
   */
  private function updateSailor(Member $sailor, Sync_Log $log, Season $season, Array &$used_urls) {
    $sailor->active = 1;
    $cur = DB::getRegisteredSailor($sailor->icsa_id);

    $update = false;
    if ($cur !== null) {
      $sailor->id = $cur->id;
      $sailor->url = $cur->url;
      $update = true;
    }
    else {
      $sailor->sync_log = $log;
    }
    if ($sailor->gender === null)
      $sailor->gender = Sailor::MALE;

    // URL
    $old_url = $sailor->getURL();
    $name = $sailor->getName();
    $seeds = array($name);
    if ($sailor->year > 0) {
      $seeds[] = $name . " " . $sailor->year;
    }
    $seeds[] = $name . " " . $sailor->school->nick_name;
    $url = DB::createUrlSlug(
      $seeds,
      function($slug) use ($used_urls) {
        return !array_key_exists($slug, $used_urls);
      }
    );
    $sailor->url = $url;
    $used_urls[$url] = $url;

    // URL change?
    $new_url = $sailor->getURL();
    if ($sailor instanceof Sailor && DB::g(STN::SAILOR_PROFILES) !== null && $old_url != $new_url) {
      self::errln(sprintf("URL change for sailor %s: %s -> %s", $name, $old_url, $new_url), 3);
      require_once('public/UpdateManager.php');
      UpdateManager::queueSailor($sailor, UpdateSailorRequest::ACTIVITY_URL, $season, $old_url);

      // queue school DETAILS as well, if entirely new URL. This will
      // cause all the seasons to be regenerated, without affecting
      // the regattas.
      if ($old_url === null) {
        UpdateManager::queueSchool($sailor->school, UpdateSailorRequest::ACTIVITY_DETAILS, $season);
      }
    }

    DB::set($sailor, $update);

    // Activate season entry
    if ($this->log_activation) {
      $season_entry = new Sailor_Season();
      $season_entry->season = $season;
      $season_entry->sailor = $sailor;
      DB::set($season_entry);
    }
  }

  /**
   * Sync the schools
   *
   * @param Sync_Log $log the sync log to associate with updated schools
   */
  public function updateSchools(Sync_Log $log) {
    $season = Season::forDate(DB::T(DB::NOW));
    if ($season === null) {
      self::errln("No current season available.");
      return;
    }

    if (strlen(DB::g(STN::SCHOOL_API_URL)) == 0) {
      self::errln("No URL to update schools list.");
      return;
    }

    self::errln("Fetching and parsing schools from " . DB::g(STN::SCHOOL_API_URL));

    if (($xml = @simplexml_load_file(DB::g(STN::SCHOOL_API_URL))) === false) {
      $this->errors[] = "Unable to load XML from " . DB::g(STN::SCHOOL_API_URL);
      return;
    }

    self::errln("Inactivating schools", 2);
    DB::inactivateSchools($season);
    self::errln("Schools deactivated", 2);

    $used_urls = array();
    // parse data
    foreach ($xml->school as $school) {
      try {
        $id = trim((string)$school->school_code);
        $sch = DB::getSchool($id);
        if ($sch->created_by != Conf::$USER->id) {
          $this->warnings[] = sprintf("Ignoring %s as it was manually updated.", $school->school_code);
          continue;
        }
        $upd = true;
        $old_url = null;
        if ($sch === null) {
          $this->warnings[] = sprintf("New school: %s", $school->school_code);
          $sch = new School();
          $sch->id = $id;
          $sch->sync_log = $log;
          $upd = false;
        }
        else {
          $old_url = $sch->getURL();
        }
        $dist = trim((string)$school->district);
        $sch->conference = DB::getConference($dist);
        if ($sch->conference === null) {
          $this->errors['conf-'.$dist] = "No valid conference found: " . $dist;
          continue;
        }

        // Update fields
        $sch->name = trim((string)$school->school_name);
        if ($sch->nick_name === null)
          $sch->nick_name = School::createNick($school->school_display_name);
        $sch->nick_name = trim($sch->nick_name);
        $sch->city = trim((string)$school->school_city);
        $sch->state = trim((string)$school->school_state);
        $sch->inactive = null;

        $url = DB::slugify($sch->nick_name);
        if (isset($used_urls[$url])) {
          $root = $sch->nick_name . " " . $sch->conference;
          $url = DB::slugify($root);
          $suf = 1;
          while (isset($used_urls[$url])) {
            $url = DB::slugify($root . ' ' . $suf);
            $suf++;
          }
        }
        $sch->url = $url;
        $used_urls[$url] = $url;

        DB::set($sch, $upd);

        // Activate season entry
        if ($this->log_activation) {
          $season_entry = new School_Season();
          $season_entry->season = $season;
          $season_entry->school = $sch;
          DB::set($season_entry);
        }

        self::errln(sprintf("Activated school %10s: %s", $sch->id, $sch->name), 2);

        // URL change?
        $new_url = $sch->getURL();
        if ($old_url !== null && $old_url != $new_url) {
          self::errln(sprintf("URL change for school %10s: %s -> %s", $sch->name, $old_url, $new_url), 2);
          require_once('public/UpdateManager.php');
          UpdateManager::queueSchool($sch, UpdateSchoolRequest::ACTIVITY_URL, null, $old_url);
        }

      } catch (Exception $e) {
        $this->errors[] = "Invalid school information: " . $e->getMessage();
      }
    }
  }

  /**
   * Sync sailor, depending on argument.
   *
   * @param Member $proto one of Sailor
   * @param Sync_Log $log the log to save as
   */
  public function updateMember(Member $proto, Sync_Log $log) {
    $season = Season::forDate(DB::T(DB::NOW));
    if ($season === null) {
      self::errln("No current season available.");
      return;
    }

    $src = null;
    if ($proto instanceof Sailor)
      $src = DB::g(STN::SAILOR_API_URL);
    else
      throw new TSScriptException("I do not know how to sync that kind of member.");

    $role = 'sailor';
    if (strlen($src) == 0) {
      self::errln("No URL found for $role list.");
      return;
    }

    self::errln("Fetching and parsing $role list from $src");
    if (($xml = @simplexml_load_file($src)) === false) {
      $this->errors[] = "Unable to load XML from $src";
      return;
    }

    self::errln("Inactivating role $role", 2);
    RpManager::inactivateRole($proto->role);
    self::errln(sprintf("%s role deactivated", ucfirst($role)), 2);
    $used_urls = array();
    foreach ($xml->$role as $sailor) {
      try {
        $s = clone $proto;

        $school_id = trim((string)$sailor->school);
        $school = DB::getSchool($school_id);
        if ($school !== null) {
          $s->school = $school;
          $s->icsa_id = (int)$sailor->id;
          $s->last_name  = (string)$sailor->last_name;
          $s->first_name = (string)$sailor->first_name;
          if ($proto instanceof Sailor) {
            $s->year = (int)$sailor->year;
            $s->gender = $sailor->gender;
          }

          $this->updateSailor($s, $log, $season, $used_urls);
          self::errln("Activated $role $s", 2);
        }
        else
          $this->warnings[$school_id] = "Missing school " . $school_id;
      } catch (Exception $e) {
        $this->warnings[] = "Invalid sailor information: " . $e->getMessage() . "\n\n" . print_r($sailor, true);
      }
    }
  }

  /**
   * Runs the update
   *
   * @param boolean $schools true to sync schools
   * @param boolean $sailors true to sync sailors
   */
  public function run($schools = true, $sailors = true) {
    // Create log entry
    $log = new Sync_Log();
    $log->updated = array();
    $log->error = array();
    DB::set($log);

    // ------------------------------------------------------------
    // Schools
    // ------------------------------------------------------------
    if ($schools !== false) {
      $this->updateSchools($log);
      $log->updated[] = Sync_Log::SCHOOLS;
    }

    // ------------------------------------------------------------
    // Sailors
    // ------------------------------------------------------------
    if ($sailors !== false) {
      $this->updateMember(DB::T(DB::SAILOR), $log);
      $log->updated[] = Sync_Log::SAILORS;
    }

    foreach ($this->warnings as $mes) {
      self::errln("Warning: $mes");
    }

    foreach ($this->errors as $mes) {
      $log->error[] = $mes;
    }

    $log->ended_at = new DateTime();
    DB::set($log);
    return $log;
  }
}

if (isset($argv) && basename(__FILE__) == basename($argv[0])) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  // Parse arguments
  $P = new SyncDB();
  $opts = $P->getOpts($argv);
  if (count($opts) == 0)
    throw new TSScriptException("Missing update argument");
  $tosync = array(
    Sync_Log::SCHOOLS => false,
    Sync_Log::SAILORS => false,
  );

  $log = false;
  foreach ($opts as $arg) {
    switch ($arg) {
    case Sync_Log::SCHOOLS:
    case Sync_Log::SAILORS:
      $tosync[$arg] = true;
      break;

    case '--log':
      $log = true;
      break;

    default:
      throw new TSScriptException("Invalid argument $arg");
    }
  }
  $P->setLogActivation($log);
  $P->run($tosync[Sync_Log::SCHOOLS], $tosync[Sync_Log::SAILORS]);
  if ($tosync[Sync_Log::SCHOOLS]) {
    // Update the summary page for completeness
    require_once('UpdateSchoolsSummary.php');
    $P2 = new UpdateSchoolsSummary();
    $P2->run();

  }
  $err = $P->errors();
  if (count($err) > 0) {
    echo "----------Error(s)\n";
    foreach ($err as $mes)
      printf("  %s\n", $mes);
  }
}

