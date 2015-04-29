<?php
namespace ui;

use \DB;
use \Regatta;

/**
 * Encapsulates filter and search terms for regatta-display panes.
 *
 * @author Dayan Paez
 * @version 2015-04-26
 */
class RegattaSearchParams {

  /**
   * Which page in the series is requested?
   */
  public $pageset;
  /**
   * Search term?
   */
  public $query;
  /**
   * @var Type Regatta type filter, if any.
   */
  public $typeFilter;
  /**
   * @var String regatta scoring filter, if any (class constant).
   */
  public $scoringFilter;

  public function __construct() {
  }

  /**
   * Creates and return object based on input parameters.
   *
   * @param Array $args GET request.
   * @param int $pageSize the size of the pages (optional).
   * @return RegattaSearchParams
   */
  public static function fromArgs(Array $args) {
    $params = new RegattaSearchParams();
    $params->pageset = DB::$V->incInt($args, 'r', 1);
    $params->query = DB::$V->incString($args, 'q', 1, 256);
    $params->typeFilter = DB::$V->incID($args, 'type', DB::T(DB::TYPE));
    $params->scoringFilter = DB::$V->incKey($args, 'scoring', Regatta::getScoringOptions());
    return $params;
  }
}