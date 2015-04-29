<?php
namespace utils;

use \Account;
use \DB;
use \Regatta;
use \Season;
use \Type;

use \InvalidArgumentException;

/**
 * Encapsulates filter and search terms for regatta-display panes.
 *
 * @author Dayan Paez
 * @version 2015-04-26
 */
class RegattaSearcher {

  /**
   * Search term?
   */
  private $query;
  /**
   * @var Type Regatta type filter, if any.
   */
  private $typeFilter;
  /**
   * @var String regatta scoring filter, if any (class constant).
   */
  private $scoringFilter;
  /**
   * @var Account limit to user's jurisdiction.
   */
  private $account;
  /**
   * @var boolean true to also include participating. Applies only
   * when account is provided.
   */
  private $includeAccountAsParticipant;
  /**
   * @var Array:Season the seasons to limit to.
   */
  private $seasons;

  public function __construct() {
    $this->includeAccountAsParticipant = false;
    $this->seasons = array();
  }

  /**
   * Searches regattas based on criteria set.
   *
   * @return Array:Regatta
   */
  public function doSearch() {
    // TODO!
  }

  /**
   * Creates and return object based on input parameters.
   *
   * @param Array $args GET request.
   * @param int $pageSize the size of the pages (optional).
   * @return RegattaSearcher
   */
  public static function fromArgs(Array $args) {
    $params = new RegattaSearcher();
    $params->query = DB::$V->incString($args, 'q', 1, 256);
    $params->typeFilter = DB::$V->incID($args, 'type', DB::T(DB::TYPE));
    $params->scoringFilter = DB::$V->incKey($args, 'scoring', Regatta::getScoringOptions());
    return $params;
  }

  public function setQuery($qry = null) {
    $this->query = $qry;
  }
  public function setTypeFilter(Type $type = null) {
    $this->typeFilter = $type;
  }
  public function setScoringFilter($scoring = null) {
    if ($scoring !== null && !array_key_exists($scoring, Regatta::getScoringOptions())) {
      throw new InvalidArgumentException("Invalid scoring option: $scoring.");
    }
    $this->scoringFilter = $scoring;
  }
  public function setAccount(Account $account = null) {
    $this->account = $account;
  }
  public function setIncludeAccountAsParticipant($flag = false) {
    $this->includeAccountAsParticipant = ($flag !== false);
  }
  public function addSeason(Season $season) {
    $this->seasons[$season->id] = $season;
  }
  public function setSeasons(Array $seasons = array()) {
    $this->seasons = array();
    foreach ($seasons as $season) {
      $this->addSeason($season);
    }
  }

  public function __get($name) {
    if (!property_exists($this, $name)) {
      throw new InvalidArgumentException("Invalid property requested: $name.");
    }
    return $this->$name;
  }
}