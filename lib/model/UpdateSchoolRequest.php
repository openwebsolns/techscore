<?php
/*
 * This file is part of Techscore
 */



/**
 * School updates
 *
 * @author Dayan Paez
 * @version 2012-10-05
 */
class UpdateSchoolRequest extends AbstractUpdate {
  protected $school;
  protected $season;
  public $argument;

  public function db_name() { return 'pub_update_school'; }
  public function db_type($field) {
    if ($field == 'school')
      return DB::T(DB::SCHOOL);
    if ($field == 'season')
      return DB::T(DB::SEASON);
    return parent::db_type($field);
  }

  const ACTIVITY_BURGEE = 'burgee';
  const ACTIVITY_SEASON = 'season';
  const ACTIVITY_DETAILS = 'details';
  const ACTIVITY_URL = 'url';
  const ACTIVITY_ROSTER = 'roster';

  public static function getTypes() {
    return array(self::ACTIVITY_BURGEE => self::ACTIVITY_BURGEE,
                 self::ACTIVITY_DETAILS => self::ACTIVITY_DETAILS,
                 self::ACTIVITY_URL => self::ACTIVITY_URL,
                 self::ACTIVITY_ROSTER => self::ACTIVITY_ROSTER,
                 self::ACTIVITY_SEASON => self::ACTIVITY_SEASON);
  }

  public function hash() {
    $id = ($this->school instanceof School) ? $this->school->id : $this->school;
    $season = ($this->season instanceof Season) ? $this->season->id : $this->season;
    return sprintf('%s-%s-%s', $id, $this->activity, $season);
  }
}
