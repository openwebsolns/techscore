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
 * The overall suggestion would be to either call this script from an
 * actual daemonized process (this script is NOT a Linux daemon, per
 * se); or call the script uring a Cron job. The script will create a
 * file lock in the system's temp directory (as returned by PHP's
 * sys_get_temp_dir call) so that only one instance of this script is
 * run at a time. Thus, it should be no problem to set the cronjob to
 * cycle relatively quickly during high traffic times like the
 * weekend, as only one instance of this process will actually execute
 * at a time.
 *
 * This script has been written in the style of the other update
 * scripts in that it can be run from the command line or as a
 * "library" call from a different script, by using the class's 'run'
 * method.
 *
 * 2011-01-03: When a regatta is updated, also update all the info
 * pages for the schools associated with that regatta.
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
   * @var Map of school objects for reference (ID => School)
   */
  private $schools;
  /**
   * @var Map of school ID => list of seasons to update
   */
  private $school_seasons;

  /**
   * Checks for school-level updates and performs them
   *
   */
  public function runSchools() {
    self::$lock_files['sch'] = sprintf("%s/%s-sch", sys_get_temp_dir(), Conf::$LOCK_FILENAME);
    if (file_exists(self::$lock_files['sch'])) {
      die("Remove lockfile to proceed! (Created: " . file_get_contents(self::$lock_files['sch']) . ")\n");
    }

    // Create file lock
    register_shutdown_function("Daemon::cleanup");
    if (file_put_contents(self::$lock_files['sch'], date('r')) === false)
      throw new RuntimeException("Unable to create lock file!");

    $requests = array();
    // ------------------------------------------------------------
    // Loop through the school requests
    // ------------------------------------------------------------
    $pending = UpdateManager::getPendingSchools();
    if (count($pending) > 0) {
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
  public function runRegattas() {
    // Check file lock
    self::$lock_files['reg'] = sprintf("%s/%s-reg", sys_get_temp_dir(), Conf::$LOCK_FILENAME);
    if (file_exists(self::$lock_files['reg'])) {
      die("Remove lockfile to proceed! (Created: " . file_get_contents(self::$lock_files['reg']) . ")\n");
    }

    // Create file lock
    register_shutdown_function("Daemon::cleanup");
    if (file_put_contents(self::$lock_files['reg'], date('r')) === false)
      throw new RuntimeException("Unable to create lock file!");

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
    $this->schools = array();
    $this->school_seasons = array();

    // Update the seasons summary page
    $seasons_summary = false;

    // ------------------------------------------------------------
    // Loop through the regatta requests
    // ------------------------------------------------------------
    foreach (UpdateManager::getPendingRequests() as $r) {
      $requests[] = $r;

      $reg = $r->regatta;

      $hash = $r->hash();
      if (isset($hashes[$hash]))
        continue;
      $hashes[$hash] = $r;

      $this->queueRegattaActivity($reg, $r->activity);
      $season = $reg->getSeason();

      // If the regatta is personal, but a request still exists, then
      // request the update of the seasons, the schools, and the
      // season summaries, regardless.
      if ($reg->type == Regatta::TYPE_PERSONAL) {
        $seasons_summary = true;
        $this->seasons[$season->id] = $season;
        foreach ($reg->getTeams() as $team)
          $this->queueSchoolSeason($team->school, $season);
        continue;
      }

      switch ($r->activity) {
        // ------------------------------------------------------------
      case UpdateRequest::ACTIVITY_DETAILS:
        $seasons_summary = true;
      case UpdateRequest::ACTIVITY_SCORE:
        $this->seasons[$season->id] = $season;
        foreach ($reg->getTeams() as $team)
          $this->queueSchoolSeason($team->school, $season);
        break;
        // ------------------------------------------------------------
      case UpdateRequest::ACTIVITY_RP:
        if ($r->argument !== null)
          $this->queueSchoolSeason(DB::getSchool($r->argument), $season);
        break;
        // ------------------------------------------------------------
        // Rotation and summary do not affect seasons or schools
      }
    }

    // ------------------------------------------------------------
    // Perform regatta level updates
    // ------------------------------------------------------------
    require_once('scripts/UpdateRegatta.php');
    $P = new UpdateRegatta();
    foreach ($this->regattas as $id => $reg) {
      $P->run($reg, $this->activities[$id]);
      foreach ($this->activities[$id] as $act)
        self::errln(sprintf("performed activity %s on %4d: %s", $act, $id, $reg->name));
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
    // Perform season updates
    // ------------------------------------------------------------
    if (count($this->seasons) > 0) {
      require_once('scripts/UpdateSeason.php');
      $P = new UpdateSeason();
      $current = Season::forDate(DB::$NOW);
      foreach ($this->seasons as $season) {
        $P->run($season);
        self::errln('generated season ' . $season);

        // Deal with home page
        if ((string)$season == (string)$current) {
          require_once('scripts/UpdateFront.php');
          $P = new UpdateFront();
          $P->run();

          require_once('scripts/Update404.php');
          $P = new Update404();
          $P->run(true);
          self::errln('generated front and 404 page');
        }
      }
    }

    // ------------------------------------------------------------
    // Perform all seasons summary update
    // ------------------------------------------------------------
    if ($seasons_summary) {
      require_once('scripts/UpdateSeasonsSummary.php');
      $P = new UpdateSeasonsSummary();
      $P->run();
      self::errln('generated all-seasons summary');
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

  private function queueRegattaActivity(Regatta $reg, $activity) {
    // Score activities are carried out instantaneously
    if ($activity == UpdateRequest::ACTIVITY_SCORE)
      return;
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
        $reg = DB::getRegatta($id);
        if ($reg === null)
          throw new RuntimeException("Invalid regatta ID $id.");
        printf("--------------------\nRegatta: [%s] %s (%s/%s)\n--------------------\n",
               $reg->id, $reg->name, $reg->getSeason(), $reg->nick);
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

  // ------------------------------------------------------------
  // CLI setup
  // ------------------------------------------------------------
  protected $cli_opts = '[-l] {regatta|school}';
  protected $cli_usage = ' -l --list   only list the pending updates

 regatta: perform pending regatta-level updates
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

  foreach ($opts as $opt) {
    switch ($opt) {
    case '-l':
    case '--list':
      $list = true;
      break;

    case 'regatta':
    case 'school':
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
    else
      $P->listSchools();
  }
  else {
    if ($axis == 'regatta')
      $P->runRegattas();
    else
      $P->runSchools();
  }
}
?>
