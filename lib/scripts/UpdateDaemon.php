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
    file_put_contents(self::$lock_file, date('r'));

    // Check queueu
    $requests = UpdateManager::getPendingRequests();
    if (count($requests) == 0) self::cleanup();

    // Sort the requests by regatta, and then by type
    // assoc list of regatta id => list of unique requests
    $regattas = array();
    
    // Synching: set of regattas to also sync. Syncing must happen
    // before either SCORE or DETAILS gets executed. Each entry in
    // this array is indexed by the ID of the regatta and consists of
    // an array of three arguments, corresponding to the three
    // possible arguments of UpdateRegatta::runSync.
    //
    // The first argument is the boolean value for syncing scores, and
    // the third is the boolean value for syncing RPs.
    $sync = array();
    foreach ($requests as $r) {
      if (!isset($regattas[$r->regatta]))
	$regattas[$r->regatta] = array();
      if ($r->activity == UpdateRequest::ACTIVITY_SYNC)
	$sync[$r->regatta] = array($r->regatta, true, true);
      else {
	$regattas[$r->regatta][] = $r;

	// handle syncing
	if ($r->activity == UpdateRequest::ACTIVITY_DETAILS && !isset($sync[$r->regatta]))
	  $sync[$r->regatta] = array(false, false);
	elseif ($r->activity == UpdateRequest::ACTIVITY_SCORE) {
	  if (!isset($sync[$r->regatta])) $sync[$r->regatta] = array(false, false);
	  $sync[$r->regatta][0] = true;
	}
	elseif ($r->activity == UpdateRequest::ACTIVITY_RP) {
	  if (!isset($sync[$r->regatta])) $sync[$r->regatta] = array(false, false);
	  $sync[$r->regatta][1] = true;
	}
      }
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

    require_once('scripts/UpdateRegatta.php');
    foreach ($regattas as $id => $requests) {
      $actions = UpdateRequest::getTypes();
      while (count($requests) > 0) {
	$last = array_pop($requests);
	if (isset($actions[$last->activity])) {
	  // Action is still available, do it
	  unset($actions[$last->activity]);
	  try {
	    self::$REGATTA = new Regatta($id);
	    if (isset($sync[$id])) {
	      UpdateRegatta::runSync(self::$REGATTA, $sync[$id][0], $sync[$id][1]);
	      self::report('synced');
	    }
	    $new = UpdateRegatta::run(self::$REGATTA, $last->activity);
	    self::report(sprintf('performed (%d) %s', $last->id, $last->activity));

	    // Cascade update to season
	    if (in_array($last->activity, $UPD_SEASON) || $new) {
	      $season = self::$REGATTA->get(Regatta::SEASON);
	      $seasons[$season->id] = $season;
	    }

	    // Affected schools
	    if (in_array($last->activity, $UPD_SCHOOL)) {
	      if ($last->activity == UpdateRequest::ACTIVITY_RP && $last->argument !== null)
		$schools[$last->argument] = DB::getSchool($last->argument);
	      else {
		foreach (self::$REGATTA->getTeams() as $team)
		  $schools[$team->school->id] = $team->school;
	      }
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
	  self::report(sprintf('assumed (%d) %s', $last->id, $last->activity));
	}
      }
    }
    self::$REGATTA = null;

    // Deal now with each affected season.
    require_once('scripts/UpdateSeason.php');
    $current = new Season(new DateTime());
    foreach ($seasons as $season) {
      UpdateSeason::run($season);
      UpdateManager::logSeason($season);
      self::report('generated ' . $season);

      // Deal with home page
      if ((string)$season == (string)$current) {
	require_once('scripts/UpdateFront.php');
	require_once('scripts/Update404.php');
	UpdateFront::run();
	Update404::run();
	self::report('generate front and 404 page');
      }
    }

    // Deal with affected schools
    require_once('scripts/UpdateSchool.php');
    foreach ($schools as $school) {
      UpdateSchool::run($school, new Season(new DateTime()));
      self::report(sprintf('generated school (%s) %s', $school->id, $school->nick_name));
    }

    // Remove lock
    self::cleanup();
    self::report('done OK');
  }
  private static function cleanup() {
    @unlink(self::$lock_file);
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
      printf("%4d: %s\n", self::$REGATTA->id(), $mes);
    else
      print "$mes\n";
  }
}

// ------------------------------------------------------------
// When run as a script
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
      if (!isset($regattas[$req->regatta])) $regattas[$req->regatta] = array();
      if (!isset($regattas[$req->regatta][$req->activity]))
	$regattas[$req->regatta][$req->activity] = 0;
      $regattas[$req->regatta][$req->activity]++;
    }

    // Print them out and exit
    foreach ($regattas as $id => $list) {
      try {
	$reg = new Regatta($id);
	printf("--------------------\nRegatta: [%s] %s (%s/%s)\n--------------------\n",
	       $reg->id(), $reg->get(Regatta::NAME), $reg->get(Regatta::SEASON), $reg->get(Regatta::NICK_NAME));
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
