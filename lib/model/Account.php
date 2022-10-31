<?php
use \model\AbstractObject;
use \model\StudentProfile;

/**
 * Encapsulates an account: a user devoid of "extra" information and a
 * connection to the database
 *
 * @author Dayan Paez
 * @version 2009-11-30
 */
class Account extends AbstractObject {
  const ROLE_STUDENT = 'student';
  const ROLE_COACH = 'coach';
  const ROLE_STAFF = 'staff';

  const STAT_REQUESTED = 'requested';
  const STAT_PENDING = 'pending';
  const STAT_ACCEPTED = 'accepted';
  const STAT_REJECTED = 'rejected';
  const STAT_ACTIVE = 'active';
  const STAT_INACTIVE = 'inactive';

  const EMAIL_INBOX_STATUS_RECEIVING = 'receiving';
  const EMAIL_INBOX_STATUS_BOUNCING = 'bouncing';

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
  protected $sailor_eula_read_on;
  public $email_inbox_status;

  public function db_name() {
    return 'account';
  }

  public function db_type($field) {
    switch ($field) {
    case 'ts_role':
      return DB::T(DB::ROLE);
    case 'sailor_eula_read_on':
      return DB::T(DB::NOW);
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
    if ($this->can(Permission::EDIT_CONFERENCE_LIST)) {
      return DB::getConferences();
    }
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
   * @return Array:School
   */
  public function getSchools(Conference $conf = null, $effective = true, $active = true) {
    $obj = ($active) ? DB::T(DB::ACTIVE_SCHOOL) : DB::T(DB::SCHOOL);
    return DB::getAll($obj, $this->getSchoolsDBCond($conf, $effective));
  }

  /**
   * Searches this account's list of schools using given query.
   *
   * @param String $qry the string to search.
   * @param Conference $conf optional conference to limit to.
   * @param boolean $effective false to limit to directly assigned.
   * @param boolean $active false to include non-active schools.
   */
  public function searchSchools($qry, Conference $conf = null, $effective = true, $active = true) {
    $obj = ($active) ? DB::T(DB::ACTIVE_SCHOOL) : DB::T(DB::SCHOOL);
    return DB::searchAll($qry, $obj, $this->getSchoolsDBCond($conf, $effective));
  }

/**
   * Returns all the schools with at least one unregistered sailor.
   *
   * @param Conference $conf the possible to conference to narrow down
   * school list
   *
   * @param boolean $effective false to ignore permissions and return
   * only assigned values
   *
   * @param boolean $active true (default) to return only active schools
   *
   * @return Array:School
   */
  public function getSchoolsWithUnregisteredSailors(Conference $conf = null, $effective = true, $active = true) {
    $obj = ($active) ? DB::T(DB::ACTIVE_SCHOOL) : DB::T(DB::SCHOOL);
    return DB::getAll(
      $obj,
      new DBBool(
        array(
          $this->getSchoolsDBCond($conf, $effective),
          new DBCondIn(
            'id',
            DB::prepGetAll(
              DB::T(DB::UNREGISTERED_SAILOR),
              null,
              array('school')
            )
          )
        )
      )
    );
  }

  /**
   * Helper method to create the condition by which to search schools.
   *
   * @param Conference $conf the possible to conference to narrow down
   *   school list
   * @param boolean $effective false to ignore permissions and return
   *   only assigned values
   * @return DBExpression
   */
  public function getSchoolsDBCond(Conference $conf = null, $effective = true) {
    // Admin?
    if ($this->isAdmin() && $effective !== false) {
      if ($conf !== null) {
        return new DBCond('conference', $conf);
      }
      return null;
    }

    // Assigned
    $cond = new DBCondIn('id', DB::prepGetAll(DB::T(DB::ACCOUNT_SCHOOL), new DBCond('account', $this), array('school')));
    if ($effective !== false) {
      $cond = new DBBool(
        array(
          $cond,
          new DBCondIn(
            'conference',
            DB::prepGetAll(
              DB::T(DB::ACCOUNT_CONFERENCE),
              new DBCond('account', $this),
              array('conference')
            )
          )
        ),
        DBBool::mOR
      );
      if ($conf !== null) {
        $cond = new DBBool(array($cond, new DBCond('conference', $conf)));
      }
    }
    return $cond;
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
    if ($school == null) {
      return false;
    }
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
   * Returns all the sailors associated with the schools of this user.
   *
   * @param boolean $effective false to ignore permissions and return
   *   only assigned values
   * @param boolean $active true (default) to return only active schools
   * @return Array:Sailor
   */
  public function getSailors($effective = true, $active = true) {
    return DB::getAll(
      DB::T(DB::SAILOR),
      $this->getSailorsDBCond($effective, $active)
    );
  }

  public function searchSailors($qry, $effective = true, $active = true) {
    return DB::searchAll(
      $qry,
      DB::T(DB::SAILOR),
      $this->getSailorsDBCond($effective, $active)
    );
  }

  private function getSailorsDBCond($effective = true, $active = true) {
    $schoolObj = ($active) ? DB::T(DB::ACTIVE_SCHOOL) : DB::T(DB::SCHOOL);
    return new DBCondIn(
      'school',
      DB::prepGetAll(
        $schoolObj,
        $this->getSchoolsDBCond(null, $effective),
        array('id')
      )
    );
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
  public function getSchoolCondition($attr = 'school') {
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

  public function getVisibleAccountsCondition() {
    // Avoid returning null
    if ($this->isSuper()) {
      return new DBCond(1, 1);
    }

    // Not super: therefore only show admins
    $cond = new DBBool(
      array(
        new DBBool(
          array(
            new DBCond('admin', 1, DBCond::LE),
            new DBCond('admin', null)
          ),
          DBBool::mOR
        )
      )
    );

    // Not admin: therefore show non-admins
    if (!$this->isAdmin()) {
      $cond->add(
        new DBCondIn(
          'ts_role',
          DB::prepGetAll(DB::T(DB::ROLE), new DBCond('has_all', null, DBCond::NE), array('id')),
          DBCondIn::NOT_IN
        )
      );
    }

    return $cond;
  }

  // Student profiles

  /**
   * Returns all profiles owned by this account.
   *
   * @return Array:StudentProfile
   */
  public function getStudentProfiles() {
    return DB::getAll(DB::T(DB::ACTIVE_STUDENT_PROFILE), new DBCond('owner', $this));
  }

  /**
   * Returns all profiles under user's school (not necessarily owned by account).
   *
   * @return Array:StudentProfile
   * @see getStudentProfiles
   */
  public function getStudentProfilesUnderJurisdiction() {
    $cond = null;
    if (!$this->isAdmin()) {
      $cond = $this->getSchoolCondition('school');
    }
    return DB::getAll(DB::T(DB::ACTIVE_STUDENT_PROFILE), $cond);
  }

  public function hasStudentProfileJurisdiction(StudentProfile $profile) {
    if ($this->isAdmin()) {
      return true;
    }
    $res = DB::getAll(
      DB::T(DB::ACTIVE_STUDENT_PROFILE),
      new DBBool(
        array(
          new DBCond('id', $profile),
          $this->getSchoolCondition('school'),
        )
      )
    );
    return count($res) > 0;
  }

  public function canReceiveEmail() {
    return $this->email_inbox_status === self::EMAIL_INBOX_STATUS_RECEIVING;
  }
}
