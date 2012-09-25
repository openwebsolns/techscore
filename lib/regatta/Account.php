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
  protected function db_cache() { return true; }

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

  public function isAdmin() {
    return $this->admin > 0;
  }

  /**
   * Returns all the schools that this user is affiliated with
   *
   * @param Conference $conf the possible to conference to narrow down
   * school list
   * @return Array:School, indexed by school ID
   */
  public function getSchools(Conference $conf = null) {
    $cond = null;
    if ($this->isAdmin()) {
      if ($conf !== null)
        $cond = new DBCond('conference', $conf);
    }
    else {
      $cond = new DBCondIn('id', DB::prepGetAll(DB::$ACCOUNT_SCHOOL, new DBCond('account', $this), array('school')));
      $cond = new DBBool(array($cond, new DBCond('id', $this->school)), DBBool::mOR);
      if ($conf !== null)
        $cond = new DBBool(array($cond, new DBCond('conference', $conf)));
    }
    return DB::getAll(DB::$SCHOOL, $cond);
  }

  /**
   * Determines whether this account has jurisdiction over the given
   * school.
   *
   * @return boolean true if the school is in the list
   */
  public function hasSchool(School $school) {
    if ($this->isAdmin())
      return true;
    $res = DB::getAll(DB::$ACCOUNT_SCHOOL, new DBBool(array(new DBCond('account', $this), new DBCond('school', $school))));
    $r = (count($res) > 0);
    unset($res);
    return $r;
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
    $res = DB::getAll(DB::$SCORER, new DBBool(array(new DBCond('regatta', $reg->id), new DBCond('account', $this))));
    $r = (count($res) > 0);
    unset($res);
    return $r;
  }
  
  /**
   * Returns all the regattas for which this user is registered as a
   * scorer, using the given optional indices to limit the list, like
   * the range function in Python.
   *
   * <ul>
   *   <li>To fetch the first ten: <code>getRegattas(10);</code></li>
   *   <li>To fetch the next ten:  <code>getRegattas(10, 20);</code><li>
   * </ul>
   *
   * @return Array:Regatta
   */
  public function getRegattas() {
    require_once('regatta/Regatta.php');
    $cond = null;
    if (!$this->isAdmin()) // regular user
      $cond = new DBCondIn('id', DB::prepGetAll(DB::$SCORER, new DBCond('account', $this), array('regatta')));
    return DB::getAll(DB::$REGATTA, $cond);
  }

  /**
   * Searches and returns a list of matching regattas.
   *
   * @param String $qry the query to search
   * @return Array:Regatta the regattas
   */
  public function searchRegattas($qry) {
    require_once('regatta/Regatta.php');
    $cond = new DBCond('name', "%$qry%", DBCond::LIKE);
    if (!$this->isAdmin()) // regular user
      $cond = new DBBool(array($cond,
                               new DBCondIn('id', DB::prepGetAll(DB::$SCORER, new DBCond('account', $this), array('regatta')))));
    return DB::getAll(DB::$REGATTA, $cond);
  }

  /**
   * Retrieve all messages for the given account in order
   *
   * @return Array:Message the messages
   */
  public function getMessages() {
    require_once('regatta/Message.php');
    return DB::getAll(DB::$MESSAGE, new DBCond('account', $this));
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
