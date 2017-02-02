<?php
use \model\StudentProfile;

/**
 * Encapsulates a sailor, whether registered or not.
 *
 * @author Dayan Paez
 * @version 2009-10-04
 */
class Sailor extends Member {
  public function __construct() {
    $this->role = Member::STUDENT;
  }
  public function db_where() {
    return new DBCond('role', 'student');
  }
  public function getUrlSeeds() {
    $name = $this->getName();
    $seeds = array($name);
    if ($this->year > 0) {
      $seeds[] = $name . " " . $this->year;
    }
    $seeds[] = $name . " " . $this->__get('school')->nick_name;
    return $seeds;
  }

  /**
   * Returns new Sailor whose data is based on given profile.
   *
   * @param StudentProfile data source
   * @return Sailor
   */
  public static function fromStudentProfile(StudentProfile $profile) {
    $sailor = new Sailor();
    $sailor->school = $profile->school;
    $sailor->last_name = $profile->last_name;
    $sailor->first_name = $profile->first_name;
    $sailor->year = $profile->graduation_year;
    $sailor->role = Sailor::STUDENT;
    $sailor->gender = $profile->gender;
    return $sailor;
  }
}
