<?php
namespace scripts;

use \Conf;
use \DB;
use \DateTime;
use \Exception;
use \Member;
use \RpManager;
use \STN;
use \Sailor;
use \Sailor_Season;
use \School;
use \School_Season;
use \Season;
use \Sync_Log;
use \TSScriptException;
use \UpdateManager;
use \UpdateSailorRequest;
use \UpdateSchoolRequest;

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
   * @var DB the instance of "core" to use.
   * @see getCore() for auto-injection.
   */
  private $techsCore;

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

  public function setCore(DB $core) {
    $this->techsCore = get_class($core);
  }

  private function getCore() {
    if ($this->techsCore === null) {
      $this->techsCore = 'DB';
    }
    return $this->techsCore;
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
   * Fetch the warnings.
   *
   * @return Array:String warning messages
   */
  public function warnings() { return $this->warnings; }

  /**
   * Updates the information in the database about the given sailor
   *
   * @param Sailor $sailor the sailor
   * @param Sync_Log $log
   * @param Season $season
   */
  private function updateSailor(Member $sailor, Sync_Log $log, Season $season, Array &$used_urls) {
    $core = $this->getCore();
    $sailor->active = 1;
    $sailor->register_status = Sailor::STATUS_REGISTERED;
    $cur = $core::getSailorByExternalId($sailor->external_id);

    $update = false;
    if ($cur !== null) {
      if ($cur->student_profile !== null) {
        self::errln(sprintf("Skipping %s because they have a student profile.", $cur));
        return;
      }
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
    $url = $core::createUrlSlug(
      $seeds,
      function($slug) use ($used_urls) {
        return !array_key_exists($slug, $used_urls);
      }
    );
    $sailor->url = $url;
    $used_urls[$url] = $url;

    // URL change?
    $new_url = $sailor->getURL();
    if ($sailor instanceof Sailor && $core::g(STN::SAILOR_PROFILES) !== null && $old_url != $new_url) {
      self::errln(sprintf("URL change for sailor %s: %s -> %s", $name, $old_url, $new_url), 3);
      UpdateManager::queueSailor($sailor, UpdateSailorRequest::ACTIVITY_URL, $season, $old_url);

      // queue school DETAILS as well, if entirely new URL. This will
      // cause all the seasons to be regenerated, without affecting
      // the regattas.
      if ($old_url === null) {
        UpdateManager::queueSchool($sailor->school, UpdateSailorRequest::ACTIVITY_DETAILS, $season);
      }
    }

    $core::set($sailor, $update);

    // Activate season entry
    if ($this->log_activation) {
      $season_entry = new Sailor_Season();
      $season_entry->season = $season;
      $season_entry->sailor = $sailor;
      $core::set($season_entry);
    }
  }

  /**
   * Sync the schools
   *
   * @param Sync_Log $log the sync log to associate with updated schools
   */
  public function updateSchools(Sync_Log $log, Season $season = null) {
    $core = $this->getCore();
    if ($season == null) {
      $season = Season::forDate($core::T($core::NOW));
      if ($season === null) {
        self::errln("No current season available.");
        return;
      }
    }

    if (strlen($core::g(STN::SCHOOL_API_URL)) == 0) {
      self::errln("No URL to update schools list.");
      return;
    }

    self::errln("Fetching and parsing schools from " . $core::g(STN::SCHOOL_API_URL));

    if (($xml = @simplexml_load_file($core::g(STN::SCHOOL_API_URL))) === false) {
      $this->errors[] = "Unable to load XML from " . $core::g(STN::SCHOOL_API_URL);
      return;
    }

    self::errln("Inactivating schools", 2);
    $core::inactivateSchools($season);
    self::errln("Schools deactivated", 2);

    $used_urls = array();
    // parse data
    foreach ($xml->school as $school) {
      try {
        $id = trim((string)$school->school_code);
        $sch = $core::getSchool($id);
        $isTrackedManually = false;

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
          $isTrackedManually = $sch->created_by != Conf::$USER->id;
          $old_url = $sch->getURL();
        }

        if (!$isTrackedManually) {
          $dist = trim((string)$school->district);
          $sch->conference = $core::getConference($dist);
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
        }
        else {
          $this->warnings[] = sprintf("Ignoring %s as it was manually updated.", $school->school_code);
        }

        $url = $core::slugify($sch->nick_name);
        if (array_key_exists($url, $used_urls)) {
          $root = $sch->nick_name . " " . $sch->conference;
          $url = $core::slugify($root);
          $suf = 1;
          while (array_key_exists($url, $used_urls)) {
            $url = $core::slugify($root . ' ' . $suf, false);
            $suf++;
          }
        }
        $sch->url = $url;
        $used_urls[$url] = $url;

        $core::set($sch, $upd);

        // Activate season entry
        if ($this->log_activation) {
          $season_entry = new School_Season();
          $season_entry->season = $season;
          $season_entry->school = $sch;
          $core::set($season_entry);
        }

        self::errln(sprintf("Activated school %10s: %s", $sch->id, $sch->name), 2);

        // URL change?
        $new_url = $sch->getURL();
        if ($old_url !== null && $old_url != $new_url) {
          self::errln(sprintf("URL change for school %10s: %s -> %s", $sch->name, $old_url, $new_url), 2);
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
    $core = $this->getCore();
    $season = Season::forDate($core::T($core::NOW));
    if ($season === null) {
      self::errln("No current season available.");
      return;
    }

    $src = null;
    if ($proto instanceof Sailor)
      $src = $core::g(STN::SAILOR_API_URL);
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
        $school = $core::getSchool($school_id);
        if ($school !== null) {
          $s->school = $school;
          $s->external_id = (int) $sailor->id;
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
   * @param Season $season the optional season to work with.
   * @return Sync_Log the log of what happened.
   */
  public function run($schools = true, $sailors = true, Season $season = null) {
    // Create log entry
    $core = $this->getCore();
    $log = new Sync_Log();
    $log->updated = array();
    $log->error = array();
    $core::set($log);

    // ------------------------------------------------------------
    // Schools
    // ------------------------------------------------------------
    if ($schools !== false) {
      $this->updateSchools($log, $season);
      $log->updated[] = Sync_Log::SCHOOLS;
    }

    // ------------------------------------------------------------
    // Sailors
    // ------------------------------------------------------------
    if ($sailors !== false) {
      $this->updateMember($core::T($core::SAILOR), $log);
      $log->updated[] = Sync_Log::SAILORS;
    }

    foreach ($this->warnings as $mes) {
      self::errln("Warning: $mes");
    }

    foreach ($this->errors as $mes) {
      $log->error[] = $mes;
    }

    $log->ended_at = new DateTime();
    $core::set($log);
    return $log;
  }

  public function runCli(Array $argv) {
    if (Conf::$LIBXML_STREAM_CONTEXT !== null) {
      libxml_set_streams_context(Conf::$LIBXML_STREAM_CONTEXT);
    }

    $opts = $this->getOpts($argv);
    if (count($opts) == 0) {
      throw new TSScriptException("Missing update argument");
    }
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
    $this->setLogActivation($log);
    $this->run($tosync[Sync_Log::SCHOOLS], $tosync[Sync_Log::SAILORS]);
    if ($tosync[Sync_Log::SCHOOLS]) {
      // Update the summary page for completeness
      $this2 = new UpdateSchoolsSummary();
      $this2->run();

    }
    $err = $this->errors();
    if (count($err) > 0) {
      echo "----------Error(s)\n";
      foreach ($err as $mes)
        printf("  %s\n", $mes);
    }
  }
}
