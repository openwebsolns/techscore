<?php
namespace scripts;

use \charts\RegattaChartCreator;
use \scripts\AbstractScript;

use \DB;
use \DateTime;
use \Division;
use \FullRegatta;
use \Regatta;
use \ReportMaker;
use \TPublicPage;
use \TSScriptException;
use \TweetFactory;
use \UpdateRequest;

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
 * 2012-11-07: For combined division, use the special ranker when (a)
 * performing syncs and (b) displaying
 *
 * @author Dayan Paez
 * @version 2010-08-27
 * @package scripts
 */
class UpdateRegatta extends AbstractScript {

  /**
   * Compare list of cached URLs to current ones
   *
   * Delete URLs that are no longer valid, and save the current
   * ones. Returns list of affected URLs, indexed by the URL, with
   * value indicating ADDED (true) or DELETED (false)
   *
   * @param FullRegatta $reg the regatta to save
   * @return Array:String:boolean affected URLs
   */
  public function syncUrls(FullRegatta $reg) {
    $actual = $reg->calculatePublicPages();
    $saved = $reg->getPublicPages();

    $affected = array();
    $delete = array();
    foreach ($saved as $url) {
      if (!in_array($url, $actual)) {
        $delete[] = $url;
        $affected[$url] = false;
      }
    }
    foreach ($actual as $url) {
      if (!in_array($url, $saved)) {
        $affected[$url] = true;
      }
    }
    foreach ($delete as $url)
      self::remove($url);
    $reg->setPublicPages($actual);
    return $affected;
  }

  /**
   * Convenience method provides option to sync dt_* data.
   *
   * This method should not be called by a Daemon process, which
   * should focus instead on retrieving data.
   *
   * @param FullRegatta $reg the regatta whose information to sync
   */
  public function runSync(FullRegatta $reg) {
    $reg->setData();
    self::errln(sprintf("Synced data for %s.", $reg->name), 2);
    $reg->setRanks();
    self::errln(sprintf("Synced ranks for %s.", $reg->name), 2);
    $reg->setRpData();
    self::errln(sprintf("Synced RP data for %s.", $reg->name), 2);
    self::errln(sprintf("Synced %s.", $reg->name));
  }

  /**
   * Updates the regatta's pages according to the activity
   * requested. Will not create scores page if no finishes are registered!
   *
   * @param Regatta $reg the regatta to update
   * @param Array:UpdateRequest::Constant the activities to execute
   */
  public function run(FullRegatta $reg, Array $activities) {
    $changed = $this->syncUrls($reg);

    if ($reg->private || $reg->inactive !== null) {
      return;
    }

    if ($reg->scoring == Regatta::SCORING_TEAM) {
      $this->runTeamRacing($reg, $activities, $changed);
      return;
    }

    // Based on the list of activities, determine what files need to
    // be (re)serialized
    $sync_rp = false;

    $tweet_finalized = false;

    $docs = $reg->getDocuments();

    $rotation = false;
    $divisions = false;
    $divisions_history = false;
    $front = false;
    $front_history = false;
    $full = false;
    $notice = false;
    $notice_docs = false;
    $rot = $reg->getRotationManager();

    // If any 'index.html' files were added or deleted, then all pages
    // need to be regenerated, regardless of activity, because the
    // page's menu will have changed.
    foreach ($changed as $url => $action) {
      if (strlen($url) > 11 && substr($url, -11) == '/index.html') {
        self::errln(sprintf("URL $url changed, queueing all pages.", $url), 3);
        $front = true;
        if ($reg->hasFinishes()) {
          $full = true;
          if (!$reg->isSingleHanded())
            $divisions = true;
        }
        if ($rot->isAssigned())
          $rotation = true;
        if (count($docs) > 0)
          $notice = true;
        break;
      }
    }

    if (in_array(UpdateRequest::ACTIVITY_URL, $activities)) {
      // Also regen the documents, if any
      if (count($docs) > 0)
        $notice_docs = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_ROTATION, $activities)) {
      if ($rot->isAssigned()) {
        $rotation = true;
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_SCORE, $activities)) {
      $sync_rp = true; // re-rank sailors
      $front = true;
      $front_history = true;
      $rotation = true; // rotation table accounts for scored races
      if ($reg->hasFinishes()) {
        $full = true;

        // Individual division scores (do not include if singlehanded as
        // this is redundant)
        if (!$reg->isSingleHanded()) {
          $divisions = true;
          $divisions_history = true;
        }
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_TEAM, $activities)) {
      $front = true;
      $front_history = true;
      if ($rot->isAssigned()) {
        $rotation = true;
      }
      if ($reg->hasFinishes()) {
        $full = true;

        // Individual division scores (do not include if singlehanded as
        // this is redundant)
        if (!$reg->isSingleHanded()) {
          $divisions = true;
          $divisions_history = true;
        }
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_RP, $activities)) {
      $sync_rp = true;
      if ($reg->isSinglehanded()) {
        $rotation = true;
        if ($reg->hasFinishes()) {
          $front = true;
          $front_history = true;
          $full = true;
        }
      }
      elseif ($reg->hasFinishes())
        $divisions = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_SUMMARY, $activities)) {
      $front = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_DETAILS, $activities) ||
        in_array(UpdateRequest::ACTIVITY_SEASON, $activities)) {
      // do them all (except RP)!
      $front = true;
      $front_history = true;
      if ($rot->isAssigned())
        $rotation = true;
      if ($reg->hasFinishes()) {
        $full = true;

        // Individual division scores (do not include if singlehanded as
        // this is redundant)
        if (!$reg->isSingleHanded()) {
          $divisions = true;
          $divisions_history = true;
        }
      }
      if (count($docs) > 0)
        $notice = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_FINALIZED, $activities)) {
      $sync_rp = true; // some races were removed
      $front = true;
      $end = clone($reg->end_date);
      $end->setTime(23,59,59);
      if ($end > new DateTime('2 days ago'))
        $tweet_finalized = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_DOCUMENT, $activities)) {
      if (count($docs) > 0)
        $notice = true;
      $notice_docs = true;
      if (!$reg->hasFinishes())
        $front = true;
    }

    // ------------------------------------------------------------
    // Perform the updates
    // ------------------------------------------------------------
    if ($sync_rp)    $reg->setRpData();

    $D = $reg->getURL();
    $M = new ReportMaker($reg);

    if ($rotation)   $this->createRotation($D, $M);
    if ($front)      $this->createFront($D, $M);
    if ($front_history) $this->createFrontHistory($D, $reg);
    if ($full)       $this->createFull($D, $M);
    if ($notice)     $this->createNotice($D, $M);
    if ($notice_docs) {
      foreach ($docs as $doc) {
        $name = $doc->getURL();
        self::write($name, $doc);
        self::errln(sprintf("Wrote file %s.", $doc->name));
      }
    }
    if ($divisions) {
      if ($reg->scoring == Regatta::SCORING_STANDARD) {
        foreach ($reg->getDivisions() as $div)
          $this->createDivision($D, $M, $div);
      }
      else {
        $this->createCombined($D, $M);
      }
    }
    if ($divisions_history && $reg->scoring == Regatta::SCORING_STANDARD) {
      $root = $reg->getURL();
      foreach ($reg->getDivisions() as $div)
        $this->createDivisionHistory($D, $reg, $div);
    }
    if ($tweet_finalized) {
      require_once('twitter/TweetFactory.php');
      $fac = new TweetFactory();
      DB::tweet($fac->create(TweetFactory::FINALIZED_EVENT, $reg));
      self::errln(sprintf("Tweeted about regatta %s.", $reg->name));
    }
  }

  /**
   * Interpreter for team racing regattas
   *
   * No need to check if public, since parent performs check ahead of
   * time. No need to sync URLs either, for the same reason. Hence,
   * the third argument, which contains the changed URLs.
   */
  private function runTeamRacing(FullRegatta $reg, Array $activities, Array $changed) {
    // Based on the list of activities, determine what files need to
    // be (re)serialized
    $sync_rp = false;

    $tweet_finalized = false;

    $docs = $reg->getDocuments();

    $rotation = false;
    $allraces = false;
    $front = false;
    $full = false;
    $notice = false;
    $notice_docs = false;
    $sailors = false;

    // If any 'index.html' files were added or deleted, then all pages
    // need to be regenerated, regardless of activity, because the
    // page's menu will have changed.
    foreach ($changed as $url => $action) {
      if (strlen($url) > 11 && substr($url, -11) == '/index.html') {
        self::errln(sprintf("URL $url changed, queueing all pages.", $url), 3);
        $front = true;
        $allraces = true;
        $rotation = true;
        if ($reg->hasFinishes()) {
          $sailors = true;
          $full = true;
        }
        if (count($docs) > 0)
          $notice = true;
        break;
      }
    }

    if (in_array(UpdateRequest::ACTIVITY_URL, $activities)) {
      // Also regen the documents, if any
      if (count($docs) > 0)
        $notice_docs = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_ROTATION, $activities)) {
      $rotation = true;
      $allraces = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_SCORE, $activities)) {
      $sync_rp = true; // re-rank sailors
      $front = true;
      $allraces = true;
      $rotation = true; // rotation table accounts for scored races
      if ($reg->hasFinishes()) {
        $full = true;
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_RP, $activities)) {
      $sync_rp = true;
      $front = true;
      if ($reg->hasFinishes())
        $sailors = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_SUMMARY, $activities)) {
      $front = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_DETAILS, $activities) ||
        in_array(UpdateRequest::ACTIVITY_SEASON, $activities) ||
        in_array(UpdateRequest::ACTIVITY_TEAM, $activities)) {
      // do them all (except RP)!
      $front = true;
      $rotation = true;
      $allraces = true;
      if ($reg->hasFinishes()) {
        $sailors = true;
        $full = true;
      }
      if (count($docs) > 0)
        $notice = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_FINALIZED, $activities)) {
      $sync_rp = true; // some races were removed
      $front = true;
      $full = true;
      $sailors = true;
      $tweet_finalized = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_RANK, $activities)) {
      $sync_rp = true;
      $front = true;
      $full = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_DOCUMENT, $activities)) {
      if (count($docs) > 0)
        $notice = true;
      $notice_docs = true;
    }

    // ------------------------------------------------------------
    // Perform the udpates
    // ------------------------------------------------------------
    if ($sync_rp)    $reg->setRpData();

    $D = $reg->getURL();
    $M = new ReportMaker($reg);

    if ($rotation)   $this->createRotation($D, $M);
    if ($front)      $this->createFront($D, $M);
    if ($full)       $this->createFull($D, $M);
    if ($allraces)   $this->createAllRaces($D, $M);
    if ($sailors)    $this->createSailors($D, $M);
    if ($notice)     $this->createNotice($D, $M);
    if ($notice_docs) {
      foreach ($docs as $doc) {
        $name = $doc->getURL();
        self::write($name, $doc);
        self::errln(sprintf("Wrote file %s.", $doc->name));
      }
    }

    if ($tweet_finalized) {
      require_once('twitter/TweetFactory.php');
      $fac = new TweetFactory();
      DB::tweet($fac->create(TweetFactory::FINALIZED_EVENT, $reg));
    }
  }

  // ------------------------------------------------------------
  // Helper methods for standardized file creation
  // ------------------------------------------------------------

  /**
   * Creates and writes the front page (index) in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   */
  private function createFront($dirname, ReportMaker $maker) {
    $filename = $dirname . 'index.html';
    $page = $maker->getScoresPage();
    self::write($filename, $page);
  }

  private function createFrontHistory($dirname, FullRegatta $reg) {
    $chart = RegattaChartCreator::getChart($reg);
    if ($chart !== null) {
      $filename = $dirname . 'history.svg';
      self::write($filename, $chart);
    }
  }

  /**
   * Creates and writes the notice board page
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   */
  private function createNotice($dirname, ReportMaker $maker) {
    $page = $maker->getNoticesPage();
    $path = $dirname . 'notices/index.html';
    self::write($path, $page);
  }

  /**
   * Creates and writes the full scores page in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private function createFull($dirname, ReportMaker $maker) {
    $page = $maker->getFullPage();
    $path = $dirname . 'full-scores/index.html';
    self::write($path, $page);
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
  private function createDivision($dirname, ReportMaker $maker, Division $div) {
    $path = $dirname . $div . '/index.html';
    $page = $maker->getDivisionPage($div);
    self::write($path, $page);
  }

  private function createDivisionHistory($dirname, FullRegatta $reg, Division $div) {
    $chart = RegattaChartCreator::getChart($reg, $div);
    if ($chart !== null) {
      $filename = $dirname . $div . '/history.svg';
      self::write($filename, $chart);
    }
  }

  /**
   * Creates and writes the combined division page in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private function createCombined($dirname, ReportMaker $maker) {
    $path = $dirname . 'divisions/index.html';
    $page = $maker->getCombinedPage();
    self::write($path, $page);
  }

  /**
   * Creates and writes the rotation page in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private function createRotation($dirname, ReportMaker $maker) {
    $path = $dirname . 'rotations/index.html';
    $page = $maker->getRotationPage();
    self::write($path, $page);
  }

  /**
   * Creates and writes the all-races page for team racing
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private function createAllRaces($dirname, ReportMaker $maker) {
    $path = $dirname . 'all/index.html';
    $page = $maker->getAllRacesPage();
    self::write($path, $page);
  }

  /**
   * Creates and writes the sailors report for team racing
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private function createSailors($dirname, ReportMaker $maker) {
    $path = $dirname . 'sailors/index.html';
    $page = $maker->getSailorsPage();
    self::write($path, $page);
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------
  public function __construct() {
    parent::__construct();
    $this->cli_opts = '<regatta-id> <activity>';
    $this->cli_usage = "Activity must be one of:\n";
    foreach (UpdateRequest::getTypes() as $type)
      $this->cli_usage .= "\n - $type";
    $this->cli_usage .= "\n - sync:      special rule to set data";
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    if (count($opts) < 2) {
      throw new TSScriptException("Missing argument(s)");
    }
    if (($reg = DB::getRegatta(array_shift($opts))) === null) {
      throw new TSScriptException("Invalid regatta");
    }

    $pos_actions = UpdateRequest::getTypes();
    $actions = array();
    $sync = false;
    foreach ($opts as $opt) {
      if ($opt == 'sync') {
        $sync = true;
      }
      else {
        if (!array_key_exists($opt, $pos_actions)) {
          throw new TSScriptException("Invalid activity $opt");
        }
        $actions[] = $opt;
      }
    }
    if ($sync) {
      $this->runSync($reg);
    }
    if (count($actions) > 0) {
      $this->run($reg, $actions);
    }
  }
}
