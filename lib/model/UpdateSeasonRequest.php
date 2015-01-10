<?php
/*
 * This file is part of Techscore
 */



/**
 * Request to update season
 *
 * @author Dayan Paez
 * @version 2012-01-15
 */
class UpdateSeasonRequest extends AbstractUpdate {
  protected $season;

  const ACTIVITY_REGATTA = 'regatta';
  const ACTIVITY_DETAILS = 'details';
  const ACTIVITY_FRONT = 'front';
  const ACTIVITY_404 = '404';
  const ACTIVITY_SCHOOL_404 = 'school404';

  public static function getTypes() {
    return array(self::ACTIVITY_REGATTA => self::ACTIVITY_REGATTA,
                 self::ACTIVITY_FRONT => self::ACTIVITY_FRONT,
                 self::ACTIVITY_404 => self::ACTIVITY_404,
                 self::ACTIVITY_SCHOOL_404 => self::ACTIVITY_SCHOOL_404,
                 self::ACTIVITY_DETAILS => self::ACTIVITY_DETAILS);
  }

  public function db_name() { return 'pub_update_season'; }
  public function db_type($field) {
    switch ($field) {
    case 'season': return DB::T(DB::SEASON);
    default:
      return parent::db_type($field);
    }
  }

  public function hash() {
    $id = ($this->season instanceof Season) ? $this->season->id : $this->season;
    return sprintf('%s-%s', $id, $this->activity);
  }
}
