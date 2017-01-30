<?php
namespace model;

use \DB;
use \DBCond;

/**
 * A student profile is the cornerstone of the membership process.
 *
 * @author Dayan Paez
 * @version 2016-03-23
 */
class StudentProfile extends AbstractObject {

  const MALE = 'M';
  const FEMALE = 'F';

  const STATUS_REQUESTED = 'requested';

  public $first_name;
  public $middle_name;
  public $last_name;
  public $display_name;
  public $gender;
  protected $school;
  protected $owner;
  protected $eligibility_start;
  public $graduation_year;
  protected $birth_date;
  public $status;

  public function db_name() {
    return 'student_profile';
  }

  public function db_type($field) {
    switch ($field) {
    case 'school': return DB::T(DB::SCHOOL);
    case 'eligibility_start':
    case 'birth_date':
      return DB::T(DB::NOW);
    case 'owner': return DB::T(DB::ACCOUNT);
    default: return parent::db_type($field);
    }
  }

  public function getName() {
    if ($this->display_name !== null) {
      return $this->display_name;
    }
    return sprintf('%s %s', $this->first_name, $this->last_name);
  }

  // Contact information handling

  public function addContact(StudentProfileContact $contact) {
    $contact->student_profile = $this;
    DB::set($contact);
  }

  public function getContact($type) {
    $res = DB::getAll(
      DB::T(DB::STUDENT_PROFILE_CONTACT),
      new DBCond('contact_type', $type)
    );
    return count($res) > 0 ? $res[0] : null;
  }

  public function getHomeContact() {
    return $this->getContact(StudentProfileContact::CONTACT_TYPE_HOME);
  }

  public function getSchoolContact() {
    return $this->getContact(StudentProfileContact::CONTACT_TYPE_SCHOOL);
  }

  /**
   * Fetches all the sailor records associated with this profile.
   *
   * @return Array:Sailor
   */
  public function getSailorRecords() {
    return DB::getAll(DB::T(DB::SAILOR), new DBCond('student_profile', $this));
  }

  // Eligibility handling

  public function getEligibilities() {
    return DB::getAll(DB::T(DB::ELIGIBILITY), new DBCond('student_profile', $this));
  }
}