<?php
namespace scripts;

use \writers\TSWriterException;

use \Conf;
use \DB;
use \DateTime;
use \Exception;
use \FullRegatta;
use \Pub_File;
use \RuntimeException;
use \Sailor;
use \School;
use \Season;
use \STN;
use \TSScriptException;
use \UpdateConferenceRequest;
use \UpdateManager;
use \UpdateRequest;
use \UpdateSailorRequest;
use \UpdateSchoolRequest;
use \UpdateSeasonRequest;

/**
 * This script orchestrates all the queued update requests so that
 * they are executed from one process. This is necessary so that
 * concurrent update requests do not clash when writing to the
 * filesystem.
 *
 * In addition, by originating from a centralized location, the update
 * process can be made faster and more efficient. For instance,
 * consider two regattas from the same season being updated at once
 * (this is actually a typical situation, of course). Updating each
 * regatta also triggers updates to the season summary page and the
 * home page. Were the writing process to occur as separate processes
 * for each regatta, the season page would be updated twice.
 *
 * With this method, the daemon wakes up and checks the queue of
 * requests from the database. It then reads the queues and determines
 * which sub-processes to run "intelligently." In the example above,
 * the daemon would decide to update the regattas individually, and
 * then update the season page only once.
 *
 * Suppose further that in between wake up times, the same regatta was
 * updated twice. All updates would then be gobbled up by one update
 * process at the time interval specified either in the daemon or the
 * cronjob, as explained below.
 *
 * Also, since only one process is writing to the filesystem at a
 * time, the chances of two processes writing to the same file at the
 * same time is minimized--dare I say, eliminated?
 *
 * As of 2013-03-25, this script works more like a real daemon. When
 * launched, it creates a PID file in the tmp directory (in what used
 * to be a simple lock file). It will then launch itself into an
 * infinite loop, in which it checks for all pending updates,
 * processes them, and then checks again, or goes to sleep, depending
 * on the kind of daemon it is being run as.
 *
 * For instance, Regatta updates happen "as quickly as possible", to
 * provide "real time" results. Updates to the season and front pages
 * happen every 5 minutes, while school page updates every 20.
 *
 * This script has been written in the style of the other update
 * scripts in that it can be run from the command line or as a
 * "library" call from a different script, by using the class's 'run'
 * method.
 *
 * 2013-10-04: Included a new daemon for "files"
 *
 * @author Dayan Paez
 * @version 2010-10-08
 * @package scripts
 */
class Daemon extends AbstractScript {

  private static $lock_files = array(); // full path, used below

  public static $MAX_REQUESTS_PER_CYCLE = 50;

  // ------------------------------------------------------------
  // Public pages that need to be updated, after parsing through all
  // the update requests
  // ------------------------------------------------------------

  /**
   * @var Map regatta ID => Regatta objects for reference
   */
  private $regattas;
  /**
   * @var Map regatta ID => Array:UpdateRequest::CONST. This is the
   * second argument to UpdateRegatta::run.
   */
  private $activities;
  /**
   * @var Map of season pages to update (ID => Season object)
   */
  private $seasons;
  /**
   * @var Map of season ID => list of activities
   */
  private $season_activities;
  /**
   * @var Map of school objects for reference (ID => School)
   */
  private $schools;
  /**
   * @var Map of school ID => list of seasons to update
   */
  private $school_seasons;
  /**
   * @var Map of sailor objects to update (ID => Sailor)
   */
  private $sailorsRp;
  /**
   * @var Map of Conference objects for reference (ID => School)
   */
  private $conferences;
  /**
   * @var Map of Conference ID => list of seasons to update
   */
  private $conference_seasons;

  /**
   * Creates or complains about lock file
   *
   * @param String $suffix the identifier for the lock file
   */
  private function createLock($suffix) {
    if (file_exists(self::$lock_files[$suffix]) && !@unlink(self::$lock_files[$suffix]))
      throw new TSScriptException("Unable to remove PID file.", 2);

    // Create file lock
    register_shutdown_function('\scripts\Daemon::cleanup');
    if (file_put_contents(self::$lock_files[$suffix], getmypid()) === false)
      throw new TSScriptException("Unable to create PID file.", 4);
  }

  /**
   * Check that the PID file exists and equals argument $pid
   *
   * If $pid is null, then make sure that the lock files does not
   * exist, or that the PID in it is no longer active.
   *
   */
  private function checkLock($suffix, $pid = null) {
    self::$lock_files[$suffix] = sprintf("%s/%s-" . $suffix, sys_get_temp_dir(), Conf::$LOCK_FILENAME);
    if ($pid === null) {
      if (@file_exists(self::$lock_files[$suffix])) {
        $pid = file_get_contents(self::$lock_files[$suffix]);

        $old = set_error_handler(function($errno, $errstr) {
            if ($errno == E_WARNING)
              return;
            throw new TSScriptException($errstr, $errno);
          });
        if (pcntl_getpriority($pid) !== false)
          throw new TSScriptException("Daemon is already running with PID $pid.", 8);
        set_error_handler($old);
      }
      return;
    }
    
    if (!@file_exists(self::$lock_files[$suffix]))
      throw new TSScriptException("Lock file is gone.", 16);
    $content = @file_get_contents(self::$lock_files[$suffix]);
    if ($content != $pid)
      throw new TSScriptException("Lock file owned by different process.", 18);
  }

  /**
   * Retrieves the md5sum, if any
   *
   * @return String the sum
   */
  private function getMD5sum() {
    $sum = null;
    $path = dirname(dirname(__DIR__)) . '/src/md5sum';
    if (file_exists($path)) {
      if (($sum = file_get_contents($path)) !== false)
        $sum = trim($sum);
    }
    return $sum;
  }

  /**
   * Checks if the given md5sum matches the current one from file.
   *
   * If no match, then throws a TSScriptException. A mismatch is
   * caused by a non-null $current argument that does not equal the
   * value from getMD5sum (which, in turn, may be null).
   *
   * @param String|null $current the current sum, if any
   * @return String the sum
   * @throws TSScriptException if mismatch
   */
  private function checkMD5sum($current = null) {
    $file = $this->getMD5sum();
    if ($current !== null && $current != $file)
      throw new TSScriptException("MD5sum has changed on file.");
    return $file;
  }

  /**
   * Fork off as daemon, and return child PID
   *
   */
  private function daemonize() {
    DB::setConnection(null); // force a reconnect
    $pid = pcntl_fork();
    if ($pid == -1) {
      throw new TSScriptException("Could not fork.");
    }
    if ($pid != 0) {
      exit(0); // parent
    }
    if (posix_setsid() == -1) {
      throw new TSScriptException("Could not detach from terminal.");
    }

    // register signal handlers
    $handler = function($signo) {
      echo "Termination received. Exiting.\n";
      Daemon::cleanup();
      exit(127);
    };

    pcntl_signal(SIGTERM, $handler);
    pcntl_signal(SIGHUP, $handler);

    return getmypid();
  }

  /**
   * Checks for file-level updates and performs them
   *
   * @param boolean $daemon run in daemon mode
   */
  public function runFiles($daemon = false) {
    $this->checkLock('fil');
    $md5 = $this->checkMD5sum();
    if ($daemon)
      $mypid = $this->daemonize();
    $this->createLock('fil');

    $con = DB::connection();
    $con->autocommit(true);
    while (true) {
      $pending = UpdateManager::getPendingFiles();
      if (count($pending) == 0) {
        if ($daemon) {
          self::errln("[files] Sleeping...");
          DB::commit();
          DB::resetCache();
          sleep(59);
          $this->checkLock('fil', $mypid);
          $md5 = $this->checkMD5sum($md5);
          continue;
        }
        break;
      }

      $hashes = array();
      $requests = array();
      // ------------------------------------------------------------
      // Loop through the file requests
      // ------------------------------------------------------------
      $P = new UpdateFile();
      $initJS = false;
      foreach ($pending as $i => $r) {
        if ($i >= self::$MAX_REQUESTS_PER_CYCLE)
          break;

        UpdateManager::markAttempt($r);
        $requests[] = $r;

        $hash = $r->hash();
        if (isset($hashes[$hash]))
          continue;
        $hashes[$hash] = $r;

        // Init?
        if ($r->file == Pub_File::INIT_FILE) {
          $initJS = true;
          continue;
        }

        // Any JS file?
        if (substr($r->file, -3) == '.js')
          $initJS = true;

        try {
          // ------------------------------------------------------------
          // Perform the updates
          // ------------------------------------------------------------
          $P->run($r->file);
          DB::commit();
        }
        catch (TSWriterException $e) {
          DB::commit();
          self::errln("[files] Error while writing: " . $e->getMessage(), 0);
          sleep(3);
          continue;
        }
      }
      if ($initJS) {
        $P->runInitJs();
      }

      // ------------------------------------------------------------
      // Mark all requests as completed
      // ------------------------------------------------------------
      foreach ($requests as $r)
        UpdateManager::log($r);

      // ------------------------------------------------------------
      // Perform all hooks
      // ------------------------------------------------------------
      foreach (self::getHooks() as $hook) {
        $ret = 0;
        passthru($hook, $ret);
        if ($ret != 0)
          throw new RuntimeException("Hook $hook", $ret);
        self::errln("[files] Hook $hook run");
      }

      DB::commit();
      DB::resetCache();
    }

    self::errln('[files] done');
  }

  /**
   * Checks for sailor-level updates and performs them
   *
   * @param boolean $daemon run in daemon mode
   */
  public function runSailors($daemon = false) {
    $this->checkLock('slr');
    $md5 = $this->checkMD5sum();
    if ($daemon)
      $mypid = $this->daemonize();
    $this->createLock('slr');

    /**
     * Helper function to trigger sailor dependencies
     */
    function triggerDependencies(Sailor $sailor) {
      foreach ($sailor->getRegattas() as $regatta) {
        UpdateManager::queueRequest($regatta, UpdateRequest::ACTIVITY_RP);
      }
      foreach ($sailor->getSeasonsActive() as $season) {
        UpdateManager::queueSchool($sailor->school, UpdateSchoolRequest::ACTIVITY_ROSTER, $season);
      }
    }

    $con = DB::connection();
    $con->autocommit(true);
    while (true) {
      $pending = UpdateManager::getPendingSailors();
      if (count($pending) == 0) {
        if ($daemon) {
          self::errln("[sailors] Sleeping...");
          DB::commit();
          DB::resetCache();
          sleep(639);
          $this->checkLock('slr', $mypid);
          $md5 = $this->checkMD5sum($md5);
          continue;
        }
        break;
      }

      $requests = array();
      // ------------------------------------------------------------
      // Loop through the sailor requests
      // ------------------------------------------------------------
      $sailors = array(); // map of sailors indexed by ID
      $to_delete = array();

      foreach ($pending as $i => $r) {
        if ($i >= self::$MAX_REQUESTS_PER_CYCLE)
          break;

        UpdateManager::markAttempt($r);
        $requests[] = $r;

        $sailors[$r->sailor->id] = $r->sailor;
          
        // DISPLAY
        if ($r->activity == UpdateSailorRequest::ACTIVITY_DISPLAY) {
          if (DB::g(STN::SAILOR_PROFILES) === null) {
            if ($r->argument !== null) {
              $to_delete[$r->argument] = $r->argument;
            }
          }
        }

        // URL
        if ($r->activity == UpdateSailorRequest::ACTIVITY_URL) {
          if ($r->argument !== null) {
            $to_delete[$r->argument] = $r->argument;
          }
        }

        // SEASON
        if ($r->activity == UpdateSailorRequest::ACTIVITY_SEASON && $r->season !== null) {
        }

        // The following updates trigger changes across dependencies
        if ($r->activity == UpdateSailorRequest::ACTIVITY_DISPLAY
            || $r->activity == UpdateSailorRequest::ACTIVITY_URL
            || $r->activity == UpdateSailorRequest::ACTIVITY_NAME) {

          triggerDependencies($r->sailor);
        }
      }

      try {
        // ------------------------------------------------------------
        // Perform the updates
        // ------------------------------------------------------------
        foreach ($to_delete as $root) {
          self::remove($root);
        }

        if (count($sailors) > 0) {
          $P = new UpdateSailor();
          foreach ($sailors as $sailor) {
            $P->run($sailor);
            DB::commit();
            self::errln(
              sprintf('[sailors] generated sailor %s: %s', $sailor->getURL(), $sailor)
            );
          }
        }

        $P = new UpdateSchoolsSummary();
        $P->runSailors();
        DB::commit();
      }
      catch (TSWriterException $e) {
        DB::commit();
        self::errln("[sailors] Error while writing: " . $e->getMessage(), 0);
        sleep(3);
        continue;
      }

      // ------------------------------------------------------------
      // Mark all requests as completed
      // ------------------------------------------------------------
      foreach ($requests as $r)
        UpdateManager::log($r);

      // ------------------------------------------------------------
      // Perform all hooks
      // ------------------------------------------------------------
      foreach (self::getHooks() as $hook) {
        $ret = 0;
        passthru($hook, $ret);
        if ($ret != 0)
          throw new RuntimeException("Hook $hook", $ret);
        self::errln("[sailors] Hook $hook run");
      }
      DB::commit();
      DB::resetCache();
    }

    self::errln('[sailors] done');
  }

  /**
   * Checks for school-level updates and performs them
   *
   * @param boolean $daemon run in daemon mode
   */
  public function runSchools($daemon = false) {
    $this->checkLock('sch');
    $md5 = $this->checkMD5sum();
    if ($daemon)
      $mypid = $this->daemonize();
    $this->createLock('sch');

    $con = DB::connection();
    $con->autocommit(true);
    while (true) {
      $pending = UpdateManager::getPendingSchools();
      if (count($pending) == 0) {
        if ($daemon) {
          self::errln("[schools] Sleeping...");
          DB::commit();
          DB::resetCache();
          sleep(123);
          $this->checkLock('sch', $mypid);
          $md5 = $this->checkMD5sum($md5);
          continue;
        }
        break;
      }

      $requests = array();
      // ------------------------------------------------------------
      // Loop through the school requests
      // ------------------------------------------------------------
      $burgees = array(); // map of schools whose burgees to update

      $schools = array(); // map of schools indexed by ID
      $seasons = array(); // map of seasons to update indexed by school
      $rosters = array(); // map of seasons indexed by school

      $to_delete = array();

      foreach ($pending as $i => $r) {
        if ($i >= self::$MAX_REQUESTS_PER_CYCLE)
          break;

        UpdateManager::markAttempt($r);
        $requests[] = $r;
        if ($r->activity == UpdateSchoolRequest::ACTIVITY_BURGEE) {
          $burgees[$r->school->id] = $r->school;
        }
        elseif ($r->activity == UpdateSchoolRequest::ACTIVITY_ROSTER) {
          $schools[$r->school->id] = $r->school;

          if (!isset($rosters[$r->school->id]))
            $rosters[$r->school->id] = array();

          // season value is null: do all (such as for ACTIVITY_DETAILS)
          if ($r->season !== null)
            $rosters[$r->school->id][(string)$r->season] = $r->season;
          else {
            foreach ($r->school->getSeasons() as $season) {
              $rosters[$r->school->id][(string)$season] = $season;
            }
          }
        }
        elseif ($r->activity == UpdateSchoolRequest::ACTIVITY_SEASON) {
          $schools[$r->school->id] = $r->school;
          if (!isset($seasons[$r->school->id]))
            $seasons[$r->school->id] = array();

          if ($r->season !== null) {
            $seasons[$r->school->id][(string)$r->season] = $r->season;
          }
          else {
            foreach ($r->school->getSeasons() as $season) {
              $seasons[$r->school->id][(string)$season] = $season;
            }
          }
        }
        else { // season summary, or details
          // URL: delete old one
          if ($r->activity == UpdateSchoolRequest::ACTIVITY_URL) {
            if ($r->argument !== null)
              $to_delete[$r->argument] = $r->argument;

            // trigger all the school's regattas
            foreach ($r->school->getRegattas() as $reg) {
              UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_TEAM);
            }
          }

          $schools[$r->school->id] = $r->school;
          if (!isset($seasons[$r->school->id]))
            $seasons[$r->school->id] = array();
          if (!isset($rosters[$r->school->id]))
            $rosters[$r->school->id] = array();
          
          // season value is null: do all (such as for ACTIVITY_DETAILS)
          if ($r->season !== null && $r->activity != UpdateSchoolRequest::ACTIVITY_URL) {
            $seasons[$r->school->id][(string)$r->season] = $r->season;
            $rosters[$r->school->id][(string)$r->season] = $r->season;
          }
          else {
            foreach ($r->school->getSeasons() as $season) {
              $seasons[$r->school->id][(string)$season] = $season;
              $rosters[$r->school->id][(string)$season] = $season;
            }
          }
        }
      }

      try {
        // ------------------------------------------------------------
        // Perform the updates
        // ------------------------------------------------------------
        foreach ($to_delete as $root) {
          self::remove($root);
        }

        if (count($burgees) > 0) {
          $P = new UpdateBurgee();
          foreach ($burgees as $school) {
            $P->run($school);
            DB::commit();
            self::errln(sprintf('[schools] generated burgee for %s', $school));
          }
        }

        $P = null;
        if (count($seasons) > 0) {
          $P = new UpdateSchool();
          foreach ($seasons as $id => $list) {
            foreach ($list as $season) {
              $P->run($schools[$id], $season);
              DB::commit();
              self::errln(sprintf('[schools] generated school %s/%-6s %s', $season, $schools[$id], $schools[$id]));
            }
          }
        }

        if (count($rosters) > 0) {
          if ($P === null) {
            $P = new UpdateSchool();
          }
          foreach ($rosters as $id => $list) {
            foreach ($list as $season) {
              $P->runRoster($schools[$id], $season);
              DB::commit();
              self::errln(sprintf('[schools] generated roster for school %s/%-6s %s', $season, $schools[$id], $schools[$id]));
            }
          }
        }

        $P = new UpdateSchoolsSummary();
        $P->run();
        DB::commit();
      }
      catch (TSWriterException $e) {
        DB::commit();
        self::errln("[schools] Error while writing: " . $e->getMessage(), 0);
        sleep(3);
        continue;
      }

      // ------------------------------------------------------------
      // Mark all requests as completed
      // ------------------------------------------------------------
      foreach ($requests as $r)
        UpdateManager::log($r);

      // ------------------------------------------------------------
      // Perform all hooks
      // ------------------------------------------------------------
      foreach (self::getHooks() as $hook) {
        $ret = 0;
        passthru($hook, $ret);
        if ($ret != 0)
          throw new RuntimeException("Hook $hook", $ret);
        self::errln("[schools] Hook $hook run");
      }
      DB::commit();
      DB::resetCache();
    }

    self::errln('[schools] done');
  }

  /**
   * Checks for conference-level updates and performs them
   *
   * @param boolean $daemon run in daemon mode
   */
  public function runConferences($daemon = false) {
    $this->checkLock('cnf');
    $md5 = $this->checkMD5sum();
    if ($daemon)
      $mypid = $this->daemonize();
    $this->createLock('cnf');

    $con = DB::connection();
    $con->autocommit(true);
    while (true) {
      $pending = UpdateManager::getPendingConferences();
      if (count($pending) == 0) {
        if ($daemon) {
          self::errln("[conferences] Sleeping...");
          DB::commit();
          DB::resetCache();
          sleep(123);
          $this->checkLock('cnf', $mypid);
          $md5 = $this->checkMD5sum($md5);
          continue;
        }
        break;
      }

      $requests = array();
      // ------------------------------------------------------------
      // Loop through the conference requests
      // ------------------------------------------------------------
      $conferences = array(); // map of conferences indexed by ID
      $seasons = array(); // map of seasons to update indexed by conference
      $to_delete = array();

      $currentSeasons = DB::getAll(DB::T(DB::SEASON));
      foreach ($pending as $i => $r) {
        if ($i >= self::$MAX_REQUESTS_PER_CYCLE)
          break;

        UpdateManager::markAttempt($r);
        $requests[] = $r;

        // Missing conference means delete
        if ($r->conference === null) {
          foreach ($currentSeasons as $season) {
            if ($r->argument !== null) {
              $to_delete[$r->argument] = $r->argument;
            }
          }
          continue;
        }

        $conferences[$r->conference->id] = $r->conference;
        if (!isset($seasons[$r->conference->id]))
          $seasons[$r->conference->id] = array();
          
        // DISPLAY
        if ($r->activity == UpdateConferenceRequest::ACTIVITY_DISPLAY) {
          if (DB::g(STN::PUBLISH_CONFERENCE_SUMMARY) === null) {
            if ($r->argument !== null) {
              $to_delete[$r->argument] = $r->argument;
            }
          }
          else {
            foreach ($currentSeasons as $season) {
              if (count($season->getConferenceParticipation($r->conference)) > 0) {
                $seasons[$r->conference->id][(string)$season] = $season;
              }
            }
          }

          // trigger all the conference's schools
          foreach ($r->conference->getSchools() as $school) {
            UpdateManager::queueSchool($school, UpdateSchoolRequest::ACTIVITY_DETAILS);
          }
        }

        // URL
        if ($r->activity == UpdateConferenceRequest::ACTIVITY_URL) {
          if ($r->argument !== null)
            $to_delete[$r->argument] = $r->argument;

          // trigger all the conference's schools
          foreach ($r->conference->getSchools() as $school) {
            UpdateManager::queueSchool($school, UpdateSchoolRequest::ACTIVITY_DETAILS);
          }
        }

        // SEASON
        if ($r->activity == UpdateConferenceRequest::ACTIVITY_SEASON && $r->season !== null) {
          $seasons[$r->conference->id][(string)$r->season] = $r->season;
        }
      }

      try {
        // ------------------------------------------------------------
        // Perform the updates
        // ------------------------------------------------------------
        foreach ($to_delete as $root) {
          self::remove($root);
        }

        if (count($seasons) > 0) {
          $P = new UpdateConference();
          foreach ($seasons as $id => $list) {
            foreach ($list as $season) {
              $P->run($conferences[$id], $season);
              DB::commit();
              self::errln(sprintf('[conferences] generated conference %s/%-6s %s', $season, $conferences[$id], $conferences[$id]));
            }
          }
        }

        $P = new UpdateSchoolsSummary();
        $P->run();
        DB::commit();
      }
      catch (TSWriterException $e) {
        DB::commit();
        self::errln("[conferences] Error while writing: " . $e->getMessage(), 0);
        sleep(3);
        continue;
      }

      // ------------------------------------------------------------
      // Mark all requests as completed
      // ------------------------------------------------------------
      foreach ($requests as $r)
        UpdateManager::log($r);

      // ------------------------------------------------------------
      // Perform all hooks
      // ------------------------------------------------------------
      foreach (self::getHooks() as $hook) {
        $ret = 0;
        passthru($hook, $ret);
        if ($ret != 0)
          throw new RuntimeException("Hook $hook", $ret);
        self::errln("[conferences] Hook $hook run");
      }
      DB::commit();
      DB::resetCache();
    }

    self::errln('[conferences] done');
  }

  /**
   * Checks for season-level updates and performs them
   *
   * @param boolean $daemon run in daemon mode
   */
  public function runSeasons($daemon = false) {
    $this->checkLock('sea');
    $md5 = $this->checkMD5sum();
    if ($daemon)
      $mypid = $this->daemonize();
    $this->createLock('sea');

    $con = DB::connection();
    $con->autocommit(true);
    while (true) {
      $pending = UpdateManager::getPendingSeasons();
      if (count($pending) == 0) {
        if ($daemon) {
          self::errln("[seasons] Sleeping...");
          DB::commit();
          DB::resetCache();
          sleep(57);
          $this->checkLock('sea', $mypid);
          $md5 = $this->checkMD5sum($md5);
          continue;
        }
        break;
      }

      $requests = array();
      // ------------------------------------------------------------
      // Loop through the season requests
      // ------------------------------------------------------------

      // perform season summary as well?
      $summary = false;
      $front = false;
      $current = Season::forDate(new DateTime());
      $seasons = array();
      $general404 = false;
      $school404 = false;
      foreach ($pending as $i => $r) {
        if ($i >= self::$MAX_REQUESTS_PER_CYCLE)
          break;

        UpdateManager::markAttempt($r);
        $requests[] = $r;

        // TODO: handle URL changes?
        if ($r->activity == UpdateSeasonRequest::ACTIVITY_FRONT)
          $front = true;
        elseif ($r->activity == UpdateSeasonRequest::ACTIVITY_404)
          $general404 = true;
        elseif ($r->activity == UpdateSeasonRequest::ACTIVITY_SCHOOL_404)
          $school404 = true;
        else {
          $seasons[(string)$r->season] = $r->season;
          if ($r->activity == UpdateSeasonRequest::ACTIVITY_REGATTA)
            $summary = true;

          if ((string)$r->season == (string)$current) {
            $front = true;
          }
        }
      }

      try {
        if (count($seasons) > 0) {
          $P = new UpdateSeason();
          foreach ($seasons as $season) {
            $P->run($season);
            DB::commit();
            self::errln(sprintf("[seasons] processed season update %s: %s", $season->id, $season->fullString()));
          }
        }
        if ($summary) {
          $P = new UpdateSeasonsSummary();
          $P->run();
          DB::commit();
          self::errln('[seasons] generated seasons summary page');
        }
        if ($front) {
          $P = new UpdateFront();
          $P->run();
          DB::commit();
          self::errln('[seasons] generated front page');
        }
        if ($general404 || $school404) {
          $P = new Update404();
          $P->run($general404, $school404);
          DB::commit();
          self::errln('[seasons] generated 404 page(s)');
        }
      }
      catch (TSWriterException $e) {
        DB::commit();
        self::errln("[seasons] Error while writing: " . $e->getMessage(), 0);
        sleep(3);
        continue;
      }

      // ------------------------------------------------------------
      // Mark all requests as completed
      // ------------------------------------------------------------
      foreach ($requests as $r)
        UpdateManager::log($r);

      // ------------------------------------------------------------
      // Perform all hooks
      // ------------------------------------------------------------
      foreach (self::getHooks() as $hook) {
        $ret = 0;
        passthru($hook, $ret);
        if ($ret != 0)
          throw new RuntimeException("Hook $hook", $ret);
        self::errln("[seasons] Hook $hook run");
      }
      DB::commit();
      DB::resetCache();
    }

    self::errln('[seasons] done');
  }

  /**
   * Checks for the existance of a file lock. If absent, proceeds to
   * create one, then checks the queue of update requests for pending
   * items. Proceeds to intelligently update the public side of
   * TechScore, and finally removes the file lock so that a second
   * instance of this method can be called.
   *
   */
  public function runRegattas($daemon = false) {
    $this->checkLock('reg');
    $md5 = $this->checkMD5sum();
    if ($daemon) {
      $mypid = $this->daemonize();
    }
    $this->createLock('reg');

    $con = DB::connection();
    $con->autocommit(true);
    while (true) {
      $pending = UpdateManager::getPendingRequests();
      if (count($pending) == 0) {
        if ($daemon) {
          self::errln("[regattas] Sleeping...");
          DB::commit();
          DB::resetCache();
          sleep(23);
          $this->checkLock('reg', $mypid);
          $md5 = $this->checkMD5sum($md5);
          continue;
        }
        break;
      }

      // ------------------------------------------------------------
      // Initialize the set of updates that need to be created
      // ------------------------------------------------------------
      $requests = array(); // list of processed requests
      // For efficiency, track the activities performed for each
      // regatta, so as not to re-analyze
      $hashes = array();

      $this->regattas = array();
      $this->activities = array();

      $this->seasons = array();
      $this->season_activities = array();
      $this->schools = array();
      $this->school_seasons = array();
      $this->sailorsRp = array();
      $this->conferences = array();
      $this->conference_seasons = array();

      // URLs to delete
      $to_delete = array();

      // Regattas to delete from database (due to inactive flag) as a map
      $deleted_regattas = array();
      $delete_threshold = new DateTime('5 minutes ago');

      // ------------------------------------------------------------
      // Loop through the regatta requests
      // ------------------------------------------------------------
      foreach ($pending as $i => $r) {
        if ($i >= self::$MAX_REQUESTS_PER_CYCLE)
          break;

        UpdateManager::markAttempt($r);
        $requests[] = $r;

        $reg = $r->regatta;
        if ($reg->inactive !== null)
          $deleted_regattas[$reg->id] = $reg;

        $hash = $r->hash();
        if (isset($hashes[$hash]))
          continue;
        $hashes[$hash] = $r;

        $this->queueRegattaActivity($reg, $r->activity);
        $season = $reg->getSeason();

        // If season change, then check for old season to delete, and
        // queue old seasons as well
        if ($r->activity == UpdateRequest::ACTIVITY_SEASON && $r->argument !== null) {
          $prior_season = DB::getSeason($r->argument);
          if ($prior_season !== null) {
            $this->seasons[$prior_season->id] = $prior_season;
            // Queue for deletion
            $root = $reg->getURLForSeason($prior_season);
            $to_delete[$root] = $root;

            foreach ($reg->getTeams() as $team)
              $this->queueSchoolSeason($team->school, $prior_season, $reg);
          }
        }

        // If team change, then check for affected school in argument,
        // and update that school's season page
        if ($r->activity == UpdateRequest::ACTIVITY_TEAM && $r->argument !== null) {
          $school = DB::getSchool($r->argument);
          if ($school !== null) {
            $this->queueSchoolSeason($school, $season, $reg);
          }
        }

        // If the regatta is personal, but a request still exists, then
        // request the update of the seasons, the schools, and the
        // season summaries, regardless.
        if ($reg->private !== null || $reg->inactive !== null) {
          $this->queueSeason($season, UpdateSeasonRequest::ACTIVITY_REGATTA);
          foreach ($reg->getTeams() as $team)
            $this->queueSchoolSeason($team->school, $season, $reg);
          if ($reg->nick !== null) {
            $root = $reg->getUrl();
            $to_delete[$root] = $root;
          }
          continue;
        }

        switch ($r->activity) {
          // ------------------------------------------------------------
        case UpdateRequest::ACTIVITY_DETAILS:
        case UpdateRequest::ACTIVITY_FINALIZED:
        case UpdateRequest::ACTIVITY_SEASON:
        case UpdateRequest::ACTIVITY_SCORE:
          foreach ($reg->getTeams() as $team) {
            $this->queueSchoolSeason($team->school, $season, $reg);
          }
        case UpdateRequest::ACTIVITY_TEAM:
          $this->queueSeason($season, UpdateSeasonRequest::ACTIVITY_REGATTA);
          break;
          // ------------------------------------------------------------
        case UpdateRequest::ACTIVITY_RP:
        case UpdateRequest::ACTIVITY_RANK:
          if ($r->argument !== null)
            $this->queueSchoolSeason(DB::getSchool($r->argument), $season, $reg);
          break;
          // ------------------------------------------------------------
          // Rotation and summary do not affect seasons or schools
        }
      }

      // In case of a writing exception, catch it, and sleep for a
      // second to allow some "flush" time before restarting
      try {
        // ------------------------------------------------------------
        // Perform deletions
        // ------------------------------------------------------------
        foreach ($to_delete as $root)
          self::remove($root);

        // ------------------------------------------------------------
        // Perform regatta level updates
        // ------------------------------------------------------------
        $P = new UpdateRegatta();
        foreach ($this->regattas as $id => $reg) {
          $P->run($reg, $this->activities[$id]);
          DB::commit();
          foreach ($this->activities[$id] as $act)
            self::errln(sprintf("[regattas] performed activity %s on %4d: %s", $act, $id, $reg->name));
        }
        DB::commit();
      }
      catch (TSWriterException $e) {
        DB::commit();
        self::errln("[regattas] Error while writing: " . $e->getMessage(), 0);
        sleep(3);
        continue;
      }

      // ------------------------------------------------------------
      // Queue season updates
      // ------------------------------------------------------------
      foreach ($this->season_activities as $id => $activities) {
        foreach ($activities as $activity) {
          UpdateManager::queueSeason($this->seasons[$id], $activity);
          self::errln(sprintf('[regattas] queued season %s', $id));
        }
      }

      // ------------------------------------------------------------
      // Queue school updates
      // ------------------------------------------------------------
      foreach ($this->school_seasons as $id => $seasons) {
        foreach ($seasons as $season) {
          UpdateManager::queueSchool($this->schools[$id], UpdateSchoolRequest::ACTIVITY_SEASON, $season);
          self::errln(sprintf('[regattas] queued school %s/%-6s %s', $season, $id, $this->schools[$id]->nick_name));
        }
      }

      // ------------------------------------------------------------
      // Queue sailor updates
      // ------------------------------------------------------------
      foreach ($this->sailorsRp as $id => $sailor) {
        UpdateManager::queueSailor($sailor, UpdateSailorRequest::ACTIVITY_RP);
        self::errln(sprintf('[regattas] queued sailor RP %-6s %s', $id, $sailor));
      }

      // ------------------------------------------------------------
      // Queue conference updates
      // ------------------------------------------------------------
      foreach ($this->conference_seasons as $id => $seasons) {
        foreach ($seasons as $season) {
          UpdateManager::queueConference($this->conferences[$id], UpdateConferenceRequest::ACTIVITY_SEASON, $season);
          self::errln(sprintf('[regattas] queued conference %s/%-6s %s', $season, $id, $this->conferences[$id]));
        }
      }

      // ------------------------------------------------------------
      // Mark all requests as completed
      // ------------------------------------------------------------
      foreach ($requests as $r)
        UpdateManager::log($r);

      // ------------------------------------------------------------
      // Delete inactive regattas
      // ------------------------------------------------------------
      foreach ($deleted_regattas as $r) {
        DB::remove($r);
        self::errln(sprintf('[regattas] permanently deleted regatta %s: %s', $r->id, $r->name));
      }

      // ------------------------------------------------------------
      // Perform all hooks
      // ------------------------------------------------------------
      foreach (self::getHooks() as $hook) {
        $ret = 0;
        passthru($hook, $ret);
        if ($ret != 0)
          throw new RuntimeException("Hook $hook", $ret);
        self::errln("[regattas] Hook $hook run");
      }
      DB::commit();
      DB::resetCache();
    }

    self::errln('[regattas] done');
  }

  /**
   * Removes lock file prior to exiting, one way or another.
   *
   */
  public static function cleanup() {
    foreach (self::$lock_files as $file) {
      if (file_exists($file) && !@unlink($file))
        throw new RuntimeException("(EE) Unable to delete lock file $file while cleaning up!");
    }
  }

  /**
   * Returns list of files that are hooks to this daemon
   *
   * @return Array:String filenames
   * @throws RuntimeException due to IO errors
   */
  private static function getHooks() {
    $list = array();
    $path = sprintf('%s/hooks-installed', dirname(__FILE__));
    if (!is_dir($path))
      return $list;
    if (($hooks = scandir($path)) === false)
      throw new RuntimeException("Unable to read hooks directory: $path");
    foreach ($hooks as $hook) {
      if ($hook == '.' || $hook == '..')
        continue;
      $fname = "$path/$hook";
      if (is_executable($fname))
        $list[] = $fname;
    }
    return $list;
  }

  /**
   * Convenience method fills the school and seasons map
   *
   * 2014-06-24: Also fills conference and seasons map
   *
   * 2024-04-11: Also fills sailor map
   *
   * @param School $school the ID of the school to queue
   * @param Season $season the season to queue
   * @param FullRegatta $regatta associated with the event
   */
  private function queueSchoolSeason(School $school, Season $season, FullRegatta $regatta) {
    if (!isset($this->schools[$school->id])) {
      $this->schools[$school->id] = $school;
      $this->school_seasons[$school->id] = array();
    }
    $this->school_seasons[$school->id][(string)$season] = $season;

    if (!isset($this->conferences[$school->conference->id])) {
      $this->conferences[$school->conference->id] = $school->conference;
      $this->conference_seasons[$school->conference->id] = array();
    }
    $this->conference_seasons[$school->conference->id][(string)$season] = $season;

    // Sailors, too
    $rpManager = $regatta->getRpManager();
    foreach ($regatta->getTeams($school) as $team) {
      foreach ($rpManager->getAttendees($team) as $attendee) {
        if ($attendee->sailor !== null && !isset($attendee->sailor->id, $this->sailorsRp)) {
          $this->sailorsRp[$attendee->sailor->id] = $attendee->sailor;
        }
      }
    }
  }

  private function queueSeason(Season $season, $activity) {
    if (!isset($this->seasons[$season->id])) {
      $this->seasons[$season->id] = $season;
      $this->season_activities[$season->id] = array();
    }
    $this->season_activities[$season->id][$activity] = $activity;
  }

  private function queueRegattaActivity(FullRegatta $reg, $activity) {
    // Score activities are carried out instantaneously
    if (!isset($this->regattas[$reg->id])) {
      $this->regattas[$reg->id] = $reg;
      $this->activities[$reg->id] = array();
    }
    $this->activities[$reg->id][$activity] = $activity;
  }

  // ------------------------------------------------------------
  // List updates, without performing them
  // ------------------------------------------------------------

  /**
   * Produce a list of pending regattal-level updates to standard output
   *
   */
  public function listRegattas() {
    // Merely list the pending requests
    $requests = UpdateManager::getPendingRequests();
    $regattas = array();
    foreach ($requests as $req) {
      if (!isset($regattas[$req->regatta->id])) $regattas[$req->regatta->id] = array();
      if (!isset($regattas[$req->regatta->id][$req->activity]))
        $regattas[$req->regatta->id][$req->activity] = 0;
      $regattas[$req->regatta->id][$req->activity]++;
    }

    // Print them out and exit
    foreach ($regattas as $id => $list) {
      try {
        $reg = DB::get(DB::T(DB::FULL_REGATTA), $id);
        if ($reg === null)
          throw new RuntimeException("Invalid regatta ID $id.");
        printf("--------------------\nRegatta: [%s] %s (%s/%s)%s\n--------------------\n",
               $reg->id, $reg->name, $reg->getSeason(), $reg->nick,
               ($reg->inactive !== null) ? " [deleted]" : "");
        foreach ($list as $activity => $num)
          printf("%12s: %d\n", $activity, $num);
      }
      catch (Exception $e) {
        printf("(EE) %s: %s\n.", $id, $e->getMessage());
      }
    }
  }

  /**
   * Produce a list of pending file-level updates to standard output
   *
   */
  public function listFiles() {
    $requests = UpdateManager::getPendingFiles();
    $files = array(); // map of filename to # of times
    foreach ($requests as $req) {
      if (!array_key_exists($req->file, $files))
        $files[$req->file] = 0;
      $files[$req->file]++;
    }
    foreach ($files as $file => $count)
      printf("File: %s (%d)\n", $file, $count);
  }

  /**
   * Produce a list of pending school-level updates to standard output
   *
   */
  public function listSchools() {
    // Merely list the pending requests
    $requests = UpdateManager::getPendingSchools();
    $schools = array();
    foreach ($requests as $req) {
      $activity = $req->activity;
      if ($req->activity == UpdateSchoolRequest::ACTIVITY_SEASON)
        $activity .= sprintf(' (%s)', $req->season);
      if (!isset($schools[$req->school->id])) $schools[$req->school->id] = array();
      if (!isset($schools[$req->school->id][$activity]))
        $schools[$req->school->id][$activity] = 0;
      $schools[$req->school->id][$activity]++;
    }

    // Print them out and exit
    foreach ($schools as $id => $list) {
      try {
        $reg = DB::getSchool($id);
        if ($reg === null)
          throw new RuntimeException("Invalid school ID $id.");
        printf("--------------------\nSchool: [%s] %s\n--------------------\n", $reg->id, $reg->name);
        foreach ($list as $activity => $num)
          printf("%12s: %d\n", $activity, $num);
      }
      catch (Exception $e) {
        printf("(EE) %s: %s\n.", $id, $e->getMessage());
      }
    }
  }

  /**
   * Produce a list of pending sailor-level updates to standard output
   *
   */
  public function listSailors() {
    // Merely list the pending requests
    $requests = UpdateManager::getPendingSailors();
    $sailors = array();
    foreach ($requests as $req) {
      $activity = $req->activity;
      if ($req->activity == UpdateSailorRequest::ACTIVITY_SEASON)
        $activity .= sprintf(' (%s)', $req->season);
      if (!isset($sailors[$req->sailor->id])) $sailors[$req->sailor->id] = array();
      if (!isset($sailors[$req->sailor->id][$activity]))
        $sailors[$req->sailor->id][$activity] = 0;
      $sailors[$req->sailor->id][$activity]++;
    }

    // Print them out and exit
    foreach ($sailors as $id => $list) {
      try {
        $reg = DB::getSailor($id);
        if ($reg === null)
          throw new RuntimeException("Invalid sailor ID $id.");
        printf("--------------------\nSailor: [%s] %s\n--------------------\n", $reg->id, $reg);
        foreach ($list as $activity => $num)
          printf("%12s: %d\n", $activity, $num);
      }
      catch (Exception $e) {
        printf("(EE) %s: %s\n.", $id, $e->getMessage());
      }
    }
  }

  /**
   * Produce a list of pending conference-level updates to standard output
   *
   */
  public function listConferences() {
    // Merely list the pending requests
    $requests = UpdateManager::getPendingConferences();
    $conferences = array();
    foreach ($requests as $req) {
      $activity = $req->activity;
      if ($req->activity == UpdateConferenceRequest::ACTIVITY_SEASON)
        $activity .= sprintf(' (%s)', $req->season);
      if (!isset($conferences[$req->conference->id])) $conferences[$req->conference->id] = array();
      if (!isset($conferences[$req->conference->id][$activity]))
        $conferences[$req->conference->id][$activity] = 0;
      $conferences[$req->conference->id][$activity]++;
    }

    // Print them out and exit
    foreach ($conferences as $id => $list) {
      try {
        $reg = DB::getConference($id);
        if ($reg === null)
          throw new RuntimeException("Invalid conference ID $id.");
        printf("--------------------\nConference: %s\n--------------------\n", $reg);
        foreach ($list as $activity => $num)
          printf("%12s: %d\n", $activity, $num);
      }
      catch (Exception $e) {
        printf("(EE) %s: %s\n.", $id, $e->getMessage());
      }
    }
  }

  /**
   * Produce a list of pending season-level updates to standard output
   *
   */
  public function listSeasons() {
    // Merely list the pending requests
    $requests = UpdateManager::getPendingSeasons();
    $seasons = array();
    foreach ($requests as $req) {
      $activity = $req->activity;
      if (!isset($seasons[$req->season->id])) $seasons[$req->season->id] = array();
      if (!isset($seasons[$req->season->id][$activity]))
        $seasons[$req->season->id][$activity] = 0;
      $seasons[$req->season->id][$activity]++;
    }

    // Print them out and exit
    foreach ($seasons as $id => $list) {
      try {
        $reg = DB::getSeason($id);
        if ($reg === null)
          throw new RuntimeException("Invalid season ID $id.");
        printf("--------------------\nSeason: [%s] %s\n--------------------\n", $reg->id, $reg->fullString());
        foreach ($list as $activity => $num)
          printf("%12s: %d\n", $activity, $num);
      }
      catch (Exception $e) {
        printf("(EE) %s: %s\n.", $id, $e->getMessage());
      }
    }
  }

  // ------------------------------------------------------------
  // CLI setup
  // ------------------------------------------------------------

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    $axis = null;
    $action = 'run'; // 'list' or 'query'
    if (count($opts) == 0) {
      throw new TSScriptException("Missing arguments.");
    }

    $daemon = false;
    foreach ($opts as $opt) {
      switch ($opt) {
      case '-l':
      case '--list':
        $action = 'list';
        break;

      case '-q':
      case '--query':
        $action = 'query';
        break;

      case '-d':
      case '--daemon':
        $daemon = true;
        break;

      case 'regatta':
      case 'school':
      case 'conference':
      case 'season':
      case 'sailor':
      case 'file':
        if ($axis !== null) {
          throw new TSScriptException("Only one axis may be performed at a time.");
        }
        $axis = $opt;
        break;

      default:
        throw new TSScriptException("Invalid argument provided: $opt");
      }
    }
    if ($axis === null) {
      throw new TSScriptException("No update axis chosen.");
    }
  
    switch ($action) {
    case 'list':
      // ------------------------------------------------------------
      // List the pending requests only
      // ------------------------------------------------------------
      if ($axis == 'regatta') {
        $this->listRegattas();
      }
      elseif ($axis == 'season') {
        $this->listSeasons();
      }
      elseif ($axis == 'school') {
        $this->listSchools();
      }
      elseif ($axis == 'sailor') {
        $this->listSailors();
      }
      elseif ($axis == 'conference') {
        $this->listConferences();
      }
      elseif ($axis == 'file') {
        $this->listFiles();
      }
      break;

    case 'query':
      $lockSuffixes = array(
        'regatta' => 'reg',
        'season' => 'sea',
        'school' => 'sch',
        'sailor' => 'slr',
        'conference' => 'cnf',
        'file' => 'fil',
      );
      try {
        $this->checkLock($lockSuffixes[$axis]);
        exit(72);
      } catch (TSScriptException $e) {
        exit(0);
      }
      break;

    case 'run':
    default:
      if ($axis == 'regatta') {
        $this->runRegattas($daemon);
      }
      elseif ($axis == 'season') {
        $this->runSeasons($daemon);
      }
      elseif ($axis == 'school') {
        $this->runSchools($daemon);
      }
      elseif ($axis == 'sailor') {
        $this->runSailors($daemon);
      }
      elseif ($axis == 'conference') {
        $this->runConferences($daemon);
      }
      elseif ($axis == 'file') {
        $this->runFiles($daemon);
      }
    }
  }

  protected $cli_opts = '[-l] [-d] {regatta|season|school|sailor|conference|file}';
  protected $cli_usage = ' -l --list    only list the pending updates
 -d --daemon  run as a daemon
 -q --query   check lock file; exit with non-zero status if missing

 regatta:    perform pending regatta-level updates
 season:     perform pending season-level updates
 school:     perform pending school-level updates
 sailor:     perform pending sailor-level updates
 conference: perform pending conference-level updates
 file:       perform pending file-level updates';
}
