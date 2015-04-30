<?php
namespace utils;

use \Account;
use \DB;
use \Regatta;
use \Season;
use \Type;

use \DBCond;
use \DBCondIn;
use \DBBool;

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
   * @var Array:Type Regatta types to filter by, if any.
   */
  private $types;
  /**
   * @var Array:String regatta scoring to filter, if any (class constant).
   */
  private $scoringFilters;
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
    $this->types = array();
    $this->scoringFilters = array();
  }

  /**
   * Searches regattas based on criteria set.
   *
   * @return Array:Regatta
   */
  public function doSearch() {
    $condList = array();

    if ($this->query !== null) {
      $qry = '%' . trim($this->query) . '%';
      $condList[] = new DBCond('name', $qry, DBCond::LIKE);
    }

    if ($this->account !== null) {
      $cond = $this->getJurisdictionCondition($this->account, $this->includeAccountAsParticipant);
      if ($cond !== null) {
        $condList[] = $cond;
      }
    }

    if (count($this->seasons) > 0) {
      $cond = new DBBool(array(), DBBool::mOR);
      foreach ($this->seasons as $season) {
        $cond->add(
          new DBBool(
            array(
              new DBCond('start_time', $season->start_date, DBCond::GE),
              new DBCond('start_time', $season->end_date,   DBCond::LT)
            )
          )
        );
      }
      $condList[] = $cond;
    }

    if (count($this->types) > 0) {
      $cond = new DBBool(array(), DBBool::mOR);
      foreach ($this->types as $type) {
        $cond->add(new DBCond('type', $type));
      }
      $condList[] = $cond;
    }

    if (count($this->scoringFilters) > 0) {
      $cond = new DBBool(array(), DBBool::mOR);
      foreach ($this->scoringFilters as $type) {
        $cond->add(new DBCond('scoring', $type));
      }
      $condList[] = $cond;
    }

    $cond = null;
    if (count($condList) > 0) {
      $cond = new DBBool($condList);
    }
    $obj = DB::T(DB::REGATTA);
    return DB::getAll($obj, $cond);
  }

  /**
   * Create a DBExpression that limits regattas search for given user.
   *
   * @param Account $account the account to search.
   * @param boolean $includeParticipating true to include events where
   *     user's schools are participating.
   * @param String $regattaAttribute the name of the field
   * @return DBExpression, or null (for admin users).
   */
  private function getJurisdictionCondition(Account $account, $includeParticipating = false, $regattaAttribute = 'id') {
    // For admin users, no limit is required.
    if ($account->isAdmin()) {
      return null;
    }

    $schoolCond = $account->getSchoolCondition();
    $cond = new DBBool(
      array(
        // Either assigned as a scorer directly
        new DBCondIn(
          $regattaAttribute,
          DB::prepGetAll(
            DB::T(DB::SCORER),
            new DBCond('account', $account),
            array('regatta'))
        ),

        // Or hosting according to school ownership
        new DBCondIn(
          $regattaAttribute,
          DB::prepGetAll(
            DB::T(DB::HOST_SCHOOL),
            $schoolCond,
            array('regatta'))
        ),
      ),
      DBBool::mOR
    );

    if ($includeParticipating !== false) {
      $cond->add(
        // Or participating.
        new DBCondIn(
          $regattaAttribute,
          DB::prepGetAll(
            DB::T(DB::TEAM),
            $schoolCond,
            array('regatta'))
        )
      );
    }

    return $cond;
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

    foreach (DB::$V->incList($args, 'scoring') as $scoring) {
      $params->addScoringFilter($scoring);
    }

    $typeList = DB::$V->incList($args, 'type');
    foreach ($typeList as $i => $typeId) {
      $params->addType(DB::$V->reqID($typeList, $i, DB::T(DB::TYPE)));
    }

    return $params;
  }

  public function setQuery($qry = null) {
    $this->query = $qry;
  }
  public function addType(Type $type) {
    $this->types[$type->id] = $type;
  }
  public function setTypes(Array $types = array()) {
    $this->types = array();
    foreach ($types as $type) {
      $this->addType($type);
    }
  }
  public function addScoringFilter($scoringType) {
    self::validateScoringFilter($scoringType);
    $this->scoringFilters[] = $scoringFilter;
  }
  public function setScoringFilters(Array $scoringFilters = array()) {
    $this->scoringFilters = array();
    foreach ($scoringFilters as $filter) {
      $this->addScoringFilter($filter);
    }
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

  private static function validateScoringFilter($scoring) {
    if (self::$SCORING_OPTIONS === null) {
      self::$SCORING_OPTIONS = Regatta::getScoringOptions();
    }
    if ($scoring !== null && !array_key_exists($scoring, self::$SCORING_OPTIONS)) {
      throw new InvalidArgumentException("Invalid scoring option: $scoring.");
    }
  }
  /**
   * Cache of regatta scoring options.
   * @see validateScoringFilter
   */
  private static $SCORING_OPTIONS;
}