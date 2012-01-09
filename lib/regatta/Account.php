<?php
/*
 * This class is part of TechScore
 *
 * @package regatta
 */

require_once('regatta/DB.php');

/**
 * Encapsulates an account: a user devoid of "extra" information and a
 * connection to the database
 *
 * @author Dayan Paez
 * @version 2009-11-30
 */
class Account extends DBObject {
  const ROLE_STUDENT = 'student';
  const ROLE_COACH = 'coach';
  const ROLE_STAFF = 'staff';

  const STAT_REQUESTED = 'requested';
  const STAT_PENDING = 'pending';
  const STAT_ACCEPTED = 'accepted';
  const STAT_REJECTED = 'rejected';
  const STAT_ACTIVE = 'active';
  const STAT_INACTIVE = 'inactive';

  // Variables
  public $first_name;
  public $last_name;
  public $role;
  public $admin;
  public $status;
  public $password;
  protected $school;

  public function db_type($field) {
    switch ($field) {
    case 'school':
      return DB::$SCHOOL;
    default:
      return parent::db_type($field);
    }
  }

  protected function db_order() { return array('last_name'=>true, 'first_name'=>true); }

  public function __toString() {
    return $this->getName();
  }

  /**
   * Returns the user's name
   *
   * @return string "First Lastname"
   */
  public function getName() {
    return sprintf("%s %s", $this->first_name, $this->last_name);
  }
}
DB::$ACCOUNT = new Account();
?>