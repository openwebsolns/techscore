<?php
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
class UpdateDaemon {

  private static $lock_file_template = "ts2-pub.lock";
  private static $lock_file = null; // full path, used below
  private static $REGATTA = null;

  // ------------------------------------------------------------
  // Public pages that need to be updated, after parsing through all
  // the update requests
  // ------------------------------------------------------------

  /**
   * @var Map regatta ID => Regatta objects for reference
   */
  private static $regattas;
  /**
   * @var Map regatta ID => Array:UpdateRequest::CONST. This is the
   * second argument to UpdateRegatta::run.
   */
  private static $activities;
  /**
   * @var Map of season pages to update (ID => Season object)
   */
  private static $seasons;
  /**
   * @var Map of school objects for reference (ID => School)
   */
  private static $schools;
  /**
   * @var Map of school ID => list of seasons to update
   */
  private static $school_seasons;

  /**
   * @var boolean true to print out information about what's happening
   */
  public static $verbose = false;

  /**
   * Error handler which takes care of NOT exiting the script, but
   * rather print the offending regatta.
   *
   */
  public static function errorHandler($errno, $errstr, $errfile, $errline, $context) {
    if ($errno == E_NOTICE)
      return true; // ignore NOTICES, for now
    echo "(EE) + ";
    if (self::$REGATTA !== null)
      printf("ID:%d (%s)", self::$REGATTA->id, self::$REGATTA->name);
    echo "\n";

    $fmt = "     | %6s: %s\n";
    printf($fmt, "Time",   date('Y-m-d H:i:s'));
    printf($fmt, "Number", $errno);
    printf($fmt, "String", $errstr);
    printf($fmt, "File",   $errfile);
    printf($fmt, "Line",   $errline);
    foreach (debug_backtrace() as $list) {
      echo "     +--------------------\n";
      foreach (array('file', 'line', 'class', 'function') as $index) {
        if (isset($list[$index]))
          printf($fmt, ucfirst($index), $list[$index]);
      }
    }
    self::cleanup();
    return true;
  }

  /**
   * Checks for the existance of a file lock. If absent, proceeds to
   * create one, then checks the queue of update requests for pending
   * items. Proceeds to intelligently update the public side of
   * TechScore, and finally removes the file lock so that a second
   * instance of this method can be called.
   *
   */
  public static function run() {
    // Check file lock
    self::$lock_file = sprintf("%s/%s", sys_get_temp_dir(), self::$lock_file_template);
    if (file_exists(self::$lock_file)) {
      die("Remove lockfile to proceed! (Created: " . file_get_contents(self::$lock_file) . ")\n");
    }

    // Create file lock
    register_shutdown_function("UpdateDaemon::cleanup");
    if (file_put_contents(self::$lock_file, date('r')) === false)
      throw new RuntimeException("Unable to create lock file!");

    // ------------------------------------------------------------
    // Initialize the set of updates that need to be created
    // ------------------------------------------------------------
    $requests = array(); // list of processed requests
    // For efficiency, track the activities performed for each
    // regatta, so as not to re-analyze
    $hashes = array();

    self::$regattas = array();
    self::$activities = array();

    self::$seasons = array();
    self::$schools = array();
    self::$school_seasons = array();

    // Update the seasons summary page
    $seasons_summary = false;

    // ------------------------------------------------------------
    // Loop through the requests
    // ------------------------------------------------------------
    foreach (UpdateManager::getPendingRequests() as $r) {
      $log = new UpdateLog();
      $log->request = $r;
      $log->return_code = 0;
      $requests[] = $log;

      $reg = $r->regatta;

      $hash = $r->hash();
      if (isset($hashes[$hash]))
        continue;
      $hashes[$hash] = $r;

      self::queueRegattaActivity($reg, $r->activity);
      $season = $reg->getSeason();

      // If the regatta is personal, but a request still exists, then
      // request the update of the seasons, the schools, and the
      // season summaries, regardless.
      if ($reg->type == Regatta::TYPE_PERSONAL) {
        $seasons_summary = true;
        self::$seasons[$season->id] = $season;
        foreach ($reg->getTeams() as $team)
          self::queueSchoolSeason($team->school, $season);
        continue;
      }

      switch ($r->activity) {
        // ------------------------------------------------------------
      case UpdateRequest::ACTIVITY_DETAILS:
        $seasons_summary = true;
      case UpdateRequest::ACTIVITY_SCORE:
        self::$seasons[$season->id] = $season;
        foreach ($reg->getTeams() as $team)
          self::queueSchoolSeason($team->school, $season);
        break;
        // ------------------------------------------------------------
      case UpdateRequest::ACTIVITY_RP:
        if ($r->argument !== null)
          self::queueSchoolSeason(DB::getSchool($r->argument), $season);
        break;
        // ------------------------------------------------------------
        // Rotation and summary do not affect seasons or schools
      }
    }

    // ------------------------------------------------------------
    // Perform regatta level updates
    // ------------------------------------------------------------
    require_once('scripts/UpdateRegatta.php');
    foreach (self::$regattas as $id => $reg) {
      UpdateRegatta::run($reg, self::$activities[$id]);
      foreach (self::$activities[$id] as $act)
        self::report(sprintf('performed activity %s on regatta %s: %s', $act, $id, $reg->name));
    }

    // ------------------------------------------------------------
    // Perform school updates
    // ------------------------------------------------------------
    require_once('scripts/UpdateSchool.php');
    foreach (self::$school_seasons as $id => $seasons) {
      foreach ($seasons as $season) {
        UpdateSchool::run(self::$schools[$id], $season);
        self::report(sprintf('generated school (%s/%-6s) %s', $season, $id, self::$schools[$id]->nick_name));
      }
    }

    // ------------------------------------------------------------
    // Perform season updates
    // ------------------------------------------------------------
    require_once('scripts/UpdateSeason.php');
    $current = Season::forDate(DB::$NOW);
    foreach (self::$seasons as $season) {
      UpdateSeason::run($season);
      UpdateManager::logSeason($season);
      self::report('generated season ' . $season);

      // Deal with home page
      if ((string)$season == (string)$current) {
        require_once('scripts/UpdateFront.php');
        require_once('scripts/Update404.php');
        UpdateFront::run();
        Update404::run();
        self::report('generated front and 404 page');
      }
    }

    // ------------------------------------------------------------
    // Perform all seasons summary update
    // ------------------------------------------------------------
    if ($seasons_summary) {
      require_once('scripts/UpdateSeasonsSummary.php');
      UpdateSeasonsSummary::run();
      self::report('generated all-seasons summary');
    }

    // ------------------------------------------------------------
    // Mark all requests as completed
    // ------------------------------------------------------------
    DB::insertAll($requests);

    self::report('done');
  }

  /**
   * Removes lock file prior to exiting, one way or another.
   *
   */
  public static function cleanup() {
    if (file_exists(self::$lock_file) && !unlink(self::$lock_file))
      throw new RuntimeException("(EE) Unable to delete lock file while cleaning up!");
    exit(0);
  }

  /**
   * Prints the given message, if $verbose is set to true
   *
   * @param String $mes the message to print out
   */
  private static function report($mes) {
    if (self::$verbose === false)
      return;

    if (self::$REGATTA !== null)
      printf("%4d: %s\n", self::$REGATTA->id, $mes);
    else
      print "$mes\n";
  }

  /**
   * Convenience method fills the school and seasons map
   *
   * @param School $school the ID of the school to queue
   * @param Season $season the season to queue
   */
  private static function queueSchoolSeason(School $school, Season $season) {
    if (!isset(self::$schools[$school->id])) {
      self::$schools[$school->id] = $school;
      self::$school_seasons[$school->id] = array();
    }
    self::$school_seasons[$school->id][(string)$season] = $season;
  }

  private static function queueRegattaActivity(Regatta $reg, $activity) {
    if (!isset(self::$regattas[$reg->id])) {
      self::$regattas[$reg->id] = $reg;
      self::$activities[$reg->id] = array();
    }
    self::$activities[$reg->id][$activity] = $activity;
  }
}

// ------------------------------------------------------------
// When run as a script
// ------------------------------------------------------------
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');
  require_once('public/UpdateManager.php');

  $opts = getopt('vl');
  // ------------------------------------------------------------
  // List the pending requests only
  // ------------------------------------------------------------
  if (isset($opts['l'])) {
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
    exit(0);
  }

  // ------------------------------------------------------------
  // Actually perform the requests
  // ------------------------------------------------------------
  // Make sure, if nothing else, that you at least run cleanup
  $old = set_error_handler("UpdateDaemon::errorHandler", E_ALL);

  if (isset($opts['v']))
    UpdateDaemon::$verbose = true;
  try {
    UpdateDaemon::run();
  }
  catch (Exception $e) {
    UpdateDaemon::errorHandler($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine(), 0);
  }
}
?>
