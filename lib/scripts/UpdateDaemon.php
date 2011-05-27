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
      printf("ID:%d (%s)", self::$REGATTA->id(), self::$REGATTA->get(Regatta::NAME));
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
    file_put_contents(self::$lock_file, date('r'));

    // Check queueu
    $requests = UpdateManager::getPendingRequests();
    if (count($requests) == 0) self::cleanup();

    // Sort the requests by regatta
    $regattas = array(); // assoc list of regatta id => list of requests
    foreach ($requests as $r) {
      if (!isset($regattas[$r->regatta]))
	$regattas[$r->regatta] = array();
      $regattas[$r->regatta][] = $r;
    }

    // For each unique regatta, only execute the last version of each
    // unique activity in the queue, but claim that you did them all
    // anyways (lest they should remain pending later on).
    $UPD_SEASON = array(UpdateRequest::ACTIVITY_SCORE, UpdateRequest::ACTIVITY_DETAILS);
    $UPD_SCHOOL = array(UpdateRequest::ACTIVITY_SCORE,
			UpdateRequest::ACTIVITY_DETAILS,
			UpdateRequest::ACTIVITY_RP);
    $seasons = array();  // set of seasons affected
    $schools = array();  // list of unique schools
    $sync = array();     // set of regattas to also sync. Synching
			 // must happen before either SCORE or DETAILS
			 // get executed
    $UPD_REGATTA = array(UpdateRequest::ACTIVITY_SCORE, UpdateRequest::ACTIVITY_DETAILS);
    foreach ($regattas as $id => $requests) {
      $actions = UpdateRequest::getTypes();
      while (count($requests) > 0) {
	$last = array_pop($requests);
	if (isset($actions[$last->activity])) {
	  // Do the action itself
	  unset($actions[$last->activity]);
	  try {
	    self::$REGATTA = new Regatta($id);
	    if (in_array($last->activity, $UPD_REGATTA) && !isset($sync[$id])) {
	      UpdateRegatta::runSync(self::$REGATTA);
	      $sync[$id] = self::$REGATTA;
	    }
	    UpdateRegatta::run(self::$REGATTA, $last->activity);
	    if (in_array($last->activity, $UPD_SEASON)) {
	      $season = self::$REGATTA->get(Regatta::SEASON);
	      $seasons[(string)$season->getSeason()] = $season;
	    }

	    // Affected schools
	    if (in_array($last->activity, $UPD_SCHOOL)) {
	      foreach (self::$REGATTA->getTeams() as $team)
		$schools[$team->school->id] = $team->school;
	    }

	    UpdateManager::log($last, 0);
	  }
	  catch (Exception $e) {
	    UpdateManager::log($last, $e->getCode(), $e->getMessage());
	  }
	}
	else {
	  // Log the action as having taken place by "assumption"
	  UpdateManager::log($last, -1);
	}
      }
    }

    // Deal now with each affected season.
    $current = new Season(new DateTime());
    foreach ($seasons as $season) {
      UpdateSeason::run($season);
      UpdateManager::logSeason($season);

      // Deal with home page
      if ((string)$season == (string)$current) {
	UpdateFront::run();
	Update404::run();
      }
    }

    // Deal with affected schools
    foreach ($schools as $school)
      UpdateSchool::run($school, new Season(new DateTime()));

    // Remove lock
    self::cleanup();
  }
  private static function cleanup() {
    @unlink(self::$lock_file);
    exit(0);
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  $_SERVER['HTTP_HOST'] = $argv[0];
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');
  // Make sure, if nothing else, that you at least run cleanup
  $old = set_error_handler("UpdateDaemon::errorHandler", E_ALL);
  UpdateDaemon::run();
}
?>
