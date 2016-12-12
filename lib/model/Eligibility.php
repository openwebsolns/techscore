<?php
namespace model;

use \DB;

/**
 * Tracks a student's seasonal eligibility.
 */
class Eligibility extends AbstractObject {
  protected $student_profile;
  protected $season;
  public $reason;

  public function db_name() {
    return 'eligibility';
  }

  public function db_type($field) {
    switch ($field) {
    case 'student_profile':
      return DB::T(DB::STUDENT_PROFILE);
    case 'season':
      return DB::T(DB::SEASON);
    default:
      return parent::db_type($field);
    }
  }
}