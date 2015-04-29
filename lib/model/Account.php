<?php
/*
 * This class is part of TechScore
 *
 * @package regatta
 */


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
  public $email;
  public $role;
  public $admin;
  public $status;
  public $password;
  public $message;
  protected $ts_role;

  public function db_type($field) {
    switch ($field) {
    case 'ts_role':
      return DB::T(DB::ROLE);
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

  /**
   * Fetches the different statuses allowed
   *
   * @return Array:String assoc. map of statuses
   */
  public static function getStatuses() {
    return array(
                 self::STAT_REQUESTED => 'Requested',
                 self::STAT_PENDING => 'Pending',
                 self::STAT_ACCEPTED => 'Accepted',
                 self::STAT_REJECTED => 'Rejected',
                 self::STAT_ACTIVE => 'Active',
                 self::STAT_INACTIVE => 'Inactive',
                 );
  }

  /**
   * Gets String representation of account's main affiliation
   *
   * If account is assigned to one school only, then use that as the
   * affiliation. If admin, use organization name as affiliation.
   *
   * @return String
   */
  public function getAffiliation() {
    if ($this->isAdmin())
      return DB::g(STN::ORG_NAME);
    $schools = $this->getSchools();
    if (count($schools) == 1)
      return $schools[0]->name;
    return sprintf("%d schools", count($schools));
  }

  public function isAdmin() {
    return $this->admin > 1 || ($this->ts_role !== null && $this->__get('ts_role')->has_all !== null);
  }

  public function isSuper() {
    return $this->admin > 1;
  }

  /**
   * Returns all the conferences this user is affiliated with
   *
   * 'Admins' have access to all conferences, while others are
   * assigned.
   *
   * @return Array:Conference
   */
  public function getConferences() {
    if ($this->isAdmin())
      return DB::getConferences();
    return DB::getAll(DB::T(DB::CONFERENCE),
                      new DBCondIn('id',
                                   DB::prepGetAll(DB::T(DB::ACCOUNT_CONFERENCE), new DBCond('account', $this), array('conference'))));
  }

  /**
   * Sets the conference affiliations for this account
   *
   * @param Array:Conference the conferences to associate
   */
  public function setConferences(Array $conferences) {
    DB::removeAll(DB::T(DB::ACCOUNT_CONFERENCE), new DBCond('account', $this));
    $new = array();
    foreach ($conferences as $conf) {
      $link = new Account_Conference();
      $link->account = $this;
      $link->conference = $conf;
      $new[] = $link;
    }
    DB::insertAll($new);
  }

  /**
   * Sets the affiliations for this account
   *
   * @param Array:School the schools to associate
   */
  public function setSchools(Array $schools) {
    DB::removeAll(DB::T(DB::ACCOUNT_SCHOOL), new DBCond('account', $this));
    $new = array();
    foreach ($schools as $school) {
      $link = new Account_School();
      $link->account = $this;
      $link->school = $school;
      $new[] = $link;
    }
    DB::insertAll($new);
  }

  /**
   * Gets one school from the list of schools associated with account
   *
   * @param Conference $conf the optional conference to limit to
   * @return School|null
   */
  public function getFirstSchool(Conference $conf = null) {
    $schools = $this->getSchools($conf);
    return (count($schools) == 0) ? null : $schools[0];
  }

  /**
   * Returns all the schools that this user is affiliated with
   *
   * @param Conference $conf the possible to conference to narrow down
   * school list
   *
   * @param boolean $effective false to ignore permissions and return
   * only assigned values
   *
   * @param boolean $active true (default) to return only active schools
   *
   * @return Array:School, indexed by school ID
   */
  public function getSchools(Conference $conf = null, $effective = true, $active = true) {
    $cond = null;
    if ($this->isAdmin() && $effective !== false) {
      if ($conf !== null)
        $cond = new DBCond('conference', $conf);
    }
    else {
      $cond = new DBCondIn('id', DB::prepGetAll(DB::T(DB::ACCOUNT_SCHOOL), new DBCond('account', $this), array('school')));
      if ($effective !== false)
        $cond = new DBBool(array($cond,
                                 new DBCondIn('conference', DB::prepGetAll(DB::T(DB::ACCOUNT_CONFERENCE), new DBCond('account', $this), array('conference')))),
                           DBBool::mOR);
      if ($conf !== null)
        $cond = new DBBool(array($cond, new DBCond('conference', $conf)));
    }
    $obj = ($active) ? DB::T(DB::ACTIVE_SCHOOL) : DB::T(DB::SCHOOL);
    return DB::getAll($obj, $cond);
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
    $res = DB::getAll(DB::T(DB::ACCOUNT_SCHOOL), new DBBool(array(new DBCond('account', $this), new DBCond('school', $school))));
    if (count($res) > 0) {
      unset($res);
      return true;
    }
    $res = DB::getAll(DB::T(DB::ACCOUNT_CONFERENCE), new DBBool(array(new DBCond('account', $this), new DBCond('conference', $school->conference))));
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
    if ($this->isAdmin())
      return true;
    $res = DB::getAll(DB::T(DB::SCORER), new DBBool(array(new DBCond('regatta', $reg->id), new DBCond('account', $this))));
    if (count($res) > 0)
      return true;

    $res = DB::getAll(DB::T(DB::HOST_SCHOOL),
                      new DBBool(array(new DBCond('regatta', $reg),
                                       $this->getSchoolCondition('school'))));
    return count($res) > 0;
  }

  /**
   * Does any of the account's schools have a team in the given regatta
   *
   * @param Regatta $reg the regatta
   * @return boolean
   */
  public function isParticipantIn(FullRegatta $reg) {
    $res = DB::getAll(DB::T(DB::TEAM),
                      new DBBool(array(new DBCond('regatta', $reg),
                                       $this->getSchoolCondition('school'))));
    return count($res) > 0;
  }

  /**
   * Return list of regattas where this user is the author
   *
   * @return Array:Regatta
   */
  public function getRegattasCreated() {
    return DB::getAll(DB::T(DB::REGATTA), new DBCond('creator', $this->id));
  }

  /**
   * Create a DBExpression to match the schools for this account,
   * suitable to use in subqueries
   *
   * @param String $attr the attribute that represents the ID of the school
   * @return DBExpression
   */
  private function getSchoolCondition($attr) {
    return new DBBool(
      array(
        new DBCondIn($attr, DB::prepGetAll(DB::T(DB::ACCOUNT_SCHOOL),
                                           new DBCond('account', $this),
                                           array('school'))),
        new DBCondIn($attr, DB::prepGetAll(DB::T(DB::SCHOOL),
                                           new DBCondIn('conference', DB::prepGetAll(DB::T(DB::ACCOUNT_CONFERENCE),
                                                                                     new DBCond('account', $this),
                                                                                     array('conference'))),
                                           array('id'))),
      ),
      DBBool::mOR
    );
  }

  /**
   * Create a DBExpression to fetch regattas for this user
   *
   * @param String $reg_attr name of ID attribute
   * which the user is participating
   * @return DBExpression
   */
  private function getJurisdictionCondition() {
    $reg_attr = 'id';
    $school_cond = $this->getSchoolCondition('school');
    return new DBBool(
      array(
        new DBCondIn($reg_attr, DB::prepGetAll(DB::T(DB::SCORER), new DBCond('account', $this), array('regatta'))),
        new DBCondIn($reg_attr, DB::prepGetAll(DB::T(DB::HOST_SCHOOL),
                                               $school_cond,
                                               array('regatta'))),
      ),
      DBBool::mOR
    );
  }

  private function getParticipantCondition() {
    $school_cond = $this->getSchoolCondition('school');
    return new DBCondIn('id',
                        DB::prepGetAll(DB::T(DB::TEAM),
                                       $school_cond,
                                       array('regatta')));
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
    $cond = null;
    if (!$this->isAdmin()) { // regular user
      $cond = $this->getJurisdictionCondition();
      if ($inc_participating)
        $cond->add($this->getParticipantCondition());
    }
    if ($season !== null) {
      $scond = new DBBool(array(new DBCond('start_time', $season->start_date, DBCond::GE),
                                new DBCond('start_time', $season->end_date,   DBCond::LT)));
      if ($cond !== null)
        $scond->add($cond);
      $cond = $scond;
    }
    return DB::getAll(DB::T(DB::REGATTA), $cond);
  }

  /**
   * Searches and returns a list of matching regattas.
   *
   * @param String $qry the query to search
   * @param boolean $inc_participating default false
   * @return Array:Regatta the regattas
   */
  public function searchRegattas($qry, $inc_participating = false) {
    $cond = new DBCond('name', "%$qry%", DBCond::LIKE);

    if (!$this->isAdmin()) { // regular user
      $c = $this->getJurisdictionCondition();
      if ($inc_participating)
        $c->add($this->getParticipantCondition());
      $cond = new DBBool(array($cond, $c));
    }

    return DB::getAll(DB::T(DB::REGATTA), $cond);
  }

  /**
   * Retrieve all messages for the given account in order
   *
   * @return Array:Message the messages
   */
  public function getMessages() {
    return DB::getAll(DB::T(DB::MESSAGE), new DBCond('account', $this));
  }

  // ------------------------------------------------------------
  // Permissions
  // ------------------------------------------------------------

  /**
   * Does this account's role have the necessary permission?
   *
   * @param String $perm Permission constant to check
   * @return boolean true if access granted
   */
  public function can($perm) {
    if ($this->isSuper())
      return true;
    $perm = Permission::g($perm);
    if ($perm === null)
      return false;
    if ($this->ts_role === null)
      return false;
    if ($this->isAdmin())
      return true;
    return $this->__get('ts_role')->hasPermission($perm);
  }

  /**
   * Does this account's role have access to at least one of the given permissions?
   *
   * @param Array:String list of Permission constants
   * @return boolean true if role has access to one of those permissions
   */
  public function canAny(Array $perms) {
    foreach ($perms as $perm) {
      if ($this->can($perm))
        return true;
    }
    return false;
  }

  // ------------------------------------------------------------
  // Password recovery
  // ------------------------------------------------------------

  /**
   * Resets token associated with given e-mail
   *
   * @param String $email if not given, use account's email
   */
  public function resetToken($email = null) {
    if ($email === null)
      $email = $this->email;
    DB::removeAll(
      DB::T(DB::EMAIL_TOKEN),
      new DBBool(
        array(
          new DBCond('account', $this),
          new DBCond('email', $email)
        )));
  }

  /**
   * Creates and returns a new unique password token
   *
   * @param String $email the email to use (default: account's email)
   * @return Email_Token the generated token
   */
  public function createToken($email = null) {
    if ($email === null)
      $email = $this->email;
    if ($email === null)
      throw new InvalidArgumentException("No e-mail available for token");

    do {
      $salt = uniqid();
      $code = $email . '\0' . Conf::$PASSWORD_SALT . '\0' . date('U') . '\0' . $salt;
      $code = hash('sha256', $code);

      $token = DB::get(DB::T(DB::EMAIL_TOKEN), $code);
    } while ($token !== null);

    $token = new Email_Token();
    $token->id = $code;
    $token->account = $this;
    $token->email = $email;
    $token->deadline = new DateTime(DB::g(STN::REGISTRATION_TIMEOUT));
    DB::set($token);
    return $token;
  }

  /**
   * Retrieve the token for given email
   *
   * @param $email the email to use (default: account->email)
   * @return Email_Token
   */
  public function getToken($email = null) {
    if ($email === null)
      $email = $this->email;

    $tokens = DB::getAll(
      DB::T(DB::EMAIL_TOKEN),
      new DBBool(
        array(
          new DBCond('account', $this),
          new DBCond('email', $email))));

    if (count($tokens) == 0)
      return null;
    return $tokens[0];
  }

  public function isTokenActive($email = null) {
    $token = $this->getToken($email);
    return ($token !== null && $token->isTokenActive());
  }
}
