<?php
/*
 * This file is part of Techscore
 */

/**
 * Sailor-level updates
 *
 * @author Dayan Paez
 * @version 2015-01-18
 */
class UpdateSailorRequest extends AbstractUpdate {
  protected $sailor;
  protected $season;
  protected $argument;

  public function db_name() { return 'pub_update_sailor'; }
  public function db_type($field) {
    if ($field == 'sailor')
      return DB::T(DB::SAILOR);
    if ($field == 'season')
      return DB::T(DB::SEASON);
    return parent::db_type($field);
  }

  const ACTIVITY_NAME = 'name';
  const ACTIVITY_SEASON = 'season';
  const ACTIVITY_DETAILS = 'details';
  const ACTIVITY_URL = 'url';
  const ACTIVITY_DISPLAY = 'display';

  public static function getTypes() {
    return array(self::ACTIVITY_NAME => self::ACTIVITY_NAME,
                 self::ACTIVITY_DETAILS => self::ACTIVITY_DETAILS,
                 self::ACTIVITY_URL => self::ACTIVITY_URL,
                 self::ACTIVITY_DISPLAY => self::ACTIVITY_DISPLAY,
                 self::ACTIVITY_SEASON => self::ACTIVITY_SEASON);
  }

  public function hash() {
    $id = ($this->sailor instanceof Sailor) ? $this->sailor->id : $this->sailor;
    $season = ($this->season instanceof Season) ? $this->season->id : $this->season;
    return sprintf('%s-%s-%s', $id, $this->activity, $season);
  }
}