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
  const UNEXPECTED_POST_ARGUMENT = 'unexpected_post_argument';

  const MISSING_ELIGIBILITY_START = 'missing_eligibility_start';

  protected $published_on;
  public $metric;
  public $amount;

  public function db_type($field) {
    if ($field == 'published_on') {
      return DB::T(DB::NOW);
    }
    return parent::db_type($field);
  }
}
