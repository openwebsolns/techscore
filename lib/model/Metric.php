<?php
/**
 * A generic metric: to track user behavior.
 *
 * @author Dayan Paez
 * @created 2015-03-14
 */
class Metric extends DBObject {

  /**
   * Different types of metrics available.
   */
  const INVALID_USERNAME = 'invalid_username';
  const INVALID_PASSWORD = 'invalid_password';
  const TEAM_SCORES_ROUND_NO_RACES = 'team_scores_round_with_no_races';

  protected $published_on;
  public $metric;
  public $amount;

  public function db_type($field) {
    if ($field == 'published_on') {
      return DB::T(DB::NOW);
    }
    return parent::db_type($field);
  }

  /**
   * Publish the given metric by name.
   *
   * @param String $name the name of the metric (class constant)
   * @param double $amount the amount to associate.
   * @return Metric the created metric.
   */
  public static function publish($metric, $amount = 1) {
    $obj = new Metric();
    $obj->amount = (double)$amount;
    $obj->metric = $metric;
    $obj->published_on = DB::T(DB::NOW);
    DB::set($obj, false);
    return $obj;
  }
}
