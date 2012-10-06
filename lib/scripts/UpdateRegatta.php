<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

require_once(dirname(__FILE__).'/../conf.php');
require_once('regatta/PublicDB.php');
require_once('public/UpdateRequest.php');
require_once('public/ReportMaker.php');
require_once('xml5/TPublicPage.php');

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
   * Synchornize the team data for the given regatta
   *
   */
  public static function syncTeams(Regatta $reg) {
    $dreg = $reg->getData();
    if ($dreg->num_divisions == 0)
      return;

    // ------------------------------------------------------------
    // Synchronize the teams. Track team_divs which is the list of all
    // the team divisions so that we can use them when syncing RP
    // information.  Also track the team objects for the same reason,
    // with these indexed by the ID of the dt_team_division object
    $team_divs = array();
    $team_objs = array();

    $dteams = array();
    foreach ($dreg->getTeams() as $team)
      $dteams[$team->id] = $team;

    // add teams
    $dteams = array();
    foreach ($reg->scorer->rank($reg) as $i => $rank) {
      if (!isset($dteams[$rank->team->id])) {
        $team = new Dt_Team();
        $dteams[$rank->team->id] = $team;
      }
      $team = $dteams[$rank->team->id];

      $team->id = $rank->team->id;
      $team->regatta = $dreg;
      $team->school = DB::get(DB::$SCHOOL, $rank->team->school->id);
      $team->name = $rank->team->name;
      $team->rank = $i + 1;
      $team->rank_explanation = $rank->explanation;
      DB::set($team);
    }

    // do the team divisions
    foreach ($divs as $div) {
      foreach ($reg->scorer->rank($reg, $div) as $i => $rank) {
        $team_division = $dteams[$rank->team->id]->getRank($div);
        if ($team_division === null)
          $team_division = new Dt_Team_Division();

        $team_division->team = $dteams[$rank->team->id];
        $team_division->division = $div;
        $team_division->rank = ($i + 1);
        $team_division->explanation = $rank->explanation;
	$team_division->penalty = null;
	$team_division->comments = null;

        // Penalty?
        if (($pen = $reg->getTeamPenalty($rank->team, $div)) !== null) {
          $team_division->penalty = $pen->type;
          $team_division->comments = $pen->comments;
        }
        DB::set($team_division);
        $team_divs[] = $team_division;
        $team_objs[$team_division->id] = $rank->team;
      }
    }
  }

  /**
   * Sync the RP information for the given regatta
   *
   */
  public static function syncRP(Regatta $reg) {
    $dreg = $reg->getData();
    if ($dreg->num_divisions == 0)
      return;

    $team_divs = array();
    foreach ($reg->getDivisions() as $div) {
      foreach ($dreg->getRanks($div) as $team) {
        $team_divs[] = $team;
        $team_objs[$team->id] = $reg->getTeam($team->team->id);
      }
    }

    $rpm = $reg->getRpManager();
    foreach ($team_divs as $team) {
      $team->team->resetRP($team->division);
      foreach (array(RP::SKIPPER, RP::CREW) as $role) {
        $rps = $rpm->getRP($team_objs[$team->id], Division::get($team->division), $role);
        foreach ($rps as $rp) {
          $drp = new Dt_Rp();
          $drp->sailor = DB::getSailor($rp->sailor->id);
          $drp->team_division = $team;
          $drp->boat_role = $role;
          $drp->race_nums = $rp->races_nums;
          DB::set($drp);
        }
      }
    }
  }

  /**
   * Deletes the given regatta's information from the public site
   *
   * @param Regatta $reg the regatta whose information to delete.
   */
  public static function runDelete(Regatta $reg) {
    $R = realpath(dirname(__FILE__).'/../../html');
    $season = $reg->getSeason();
    if ((string)$season == "")
      return;

    // Regatta Nick Name can be empty, if the regatta has always been
    // personal, in which case there is nothing to delete, right?
    $nickname = $reg->nick;
    if (!empty($nickname)) {
      $dirname = "$R/$season/$nickname";
      if (!self::rm_r($dirname))
        throw new RuntimeException("Unable to remove files rooted at $dirname.");
    }
  }

  /**
   * Recursively remove a filepath
   *
   * @param String $root the directory to remove
   * @return boolean true on success
   */
  private static function rm_r($root) {
    if (!is_dir($root))
      return true;

    $d = opendir($root);
    if ($d === false)
      return false;

    $res = true;
    while (($file = readdir($d)) !== false) {
      if ($file != '.' && $file != '..') {
        if (is_dir("$root/$file"))
          $res = ($res && self::rm_r("$root/$file"));
        else {
          if (($my_res = unlink("$root/$file")) === true) {
            // message?
          }
          $res = ($res && $my_res);
        }
      }
    }
    closedir($d);
    $res = ($res && rmdir($root));
    return $res;
  }

  /**
   * Updates the regatta's pages according to the activity
   * requested. Will not create scores page if no finishes are registered!
   *
   * @param Regatta $reg the regatta to update
   * @param Array:UpdateRequest::Constant the activities to execute
   */
  public static function run(Regatta $reg, Array $activities) {
    if ($reg->type == Regatta::TYPE_PERSONAL) {
      self::runDelete($reg);
      return;
    }

    // In order to maintain all the regatta pages in sync, it is
    // necessary to first check what pages have been serialized
    $has_dir = false;
    $has_rotation = false;
    $has_fullscore = false;
    $has_divs = array((string)Division::A() => false,
                      (string)Division::B() => false,
                      (string)Division::C() => false,
                      (string)Division::D() => false);
    $dir = sprintf('%s/html%s', dirname(dirname(dirname(__FILE__))), $reg->getURL());
    if (is_dir($dir)) {
      $has_dir = true;
      if (is_dir($dir . '/rotations'))   $has_rotation = true;
      if (is_dir($dir . '/full-scores')) $has_fullscore = true;
      foreach ($has_divs as $div => $val) {
        if (is_dir($dir . '/' . $div))
          $has_divs[$div] = true;
      }
    }

    // Based on the list of activities, determine what files need to
    // be (re)serialized
    $sync_teams = false;
    $sync_rp = false;

    $rotation = false;
    $divisions = false;
    $front = false;
    $full = false;
    $rot = $reg->getRotation();
    if (in_array(UpdateRequest::ACTIVITY_ROTATION, $activities)) {
      if ($rot->isAssigned()) {
        $rotation = true;

        // This check takes care of the fringe case when the rotation
        // is created AFTER there are already scores, etc.
        if (!$has_rotation) {
          $front = true;
          if ($reg->hasFinishes()) {
            $full = true;
            if (!$reg->isSingleHanded())
              $divisions = true;
          }
        }
      }
      elseif ($has_rotation) {
        // What if the rotation was removed?
        $season = $reg->getSeason();
        if ($season !== null && $reg->nick !== null) {
          $root = sprintf('%s/html/%s/%s', dirname(dirname(dirname(__FILE__))), $season, $reg->nick);
          self::rm_r($root . '/rotations');
        }

        $front = true;
        if ($reg->hasFinishes()) {
          $full = true;
          if (!$reg->isSingleHanded())
            $divisions = true;
        }
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_SCORE, $activities)) {
      $sync_teams = true;
      $front = true;
      if ($reg->hasFinishes()) {
        $full = true;

        // Individual division scores (do not include if singlehanded as
        // this is redundant)
        if (!$reg->isSingleHanded())
          $divisions = true;

        // Check for the case when this is the first time a score is
        // entered, thus updating the rotation page as well
        if (!$has_fullscore && $rot->isAssigned())
          $rotation = true;
      }
      else {
        // It is possible that all finishes were removed, therefore,
        // delete all such directories, and regenerate rotation page
        $rotation = true;
        $season = $reg->getSeason();
        if ($season !== null && $reg->nick !== null) {
          $root = sprintf('%s/html/%s/%s', dirname(dirname(dirname(__FILE__))), $season, $reg->nick);
          self::rm_r($root . '/full-scores');
          self::rm_r($root . '/A');
          self::rm_r($root . '/B');
          self::rm_r($root . '/C');
          self::rm_r($root . '/D');
        }
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_RP, $activities)) {
      $sync_rp = true;
      if ($reg->isSinglehanded()) {
        $rotation = true;
        if ($reg->hasFinishes()) {
          $front = true;
          $full = true;
        }
      }
      elseif ($reg->hasFinishes())
        $divisions = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_SUMMARY, $activities)) {
      $front = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_DETAILS, $activities)) {
      // do them all!
      $front = true;
      if ($rot->isAssigned())
        $rotation = true;
      if ($reg->hasFinishes()) {
        $full = true;

        // Individual division scores (do not include if singlehanded as
        // this is redundant)
        if (!$reg->isSingleHanded())
          $divisions = true;
      }
    }

    // ------------------------------------------------------------
    // Perform the updates
    // ------------------------------------------------------------
    if ($sync_teams) self::syncTeams($reg);
    if ($sync_rp)    self::syncRP($reg);

    $D = self::createDir($reg);
    $M = new ReportMaker($reg);

    if ($rotation)   self::createRotation($D, $M);
    if ($front)      self::createFront($D, $M);
    if ($full)       self::createFull($D, $M);
    if ($divisions) {
      foreach ($reg->getDivisions() as $div)
        self::createDivision($D, $M, $div);
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

    $dirname = sprintf('%s%s', $R, $reg->getURL());
    if (!file_exists($dirname)) {
      if (!is_dir($dirname) && mkdir($dirname, 0777, true) === false)
        throw new RuntimeException("Unable to make regatta directory: $dirname\n", 4);
    }
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
    $path = "$dirname/full-scores";
    if (!is_dir($path) && mkdir($path, 0777, true) === false)
      throw new RuntimeException("Unable to make the full scores directory: $path", 8);
    $filename = "$path/index.html";
    $page = $maker->getFullPage();
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
    $path = "$dirname/$div";
    if (!is_dir($path) && mkdir($path, 0777, true) === false)
      throw new RuntimeException("Unable to make the $div division directory: $path", 8);
    $filename = "$path/index.html";
    $page = $maker->getDivisionPage($div);
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
    $path = "$dirname/rotations";
    if (!is_dir($path) && mkdir($path, 0777, true) === false)
      throw new RuntimeException("Unable to make the rotations directory: $path", 8);
    $filename = "$path/index.html";
    $page = $maker->getRotationPage();

    if (@file_put_contents($filename, $page->toXML()) === false)
      throw new RuntimeException(sprintf("Unable to make the regatta rotation: %s\n", $filename), 8);
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  array_shift($argv);
  // Arguments
  if (count($argv) < 2) {
    printf("usage: %s <regatta-id> [score|rotation [...]]\n", $_SERVER['PHP_SELF']);
    exit(1);
  }
  // SETUP PATHS and other CONSTANTS
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  $REGATTA = DB::getRegatta(array_shift($argv));
  if ($REGATTA === null) {
    printf("Invalid regatta ID provided: %s\n", $argv[1]);
    exit(2);
  }

  $pos_actions = UpdateRequest::getTypes();
  $actions = array();
  foreach ($argv as $opt) {
    if (!in_array($opt, $pos_actions)) {
      printf("Invalid update action requested: %s\n\n", $opt);
      printf("usage: %s <regatta-id> [score|rotation [...]]\n", $_SERVER['PHP_SELF']);
      exit(1);
    }
    $action[$opt] = $opt;
  }
  UpdateRegatta::run($REGATTA, $action);
}
?>
