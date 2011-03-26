<?php
/**
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

require_once(dirname(__FILE__).'/../conf.php');
require_once('mysqli/DB.php');
DBME::setConnection(Preferences::getConnection());

/**
 * Update the given regatta, given as an argument.
 *
 * This update entails checking the regatta against the database and
 * updating either its rotation, its score or both. In addition, if
 * the regatta is not meant to be published, this will also attempt to
 * delete that regatta's folder and contents. In short, a full update.
 *
 * e.g.:
 * <code>
 * php UpdateScore 491 score
 * php UpdateScore 490 # which does both
 * </code>
 *
 * 2011-02-06: For the purpose of efficiency, granularize the regatta
 * update request to handle either detail changes, summary changes,
 * score changes, RP changes, or rotation changes, separately.
 * Depending on the change, UpdateRegatta will rewrite the correct
 * page(s) for that regatta.
 *
 * For example, a scores change would affect the different scores
 * pages and the front page but not the rotation page. A sumary change
 * affects only the front page, but no other pages. An RP change
 * affects the Divisional scores, or if single handed, the full scores
 * and the rotation. Finally, a settings change affects all pages.
 *
 * 2011-03-06: If a regatta has no finishes, do not generate score
 * pages, even if requested.
 *
 * 2011-03-06: Use Dt_Regatta for every action except Sync, obviously
 *
 * @author Dayan Paez
 * @version 2010-08-27
 * @package scripts
 */
class UpdateRegatta {

  /**
   * Synchronizes the regatta's detail with the data information
   * (those fields in the database prefixed with dt_). Note this will
   * not run for personal regattas, even if requested.
   *
   * @param Regatta $reg the regatta to synchronize
   * @param boolean $full set this to false to only update information
   * about the regatta and not about the ranks (this creates slight
   * efficiency incrase)
   *
   * @return boolean true if a new regatta was inserted
   */
  public static function runSync(Regatta $reg, $full = true) {
    if ($reg->get(Regatta::TYPE) == Preferences::TYPE_PERSONAL)
      return;

    $dreg = new Dt_Regatta();
    $dreg->id = $reg->id();
    $dreg->name = $reg->get(Regatta::NAME);
    $dreg->nick = $reg->get(Regatta::NICK_NAME);
    $dreg->start_time = $reg->get(Regatta::START_TIME);
    $dreg->end_date   = $reg->get(Regatta::END_DATE);
    $dreg->type = $reg->get(Regatta::TYPE);
    $dreg->finalized = $reg->get(Regatta::FINALIZED);
    $dreg->scoring = $reg->get(Regatta::SCORING);
    $dreg->participant = $reg->get(Regatta::PARTICIPANT);

    $venue = $reg->get(Regatta::VENUE);
    if ($venue !== null)
      $dreg->venue = DBME::get(DBME::$VENUE, $venue->id);
    unset($venue);
    
    $divs = $reg->getDivisions();
    $races = $reg->getScoredRaces();
    $dreg->num_divisions = count($divs);
    if ($dreg->num_divisions == 0) // don't update at all!
      return;
    $dreg->num_races = count($reg->getRaces()) / $dreg->num_divisions;
    
    // hosts and conferences
    $confs = array();
    $hosts = array();
    foreach ($reg->getHosts() as $host) {
      $confs[$host->school->conference->id] = $host->school->conference->id;
      $hosts[$host->school->id] = $host->school->id;
    }
    $dreg->hosts = implode(',', $hosts);
    $dreg->confs = implode(',', $confs);
    unset($hosts, $confs);

    // boats
    $boats = array();
    foreach ($reg->getBoats() as $boat)
      $boats[$boat->id] = $boat->name;
    $dreg->boats = implode(',', $boats);
    
    if ($reg->isSingleHanded())
      $dreg->singlehanded = 1;
    
    $dreg->season = (string)$reg->get(Regatta::SEASON);

    // status
    $now = new DateTime();
    $now->setTime(0, 0);
    if ($dreg->finalized !== null)
      $dreg->status = 'final';
    elseif ($dreg->start_time > $now)
      $dreg->status = 'coming';
    elseif ($dreg->end_date < $now) {
      $dreg->status = 'finished';
    }
    else {
      $dreg->status = $reg->getLastScoredRace();
    }
    $added = !DBME::set($dreg);

    // ------------------------------------------------------------
    // do the teams: first delete all the old teams
    if ($full === false)
      return;
    $dreg->deleteTeams();

    // add teams
    $dteams = array();
    foreach ($reg->scorer->rank($reg) as $i => $rank) {
      $team = new Dt_Team();
      $dteams[$rank->team->id] = $team;

      $team->id = $rank->team->id;
      $team->regatta = $dreg;
      $team->school = DBME::get(DBME::$SCHOOL, $rank->team->school->id);
      $team->name = $rank->team->name;
      $team->rank = $i + 1;
      $team->rank_explanation = $rank->explanation;
      DBME::set($team);
    }

    // do the team divisions
    foreach ($divs as $div) {
      foreach ($reg->scorer->rank($reg, $div) as $i => $rank) {
	$team_division = new Dt_Team_Division();
	$team_division->team = $dteams[$rank->team->id];
	$team_division->division = $div;
	$team_division->rank = ($i + 1);
	$team_division->explanation = $rank->explanation;

	// Penalty?
	if (($pen = $reg->getTeamPenalty($rank->team, $div)) !== null) {
	  $team_division->penalty = $pen->type;
	  $team_division->comments = $pen->comments;
	}
	DBME::set($team_division);
      }
    }
    return $added;
  }

  /**
   * Deletes the given regatta's information from the public site and
   * the database.  Note that this method is automatically called from
   * <pre>runScore</pre> and <pre>runRotation</pre> if the rotation
   * type is "personal". This method does not touch the season page or
   * any other pages.
   *
   * @param Regatta $reg the regatta whose information to delete.
   */
  public static function runDelete(Regatta $reg) {
    $R = realpath(dirname(__FILE__).'/../../html');
    $season = $reg->get(Regatta::SEASON);
    if ((string)$season == "")
      return;

    // Regatta Nick Name can be empty, if the regatta has always been
    // personal, in which case there is nothing to delete, right?
    $nickname = $reg->get(Regatta::NICK_NAME);
    if (!empty($nickname)) {
      $dirname = "$R/$season/$nickname";
      if (is_dir($dirname) && $dir = @opendir($dirname)) {
        // Delete contents of dir
        while (false !== ($file = readdir($dir))) {
	  if ($file != '.' && $file != '..')
	    @unlink(sprintf('%s/%s', $dirname, $file));
        }
        // Delete directory
        closedir($dir);
        rmdir($dirname);
      }
    }

    // Delete from database
    $r = DBME::get(DBME::$REGATTA, $reg->id());
    if ($r !== null)
      DBME::remove($r);
  }

  /**
   * Updates the regatta's pages according to the activity
   * requested. Will not create scores page if no finishes are registered!
   *
   * @param Regatta $reg the regatta to update
   * @param UpdateRequest::Constant the activity
   */
  public static function run(Regatta $reg, $activity) {
    if ($reg->get(Regatta::TYPE) == Preferences::TYPE_PERSONAL) {
      self::runDelete($reg);
      UpdateSeason::run($reg->get(Regatta::SEASON));
      UpdateSchoolsSummary::run();
      return;
    }

    $D = UpdateRegatta::createDir($reg);
    $M = new ReportMaker($reg);

    switch ($activity) {
    case UpdateRequest::ACTIVITY_SCORE:
      if (!$reg->hasFinishes()) return;
      
      self::createFront($D, $M);
      self::createFull($D, $M);

      // Individual division scores (do not include if singlehanded as
      // this is redundant)
      if (!$reg->isSingleHanded()) {
	foreach ($reg->getDivisions() as $div)
	  self::createDivision($D, $M, $div);
      }
      break;
      // ------------------------------------------------------------
    case UpdateRequest::ACTIVITY_ROTATION:
      $rot = $reg->getRotation();
      if (!$rot->isAssigned())
	throw new RuntimeException(sprintf("Regatta %s (%d) does not have a rotation!",
					   $reg->get(Regatta::NAME), $reg->id()), 8);

      self::createRotation($D, $M);
      break;
      // ------------------------------------------------------------
    case UpdateRequest::ACTIVITY_RP:
      if ($reg->isSinglehanded()) {
	self::createRotation($D, $M);
	if ($reg->hasFinishes()) {
	  self::createFront($D, $M);
	  self::createFull($D, $M);
	}
      }
      else {
	if ($reg->hasFinishes()) {
	  foreach ($reg->getDivisions() as $div)
	    self::createDivision($D, $M, $div);
	}
      }
      break;
      // ------------------------------------------------------------
    case UpdateRequest::ACTIVITY_SUMMARY:
      self::createFront($D, $M);
      break;
      // ------------------------------------------------------------
    case UpdateRequest::ACTIVITY_DETAILS:
      // do them all!
      $rot = $reg->getRotation();
      if ($rot->isAssigned())
	self::createRotation($D, $M);
      if ($reg->hasFinishes()) {
	self::createFront($D, $M);
	self::createFull($D, $M);
      
	// Individual division scores (do not include if singlehanded as
	// this is redundant)
	if (!$reg->isSingleHanded()) {
	  foreach ($reg->getDivisions() as $div)
	    self::createDivision($D, $M, $div);
	}
      }
      break;
      // ------------------------------------------------------------
    case UpdateRequest::ACTIVITY_SYNC:
      UpdateRegatta::runSync($reg);
      break;

    default:
      throw new RuntimeException("Activity $activity not supported.");
    }
  }

  // ------------------------------------------------------------
  // Helper methods for standardized file creation
  // ------------------------------------------------------------

  /**
   * Creates the necessary folders for the given regatta
   *
   * @param Regatta $reg the regatta
   * @return String the filepath root of the regatta's pages
   * @throws RuntimeException when something goes bad
   */
  private static function createDir(Regatta $reg) {
    $R = realpath(dirname(__FILE__).'/../../html');
    if ($R === false)
      throw new RuntimeException("Public folder does not exist.");

    $season = $reg->get(Regatta::SEASON);
    if (!file_exists("$R/$season") && mkdir("$R/$season") === false)
      throw new RuntimeException(sprintf("Unable to make the season folder: %s\n", $season), 2);

    $dirname = "$R/$season/".$reg->get(Regatta::NICK_NAME);
    if (!file_exists($dirname) && mkdir($dirname) === false)
      throw new RuntimeException("Unable to make regatta directory: $dirname\n", 4);

    return $dirname;
  }

  /**
   * Creates and writes the front page (index) in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   */
  private static function createFront($dirname, ReportMaker $maker) {
    $filename = "$dirname/index.html";
    $page = $maker->getScoresPage();
    self::prepMenu($maker->regatta, $page);
    if (count($maker->regatta->getDivisions()) > 1)
      $page->head->add(new XScript('text/javascript', '/inc/js/rank.js'));
    if (@file_put_contents($filename, $page->toXML()) === false)
      throw new RuntimeException(sprintf("Unable to make the regatta report: %s\n", $filename), 8);
  }

  /**
   * Creates and writes the full scores page in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private static function createFull($dirname, ReportMaker $maker) {
    $filename = "$dirname/full-scores.html";
    $page = $maker->getFullPage();
    self::prepMenu($maker->regatta, $page);
    if (@file_put_contents($filename, $page->toXML()) === false)
      throw new RuntimeException(sprintf("Unable to make the regatta full scores: %s\n", $filename), 8);
  }

  /**
   * Creates and writes the division summary page in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @param Division $div the division to write about
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private static function createDivision($dirname, ReportMaker $maker, Division $div) {
    $filename = "$dirname/$div.html";
    $page = $maker->getDivisionPage($div);
    self::prepMenu($maker->regatta, $page);
    if (@file_put_contents($filename, $page->toXML()) === false)
      throw new RuntimeException(sprintf("Unable to make the regatta division score page: %s\n", $filename), 8);
  }

  /**
   * Creates and writes the rotation page in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private static function createRotation($dirname, ReportMaker $maker) {
    $filename = "$dirname/rotations.html";
    $page = $maker->getRotationPage();
    self::prepMenu($maker->regatta, $page);

    if (@file_put_contents($filename, $page->toXML()) === false)
      throw new RuntimeException(sprintf("Unable to make the regatta rotation: %s\n", $filename), 8);
  }

  /**
   * Adds the menu for the given page
   *
   */
  private static function prepMenu(Regatta $reg, TPublicPage $page) {
    // Menu
    $rot = $reg->getRotation();
    if ($rot->isAssigned())
      $page->addMenu(new XA('rotations', "Rotations"));
    if ($reg->hasFinishes()) {
      $page->addMenu(new XA('.', "Report"));
      $page->addMenu(new XA('full-scores', "Full Scores"));
      if (!$reg->isSingleHanded()) {
	foreach ($reg->getDivisions() as $div)
	  $page->addMenu(new XA($div, "$div Scores"));
      }
    }
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  // Arguments
  if (count($argv) < 2 || count($argv) > 3) {
    printf("usage: %s <regatta-id> [score|rotation]\n", $_SERVER['PHP_SELF']);
    exit(1);
  }
  // SETUP PATHS and other CONSTANTS
  $_SERVER['HTTP_HOST'] = $argv[0];
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  $action = UpdateRequest::getTypes();
  if (isset($argv[2])) {
    if (!in_array($argv[2], $action)) {
      printf("Invalid update action requested: %s\n\n", $argv[2]);
      printf("usage: %s <regatta-id> [score|rotation]\n", $_SERVER['PHP_SELF']);
      exit(1);
    }
    $action = array($argv[2]);
  }

  // GET REGATTA: first, check if the regatta exists in Dt_Regatta. If
  // it does not, attempt to find it in Regatta, and run sync, automatically.
  try {
    $REGATTA = new Regatta($argv[1]);
  }
  catch (InvalidArgumentException $e) {
    printf("Invalid regatta ID provided: %s\n", $argv[1]);
    exit(2);
  }
  foreach ($action as $act) {
    try {
      UpdateRegatta::run($REGATTA, $act);
      error_log(sprintf("I/0/%s\t(%d)\t%s: Successful!\n", date('r'), $REGATTA->id(), $act), 3, LOG_UPDATE);
    }
    catch (RuntimeException $e) {
      error_log(sprintf("E/%d/%s\t(%d)\t%s: %s\n", $e->getCode(), date('r'), $argv[1], $act, $e->getMessage()),
		3, LOG_UPDATE);
    }
  }
}
?>
