<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

require_once('AbstractScript.php');

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
 * @author Dayan Paez
 * @version 2010-10-08
 * @package scripts
 */
class Daemon extends AbstractScript {

  private static $lock_files = array(); // full path, used below

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
   * Creates or complains about lock file
   *
   * @param String $suffix the identifier for the lock file
   */
  private function createLock($suffix) {
    self::$lock_files[$suffix] = sprintf("%s/%s-" . $suffix, sys_get_temp_dir(), Conf::$LOCK_FILENAME);
    if (file_exists(self::$lock_files[$suffix])) {
      $pid = file_get_contents(self::$lock_files[$suffix]);
      try {
        $file = new SplFileInfo('/proc/' . $pid);
        if ($file->isReadable()) {
          echo "Daemon is running with PID $pid.\n";
          exit(1);
        }
      } catch (RuntimeException $e) {
        echo "Daemon may be already running with PID $pid (unable to check /proc).\n";
        exit(1);
      }
      if (!@unlink(self::$lock_files[$suffix])) {
        echo "Unable to remove PID file!\n";
        exit(2);
      }
    }

    // Create file lock
    register_shutdown_function("Daemon::cleanup");
    if (file_put_contents(self::$lock_files[$suffix], getmypid()) === false) {
      echo "Unable to create PID file!\n";
      exit(4);
    }
  }

  /**
   * Checks for school-level updates and performs them
   *
   * @param boolean $daemon run in daemon mode
   */
  public function runSchools($daemon = false) {
    $this->createLock('sch');

    while (true) {
      $pending = UpdateManager::getPendingSchools();
      if (count($pending) == 0) {
        if ($daemon) {
          self::errln("Sleeping...");
          DB::commit();
          sleep(73);
          continue;
        }
        break;
      }

      $requests = array();
      // ------------------------------------------------------------
      // Loop through the school requests
      // ------------------------------------------------------------
      require_once('scripts/UpdateBurgee.php');
      require_once('scripts/UpdateSchool.php');
      $PB = new UpdateBurgee();
      $PS = new UpdateSchool();
      foreach ($pending as $r) {
        $requests[] = $r;
        if ($r->activity == UpdateSchoolRequest::ACTIVITY_BURGEE)
          $PB->run($r->school);
        else { // season summary
          // special case: season value is null: do all
          if ($r->season !== null) {
            $PS->run($r->school, $r->season);
            self::errln(sprintf('generated school %s/%-6s %s', $r->season, $r->school->id, $r->school));
          }
          else {
            foreach (DB::getAll(DB::$SEASON) as $season) {
              $PS->run($r->school, $season);
              self::errln(sprintf('generated school %s/%-6s %s', $season, $r->school->id, $r->school));
            }
          }
        }
        self::errln(sprintf("processed school update %10s: %s", $r->school->id, $r->school->name));
      }
      require_once('scripts/UpdateSchoolsSummary.php');
      $P = new UpdateSchoolsSummary();
      $P->run();

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
        self::errln("Hook $hook run");
      }
    }

    self::errln('done');
  }

  /**
   * Checks for school-level updates and performs them
   *
   * @param boolean $daemon run in daemon mode
   */
  public function runSeasons($daemon = false) {
    $this->createLock('sea');

    while (true) {
      $pending = UpdateManager::getPendingSeasons();
      if (count($pending) == 0) {
        if ($daemon) {
          self::errln("Sleeping...");
          DB::commit();
          sleep(37);
          continue;
        }
        break;
      }

      $requests = array();
      // ------------------------------------------------------------
      // Loop through the season requests
      // ------------------------------------------------------------
      require_once('scripts/UpdateSeason.php');
      $P = new UpdateSeason();

      // perform season summary as well?
      $summary = false;
      $front = false;
      $current = Season::forDate(DB::$NOW);
      foreach ($pending as $r) {
        $requests[] = $r;
	if ($r->activity == UpdateSeasonRequest::ACTIVITY_REGATTA)
          $summary = true;

        if ((string)$r->season == (string)$current)
          $front = true;

        $P->run($r->season);
        self::errln(sprintf("processed season update %s: %s", $r->season->id, $r->season->fullString()));
      }
      if ($summary) {
        require_once('scripts/UpdateSeasonsSummary.php');
        $P = new UpdateSeasonsSummary();
        $P->run();
        self::errln('generated seasons summary page');
      }
      // Deal with home page
      if ($front) {
        require_once('scripts/UpdateFront.php');
        $P = new UpdateFront();
        $P->run();
        self::errln('generated front page');
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
        self::errln("Hook $hook run");
      }
    }

    self::errln('done');
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
    $this->createLock('reg');

    while (true) {
      $pending = UpdateManager::getPendingRequests();
      if (count($pending) == 0) {
        if ($daemon) {
          self::errln("Sleeping...");
          DB::commit();
          sleep(17);
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

      // URLs to delete
      $to_delete = array();

      // Regattas to delete from database (due to inactive flag) as a map
      $deleted_regattas = array();
      $delete_threshold = new DateTime('5 minutes ago');

      // ------------------------------------------------------------
      // Loop through the regatta requests
      // ------------------------------------------------------------
      foreach ($pending as $r) {
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

        // If the regatta is personal, but a request still exists, then
        // request the update of the seasons, the schools, and the
        // season summaries, regardless.
        if ($reg->private) {
          $this->queueSeason($season, UpdateSeasonRequest::ACTIVITY_REGATTA);
          foreach ($reg->getTeams() as $team)
            $this->queueSchoolSeason($team->school, $season);
          continue;
        }

        switch ($r->activity) {
          // ------------------------------------------------------------
        case UpdateRequest::ACTIVITY_DETAILS:
        case UpdateRequest::ACTIVITY_FINALIZED:
        case UpdateRequest::ACTIVITY_SEASON:
        case UpdateRequest::ACTIVITY_SCORE:
          $this->queueSeason($season, UpdateSeasonRequest::ACTIVITY_REGATTA);
          foreach ($reg->getTeams() as $team)
            $this->queueSchoolSeason($team->school, $season);
          break;
          // ------------------------------------------------------------
        case UpdateRequest::ACTIVITY_RP:
        case UpdateRequest::ACTIVITY_RANK:
          if ($r->argument !== null)
            $this->queueSchoolSeason(DB::getSchool($r->argument), $season);
          break;
          // ------------------------------------------------------------
          // Rotation and summary do not affect seasons or schools
        }

        // If season change, then check for old season to delete, and
        // queue old seasons as well
        if ($r->activity == UpdateRequest::ACTIVITY_SEASON && $r->argument !== null) {
          $prior_season = DB::getSeason($r->argument);
          if ($prior_season !== null) {
            $this->seasons[$prior_season->id] = $prior_season;
            // Queue for deletion
            $root = sprintf('/%s/%s', $prior_season->id, $reg->nick);
            $to_delete[$root] = $root;

            foreach ($reg->getTeams() as $team)
              $this->queueSchoolSeason($team->school, $prior_season);
          }
        }
      }

      // ------------------------------------------------------------
      // Perform deletions
      // ------------------------------------------------------------
      require_once('scripts/UpdateRegatta.php');
      foreach ($to_delete as $root)
        UpdateRegatta::deleteRegattaFiles($root);

      // ------------------------------------------------------------
      // Perform regatta level updates
      // ------------------------------------------------------------
      $P = new UpdateRegatta();
      foreach ($this->regattas as $id => $reg) {
        $P->run($reg, $this->activities[$id]);
        foreach ($this->activities[$id] as $act)
          self::errln(sprintf("performed activity %s on %4d: %s", $act, $id, $reg->name));
      }

      // ------------------------------------------------------------
      // Queue season updates
      // ------------------------------------------------------------
      foreach ($this->season_activities as $id => $activities) {
        foreach ($activities as $activity) {
          UpdateManager::queueSeason($this->seasons[$id], $activity);
          self::errln(sprintf('queued season %s', $id));
        }
      }

      // ------------------------------------------------------------
      // Queue school updates
      // ------------------------------------------------------------
      foreach ($this->school_seasons as $id => $seasons) {
        foreach ($seasons as $season) {
          UpdateManager::queueSchool($this->schools[$id], UpdateSchoolRequest::ACTIVITY_SEASON, $season);
          self::errln(sprintf('queued school %s/%-6s %s', $season, $id, $this->schools[$id]->nick_name));
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
        self::errln(sprintf('permanently deleted regatta %s: %s', $r->id, $r->name));
      }

      // ------------------------------------------------------------
      // Perform all hooks
      // ------------------------------------------------------------
      foreach (self::getHooks() as $hook) {
        $ret = 0;
        passthru($hook, $ret);
        if ($ret != 0)
          throw new RuntimeException("Hook $hook", $ret);
        self::errln("Hook $hook run");
      }
    }

    self::errln('done');
  }

  /**
   * Removes lock file prior to exiting, one way or another.
   *
   */
  public static function cleanup() {
    foreach (self::$lock_files as $file) {
      if (file_exists($file) && !unlink($file))
	throw new RuntimeException("(EE) Unable to delete lock file $file while cleaning up!");
    }
    exit(0);
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
   * @param School $school the ID of the school to queue
   * @param Season $season the season to queue
   */
  private function queueSchoolSeason(School $school, Season $season) {
    if (!isset($this->schools[$school->id])) {
      $this->schools[$school->id] = $school;
      $this->school_seasons[$school->id] = array();
    }
    $this->school_seasons[$school->id][(string)$season] = $season;
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
        $reg = DB::get(DB::$FULL_REGATTA, $id);
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
  protected $cli_opts = '[-l] [-d] {regatta|season|school}';
  protected $cli_usage = ' -l --list    only list the pending updates
 -d --daemon  run as a daemon

 regatta: perform pending regatta-level updates
 season:  perform pending season-level updates
 school:  perform pending school-level updates';
}

// ------------------------------------------------------------
// When run as a script
// ------------------------------------------------------------
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');
  require_once('public/UpdateManager.php');

  $P = new Daemon();
  $opts = $P->getOpts($argv);
  $axis = null;
  $list = false;
  if (count($opts) == 0)
    throw new TSScriptException("Missing arguments.");

  $daemon = false;
  foreach ($opts as $opt) {
    switch ($opt) {
    case '-l':
    case '--list':
      $list = true;
      break;

    case '-d':
    case '--daemon':
      $daemon = true;
      break;

    case 'regatta':
    case 'school':
    case 'season':
      if ($axis !== null)
	throw new TSScriptException("Only one axis may be performed at a time.");
      $axis = $opt;
      break;

    default:
      throw new TSScriptException("Invalid argument provided: $opt");
    }
  }
  if ($axis === null)
    throw new TSScriptException("No update axis chosen.");
  
  // ------------------------------------------------------------
  // List the pending requests only
  // ------------------------------------------------------------
  if ($list) {
    if ($axis == 'regatta')
      $P->listRegattas();
    elseif ($axis == 'season')
      $P->listSeasons();
    else
      $P->listSchools();
  }
  else {
    if ($axis == 'regatta')
      $P->runRegattas($daemon);
    elseif ($axis == 'season')
      $P->runSeasons($daemon);
    else
      $P->runSchools($daemon);
  }
}
?>
