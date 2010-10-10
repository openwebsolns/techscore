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
    if (file_exists($filename)) {
      die("Remove lockfile to proceed! (Created: " . file_get_contents(self::$lock_file) . ")\n");
    }

    // Create file lock
    file_put_contents(self::$lock_file, time());

    // Check queueu
    $requests = UpdateManager::getPendingRequests();
    if (count($requests) == 0) self::cleanup();

    // Sort the requests by regatta and season
    $seasons = array();  // set of seasons affected
    $regattas = array(); // assoc list of regatta id => list of requests
    foreach ($requests as $r) {
      $reg = new Regatta($r->regatta);
      $seasons[(string)$reg->getSeason()] = $reg->getSeason();
      if (!isset($regattas[$r->regatta]))
	$regattas[$r->regatta] = array();
      $regattas[$r->regatta][] = $r;
    }

    // For each unique regatta, only execute the last version of each
    // unique activity in the queue, but claim that you did them all
    // anyways (lest they should remain pending later on). In the
    // special case that the last activity is 'delete', do not execute
    // any previous ones. Otherwise, execute either 'score' or
    // 'rotation' as needed.
    foreach ($regattas as $id => $requests) {
      $last = array_pop($requests);
      if ($last->activity == 'delete') {
	
      }
    }

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
  UpdateDaemon::run();
}
?>