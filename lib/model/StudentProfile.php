<?php
namespace model;

use \DB;

/**
 * A student profile is the cornerstone of the membership process.
 *
 * @author Dayan Paez
 * @version 2016-03-23
 */
class StudentProfile extends AbstractObject {

  const MALE = 'M';
  const FEMALE = 'F';

  public $first_name;
  public $last_name;
  public $gender;
  protected $school;
  protected $owner;
  protected $eligibility_start;
  public $status;

  public function db_name() {
    return 'student_profile';
  }

  public function db_type($field) {
    switch ($field) {
    case 'school': return DB::T(DB::SCHOOL);
    case 'eligibility_start': return DB::T(DB::NOW);
    case 'owner': return DB::T(DB::ACCOUNT);
    default: return parent::db_type($field);
    }
  }

  public function getName() {
    return sprintf('%s %s', $this->first_name, $this->last_name);
  }

}