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
   * Returns all the conferences this user is affiliated with
   *
   * Affiliation is transitory: depending on school
   *
   * @return Array:Conference
   */
  public function getConferences() {
    if ($this->isAdmin())
      return DB::getConferences();
    return DB::getAll(DB::$CONFERENCE,
                      new DBBool(array(new DBCond('id', $this->__get('school')->conference->id),
                                       new DBCondIn('id',
                                                    DB::prepGetAll(DB::$SCHOOL,
                                                                   new DBCondIn('id',
                                                                                DB::prepGetAll(DB::$ACCOUNT_SCHOOL, new DBCond('account', $this), array('school'))),
                                                                   array('conference')))),
                                 DBBool::mOR));
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
    if ($school == $this->__get('school'))
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
   * Return list of regattas where this user is the author
   *
   * @return Array:Regatta
   */
  public function getRegattasCreated() {
    require_once('regatta/Regatta.php');
    return DB::getAll(DB::$REGATTA, new DBCond('creator', $this->id));
  }

  /**
   * Returns user's regattas
   *
   * These are regattas for which this user has jurdisdiction,
   * optionally limiting the list to a particular season.
   *
   * If the second parameter is true, then regattas in which one of
   * the user's schools is participating will also be included.
   *
   * @param Season $season optional season to limit listing to
   * @param boolean $inc_participating default false
   * @return Array:Regatta
   */
  public function getRegattas(Season $season = null, $inc_participating = false) {
    require_once('regatta/Regatta.php');
    $cond = null;
    if (!$this->isAdmin()) { // regular user
      $cond = new DBCondIn('id', DB::prepGetAll(DB::$SCORER, new DBCond('account', $this), array('regatta')));
      if ($inc_participating !== false)
        $cond = new DBBool(array($cond,
                                 new DBCondIn('id',
                                              DB::prepGetAll(DB::$TEAM,
                                                             new DBCondIn('school', $this->getSchools()),
                                                             array('regatta')))),
                           DBBool::mOR);
    }
    if ($season !== null) {
      $scond = new DBBool(array(new DBCond('start_time', $season->start_date, DBCond::GE),
                                new DBCond('start_time', $season->end_date,   DBCond::LT)));
      if ($cond !== null)
        $scond->add($cond);
      $cond = $scond;
    }
    return DB::getAll(DB::$REGATTA, $cond);
  }

  /**
   * Searches and returns a list of matching regattas.
   *
   * @param String $qry the query to search
   * @param boolean $inc_participating default false
   * @return Array:Regatta the regattas
   */
  public function searchRegattas($qry, $inc_participating = false) {
    require_once('regatta/Regatta.php');
    $cond = new DBCond('name', "%$qry%", DBCond::LIKE);

    if (!$this->isAdmin()) { // regular user
      $c = new DBBool(array(new DBCondIn('id', DB::prepGetAll(DB::$SCORER, new DBCond('account', $this), array('regatta'))),
                            new DBCondIn('id',
                                         DB::prepGetAll(DB::$TEAM,
                                                        new DBCondIn('school', $this->getSchools()),
                                                        array('regatta')))),
                      DBBool::mOR);
      $cond = new DBBool(array($cond, $c));
    }

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
