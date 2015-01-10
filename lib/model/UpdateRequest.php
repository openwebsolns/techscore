<?php
/*
 * This file is part of Techscore
 */



/**
 * Simple serialization of a public display update request
 *
 * @author Dayan Paez
 * @version 2010-10-11
 */
class UpdateRequest extends AbstractUpdate {
  protected $regatta;
  public $argument;

  public function db_name() { return 'pub_update_request'; }
  public function db_type($field) {
    if ($field == 'regatta') {
      return DB::T(DB::FULL_REGATTA);
    }
    return parent::db_type($field);
  }

  const ACTIVITY_RP = "rp";
  const ACTIVITY_SCORE = "score";
  const ACTIVITY_DETAILS = "details";
  const ACTIVITY_SUMMARY = "summary";
  const ACTIVITY_ROTATION = "rotation";
  const ACTIVITY_FINALIZED = 'finalized';
  const ACTIVITY_URL = 'url';
  const ACTIVITY_SEASON = 'season';
  const ACTIVITY_RANK = 'rank';
  const ACTIVITY_TEAM = 'team';
  const ACTIVITY_DOCUMENT = 'document';

  /**
   * Returns an associative set of the permissible types
   *
   * @return Array type constants as const => const
   */
  public static function getTypes() {
    return array(self::ACTIVITY_RP => self::ACTIVITY_RP,
                 self::ACTIVITY_SCORE => self::ACTIVITY_SCORE,
                 self::ACTIVITY_DETAILS => self::ACTIVITY_DETAILS,
                 self::ACTIVITY_SUMMARY => self::ACTIVITY_SUMMARY,
                 self::ACTIVITY_FINALIZED => self::ACTIVITY_FINALIZED,
                 self::ACTIVITY_URL => self::ACTIVITY_URL,
                 self::ACTIVITY_SEASON => self::ACTIVITY_SEASON,
                 self::ACTIVITY_RANK => self::ACTIVITY_RANK,
                 self::ACTIVITY_TEAM => self::ACTIVITY_TEAM,
                 self::ACTIVITY_DOCUMENT => self::ACTIVITY_DOCUMENT,
                 self::ACTIVITY_ROTATION => self::ACTIVITY_ROTATION);
  }

  /**
   * Returns a unique identifier for this request, constituting of the
   * regatta ID, the activity, and the argument
   */
  public function hash() {
    $id = ($this->regatta instanceof FullRegatta) ? $this->regatta->id : $this->regatta;
    return sprintf('%s-%s-%s', $id, $this->activity, $this->argument);
  }
}
