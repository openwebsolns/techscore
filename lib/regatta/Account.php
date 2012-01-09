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

  /**
   * Fetches the different roles allowed by TechScore
   *
   * @return Array:String associative map of account role types
   */
  public static function getRoles() {
    return array(self::ROLE_COACH=>"Coach",
		 self::ROLE_STAFF=>"Staff",
		 self::ROLE_STUDENT=>"Student");
  }

    /**
   * Returns all the schools that this user is affiliated with,
   * including the one enrolled as.
   *
   * @param Conference $conf the possible to conference to narrow down
   * school list
   * @return Array:School, indexed by school ID
   */
  public function getSchools(Conference $conf = null) {
    $cond = new DBBool(array(new DBCondIn('id', DB::prepGetAll(DB::$ACCOUNT_SCHOOL, new DBCond('account', $this), array('school')))));
    if ($conf !== null)
      $cond->add(new DBCond('conference', $conf));
    return DB::getAll(DB::$SCHOOL, $cond);
  }

  /**
   * Determines whether the given regatta is in this user's scoring
   * jurisdiction
   *
   * @param Regatta $reg the regatta to check
   * @return boolean true if account can edit regatta
   */
  public function hasJurisdiction(Regatta $reg) {
    if ($this->admin > 0)
      return true;
    $res = DB::getAll(DB::$HOST, new DBBool(array(new DBCond('regatta', $reg->id()), new DBCond('account', $this))));
    $r = (count($res) > 0);
    unset($res);
    return $r;
  }
}

/**
 * Many-to-many relationship between accounts and schools
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
class Account_School extends DBObject {
  protected $account;
  protected $school;
  public function db_type($field) {
    switch ($field) {
    case 'account': return DB::$ACCOUNT;
    case 'school':  return DB::$SCHOOL;
    default:
      return parent::db_type($field);
    }
  }
}
DB::$ACCOUNT = new Account();
DB::$ACCOUNT_SCHOOL = new Account_School();
?>