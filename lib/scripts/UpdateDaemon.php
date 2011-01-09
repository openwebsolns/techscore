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
    file_put_contents(self::$lock_file, time());

    // Check queueu
    $requests = UpdateManager::getPendingRequests();
    if (count($requests) == 0) self::cleanup();

    // Sort the requests by regatta
    $regattas = array(); // assoc list of regatta id => list of requests
    foreach ($requests as $r) {
      $reg = new Regatta($r->regatta);
      if (!isset($regattas[$r->regatta]))
	$regattas[$r->regatta] = array();
      $regattas[$r->regatta][] = $r;
    }

    $schools = array(); // list of unique schools

    // For each unique regatta, only execute the last version of each
    // unique activity in the queue, but claim that you did them all
    // anyways (lest they should remain pending later on).
    $seasons = array();  // set of seasons affected
    foreach ($regattas as $id => $requests) {
      $actions = UpdateRequest::getTypes();
      while (count($requests) > 0) {
	$last = array_pop($requests);
	if (isset($actions[$last->activity])) {
	  // Do the action itself
	  unset($actions[$last->activity]);
	  try {
	    $reg = new Regatta($id);
	    if ($last->activity == UpdateRequest::ACTIVITY_SCORE)
	      UpdateRegatta::runScore($reg);
	    elseif ($last->activity == UpdateRequest::ACTIVITY_ROTATION)
	      UpdateRegatta::runRotation($reg);

	    $season = $reg->get(Regatta::SEASON);
	    $seasons[(string)$season->getSeason()] = $season;
	    // Log the successful execution
	    UpdateManager::log($last, 0);

	    foreach ($reg->getTeams() as $team)
	      $schools[$team->school->id] = $team->school;
	  }
	  catch (Exception $e) {
	    // Error: log that too
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
    foreach ($seasons as $season) {
      UpdateSeason::run($season);
      UpdateManager::logSeason($season);
    }

    // Deal with home page
    // @TODO

    // Deal with affected schools
    foreach ($schools as $school)
      UpdateSchool::run($school);

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
  // Make sure, if nothing else, that you at least run cleanup
  // @TODO

  $_SERVER['HTTP_HOST'] = $argv[0];
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');
  UpdateDaemon::run();
}
?>