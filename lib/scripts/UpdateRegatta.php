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
   * Synchronizes the regatta's detail with the data information
   * (those fields in the database prefixed with dt_). Note this will
   * not run for personal regattas, even if requested.
   *
   * @param Regatta $reg the regatta to synchronize
   *
   * @param boolean $full set this to false to only update information
   * about the regatta and not about the ranks (this creates slight
   * efficiency improvement)
   *
   * @param boolean $rp set this to true to also sync the RP
   * @throws InvalidArgumentException
   */
  public static function sync(Regatta $reg) {
    if ($reg->type == Regatta::TYPE_PERSONAL)
      throw new InvalidArgumentException("Personal regattas may never be synced.");
    $divs = $reg->getDivisions();
    if (count($divs) == 0)
      throw new InvalidArgumentException("Cannot update with no divisions.");

    $dreg = new Dt_Regatta();
    $dreg->id = $reg->id;
    $dreg->name = $reg->name;
    $dreg->nick = $reg->nick;
    $dreg->start_time = $reg->start_time;
    $dreg->end_date   = $reg->end_date;
    $dreg->type = $reg->type;
    $dreg->finalized = $reg->finalized;
    $dreg->scoring = $reg->scoring;
    $dreg->participant = $reg->participant;
    $dreg->venue = $reg->venue;

    $races = $reg->getScoredRaces();
    $dreg->num_divisions = count($divs);
    $dreg->num_races = count($reg->getRaces()) / $dreg->num_divisions;

    // hosts and conferences
    $confs = array();
    $hosts = array();
    foreach ($reg->getHosts() as $host) {
      $confs[$host->conference->id] = $host->conference->id;
      $hosts[$host->id] = $host->id;
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

    $dreg->season = $reg->getSeason();

    // status
    $now = new DateTime();
    $end = $dreg->end_date;
    $end->setTime(23,59,59);
    if ($dreg->finalized !== null)
      $dreg->status = Dt_Regatta::STAT_FINAL;
    elseif (count($reg->getUnscoredRaces()) == 0)
      $dreg->status = Dt_Regatta::STAT_FINISHED;
    elseif (!$reg->hasFinishes()) {
      if ($dreg->num_races > 0)
        $dreg->status = Dt_Regatta::STAT_READY;
      else
        $dreg->status = Dt_Regatta::STAT_SCHEDULED;
    }
    else {
      $last_race = $reg->getLastScoredRace();
      $dreg->status = ($last_race === null) ? 'coming' : (string)$last_race;
    }

    $added = !DB::set($dreg);
    return $dreg;
  }

  /**
   * Synchornize the team data for the given regatta
   *
   */
  public static function syncTeams(Regatta $reg) {
    $dreg = DB::get(DB::$DT_REGATTA, $reg->id);
    if ($dreg === null)
      $dreg = self::sync($reg);

    $divs = $reg->getDivisions();
    if (count($divs) == 0)
      throw new InvalidArgumentException("Cannot update with no divisions.");

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
    $dreg = DB::get(DB::$DT_REGATTA, $reg->id);
    if ($dreg === null)
      $dreg = self::sync($reg);

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
   * Deletes the given regatta's information from the public site and
   * the database.  Note that this method is automatically called from
   * <pre>run</pre> if the rotation type is "personal". This method
   * does not touch the season page or any other pages.
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

    // Delete from database
    $r = DB::get(DB::$DT_REGATTA, $reg->id);
    if ($r !== null)
      DB::remove($r);
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
      if (is_dir($dir . '/rotations'))         $has_rotation = true;
      if (is_dir($dir . '/full-scores')) $has_fullscore = true;
      foreach ($has_divs as $div => $val) {
        if (is_dir($dir . '/' . $div))
          $has_divs[$div] = true;
      }
    }

    // Based on the list of activities, determine what files need to
    // be (re)serialized
    $sync = false;
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
      $sync = true;
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
      $sync = true;
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
    if ($sync)       self::sync($reg);
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
