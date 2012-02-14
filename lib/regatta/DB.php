<?php
/*
 * This file is part of TechScore
 */

require_once('mysqli/DBM.php');

/**
 * Database serialization manager for all of TechScore.
 *
 * @author Dayan Paez
 * @version 2012-01-07
 * @package dbm
 */
class DB extends DBM {

  // Template objects
  public static $CONFERENCE = null;
  public static $SCHOOL = null;
  public static $BURGEE = null;
  public static $BOAT = null;
  public static $VENUE = null;
  public static $MEMBER = null;
  public static $SAILOR = null;
  public static $COACH = null;
  public static $SCORER = null;
  public static $TEAM = null;
  public static $SINGLEHANDED_TEAM = null;
  public static $TEAM_NAME_PREFS = null;
  public static $SAIL = null;
  public static $NOTE = null;
  public static $RACE = null;
  public static $FINISH = null;
  public static $TEAM_PENALTY = null;
  public static $HOST_SCHOOL = null;
  public static $DAILY_SUMMARY = null;
  public static $REPRESENTATIVE = null;
  public static $RP_ENTRY = null;
  public static $SEASON = null;
  public static $NOW = null;

  public static $OUTBOX = null;
  public static $MESSAGE = null;
  public static $ACCOUNT = null;
  public static $ACCOUNT_SCHOOL = null;
  public static $REGATTA = null;
  public static $RP_LOG = null; // RpManager.php
  public static $RP_FORM = null; // RpManager.php
  public static $TEAM_DIVISION = null;
  public static $UPDATE_REQUEST = null; // UpdateRequest.php
  public static $UPDATE_LOG = null; // UpdateRequest.php
  public static $UPDATE_LOG_SEASON = null; // UpdateRequest.php

  // Public API: PublicDB.php
  public static $DT_REGATTA = null;
  public static $DT_TEAM = null;
  public static $DT_TEAM_DIVISION = null;
  public static $DT_RP = null;

  // The validation engine
  public static $V = null;

  public static function setConnectionParams($host, $user, $pass, $db) {
    // Template objects serialization
    self::$CONFERENCE = new Conference();
    self::$SCHOOL = new School();
    self::$BURGEE = new Burgee();
    self::$BOAT = new Boat();
    self::$VENUE = new Venue();
    self::$MEMBER = new Member();
    self::$SAILOR = new Sailor();
    self::$COACH = new Coach();
    self::$SCORER = new Scorer();
    self::$TEAM = new Team();
    self::$SINGLEHANDED_TEAM = new SinglehandedTeam();
    self::$TEAM_NAME_PREFS = new Team_Name_Prefs();
    self::$SAIL = new Sail();
    self::$NOTE = new Note();
    self::$RACE = new Race();
    self::$FINISH = new Finish();
    self::$TEAM_PENALTY = new TeamPenalty();
    self::$HOST_SCHOOL = new Host_School();
    self::$DAILY_SUMMARY = new Daily_Summary();
    self::$REPRESENTATIVE = new Representative();
    self::$RP_ENTRY = new RPEntry();
    self::$SEASON = new Season();
    self::$NOW = new DateTime();

    DBM::setConnectionParams($host, $user, $pass, $db);

    require_once('regatta/TSSoter.php');
    DB::$V = new TSSoter();
    DB::$V->setDBM('DB');
  }

  /**
   * Returns the conference with the given ID
   *
   * @param String $id the id of the conference
   * @return Conference the conference object
   */
  public static function getConference($id) {
    return self::get(self::$CONFERENCE, $id);
  }

  /**
   * Returns a list of conference objects
   *
   * @return a list of conferences
   */
  public static function getConferences() {
    return self::getAll(self::$CONFERENCE);
  }
  
  /**
   * Returns the school with the given ID, or null if none exists
   *
   * @return School|null $school with the given ID
   */
  public static function getSchool($id) {
    return self::get(self::$SCHOOL, $id);
  }

  /**
   * Returns a list of available boats
   *
   * @return Array<Boat> list of boats
   */
  public static function getBoats() {
    return self::getAll(self::$BOAT);
  }
  
  /**
   * Fetches the boat with the given ID
   *
   * @param int $id the ID of the boat
   * @return Boat|null
   */
  public static function getBoat($id) {
    return self::get(self::$BOAT, $id);
  }

  /**
   * Returns the venue object with the given ID
   *
   * @param String $id the id of the object
   * @return Venue the venue object, or null
   */
  public static function getVenue($id) {
    return self::get(self::$VENUE, $id);
  }

  /**
   * Get a list of registered venues.
   *
   * @return Array of Venue objects
   */
  public static function getVenues($start = null, $end = null) {
    return self::getAll(self::$VENUE);
  }

  /**
   * Sends a generic mail message to the given user with the given
   * subject, appending the correct headers (i.e., the "from"
   * field). This method uses the standard PHP mail function
   *
   * @param String $to the e-mail address to send to
   * @param String $subject the subject
   * @param String $body the body of the message, will be wrapped to
   * 72 characters
   * @return boolean the result, as returned by mail
   */
  public static function mail($to, $subject, $body) {
    if (Conf::$DIVERT_MAIL !== null) {
      $body = "Message meant for $to\n\n" . $body;
      $to = Conf::$DIVERT_MAIL;
      $subject = 'DIVERTED: ' . $subject;
    }
    return mail($to,
		$subject,
		wordwrap($body, 72),
		sprintf('From: %s', Conf::$TS_FROM_MAIL));
  }

  /**
   * Get all non-completed outgoing messages
   *
   * @return Array:Outbox the messages
   */
  public static function getPendingOutgoing() {
    return self::getAll(self::$OUTGOING, new DBCond('completion_time', null));
  }

  // ------------------------------------------------------------
  // Messages
  // ------------------------------------------------------------

  /**
   * Retrieve all messages for the given account in order
   *
   * @param Account $acc the account
   */
  public static function getMessages(Account $acc) {
    require_once('regatta/Message.php');
    return self::getAll(self::$MESSAGE, new DBCond('account', $acc->id));
  }

  /**
   * Retrieve all messages for the given account in order
   *
   * @param Account $acc the account
   */
  public static function getUnreadMessages(Account $acc) {
    require_once('regatta/Message.php');
    self::$MESSAGE->db_set_order(array('created'=>true));
    $l = self::getAll(self::$MESSAGE, new DBBool(array(new DBCond('account', $acc->id), new DBCond('read_time', null))));
    self::$MESSAGE->db_set_order();
    return $l;
  }

  /**
   * Adds the given message for the given user
   *
   * @param Account the user
   * @param String $sub the subject of the message
   * @param String $mes the message
   * @param boolean $email true to send e-mail message
   * @return Message the queued message
   */
  public static function queueMessage(Account $acc, $sub, $mes, $email = false) {
    require_once('regatta/Message.php');
    $mes = new Message();
    $mes->account = $acc->id;
    $mes->subject = $sub;
    $mes->content = $mes;
    self::set($mes);

    if ($email !== false)
      self::mail($acc->id, $sub, $mes);

    return $mes;
  }

  /**
   * Marks the given message as read using the current timestamp or
   * the one provided. Updates the Message object
   *
   * @param Message $mes
   * @param DateTime $time
   */
  public static function markRead(Message $mes, DateTime $time = null) {
    $mes->read_time = ($time === null) ? self::$NOW : $time;
    self::set($mes);
  }

  /**
   * Deletes the message (actually, marks it as "inactive")
   *
   * @param Message $mes the message to "delete"
   */
  public static function deleteMessage(Message $mes) {
    $mes->active = 0;
    self::set($mes);
  }

  /**
   * Sends mail to the authorities on behalf of the user
   *
   * @param Message $mes the message being replied
   * @param String $reply the reply
   */
  public static function reply(Message $mes, $reply) {
    $body = sprintf("Reply from: %s\n---------------------\n%s\n-------------------\n%s",
		    $mes->account->id,
		    $mes->content,
		    $reply);
    $res = self::mail(Conf::$ADMIN_MAIL, "[TechScore] Message reply", $body);
  }

  // ------------------------------------------------------------
  // Sailors
  // ------------------------------------------------------------

  /**
   * Fetches the Sailor with the given ID
   *
   * @param int $id the ID of the person
   * @return Sailor|null the sailor
   */
  public static function getSailor($id) {
    return DB::get(DB::$SAILOR, $id);
  }

  /**
   * Fetches the Sailor with the given ICSA ID
   *
   * @param int $id the ICSA ID of the sailor
   * @return Sailor|null the sailor
   */
  public static function getICSASailor($id) {
    $r = DB::getAll(DB::$SAILOR, new DBCond('icsa_id', $id));
    $s = (count($r) == 0) ? null : $r[0];
    unset($r);
    return $s;
  }
  
  public static function searchSailors($str) {
    return self::search(self::$SAILOR, $str, array('first_name', 'last_name', 'concat(first_name, " ", last_name)'));
  }

  // ------------------------------------------------------------
  // Account management
  // ------------------------------------------------------------

  /**
   * Returns the account with the given username
   *
   * @return Account the account with the given username, null if none
   * exist
   */
  public static function getAccount($id) {
    require_once('regatta/Account.php');
    return self::get(self::$ACCOUNT, $id);
  }
  
  /**
   * Returns all the pending users, using the given optional indices
   * to limit the list, like the range function in Python.
   *
   * <ul>
   *   <li>To fetch the first ten: <code>getRegattas(10);</code></li>
   *   <li>To fetch the next ten:  <code>getRegattas(10, 20);</code><li>
   * </ul>
   *
   * @param int $start the start index (inclusive)
   * @param int $end   the end index (exclusive)
   * @return Array:Account
   */
  public static function getPendingUsers() {
    require_once('regatta/Account.php');
    return self::getAll(self::$ACCOUNT, new DBCond('status', Account::STAT_PENDING));
  }

  /**
   * Returns just the administrative users
   *
   * @return Array:Account
   */
  public static function getAdmins() {
    require_once('regatta/Account.php');
    return self::getAll(self::$ACCOUNT, new DBBool(array(new DBCond('status', Account::STAT_ACTIVE),
							 new DBCond('admin', 0, DBCond::GT))));
  }

  /**
   * Returns the unique MD5 hash for the given account
   *
   * @param Account $acc the account to hash
   * @return String the hash
   * @see getAccountFromHash
   */
  public static function getHash(Account $acc) {
    return md5($acc->last_name.$acc->id.$acc->first_name);
  }

  /**
   * Fetches the account which has the hash provided. This hash is
   * calculated as an MD5 sum of last name, username, and first name
   *
   * @param String $hash the hash
   * @return Account|null the matching account or null if none match
   */
  public static function getAccountFromHash($hash) {
    require_once('regatta/Account.php');
    $res = self::getAll(self::$ACCOUNT, new DBCond('md5(concat(last_name, id, first_name))', $hash));
    $r = (count($res) == 0) ? null : $res[0];
    unset($res);
    return $r;
  }

  /**
   * Returns a list of accounts fulfilling the given role
   *
   * @param String $role a possible Account role
   * @return Array:Account the list of accounts
   * @throws InvalidArgumentException if provided role is invalid
   */
  public static function getAccounts($role) {
    $roles = Account::getRoles();
    if (!isset($roles[$role]))
      throw new InvalidArgumentException("Invalid role provided: $role.");
    return self::getAll(self::$ACCOUNT, new DBCond('role', $role));
  }
  
  /**
   * Checks that the account holder is active. Otherwise, redirect to
   * license. Otherwise, redirect out
   *
   * @param Account $user the user to check
   * @throws InvalidArgumentException if invalid parameter
   * @TODO this should be migrated to using account
   */
  public static function requireActive(Account $user) {
    switch ($user->status) {
    case "active":
      return;

    case "accepted":
      WS::go('/license');

    default:
      WS::go('/');
    }
  }

  /**
   * Returns the boat that designated as the default for the school
   *
   * @param School $school the school whose default boat to fetch
   * @return Boat the boat
   */
  public static function getPreferredBoat(School $school) {
    $res = self::getAll(self::$BOAT);
    $r = (count($res) == 0) ? null : $res[0];
    unset($res);
    return $r;
  }

  // ------------------------------------------------------------
  // Utilities
  // ------------------------------------------------------------
  
  /**
   * Creates array of range from string. On fail, return null. Expects
   * argument to contain only spaces, commas, dashes and numbers,
   * greater than 0
   *
   * @param String $str the range to parse
   * @return Array the numbers in the string in numerical order
   */
  public static function parseRange($str) {
    // Check for valid characters
    if (preg_match('/[^0-9 ,-]/', $str) == 1)
      return null;

    // Remove leading and trailing spaces, commasn and hyphens
    $str = preg_replace('/^[ ,-]*/', '', $str);
    $str = preg_replace('/[ ,-]*$/', '', $str);
    $str = preg_replace('/ +/', ' ', $str);

    // Squeeze spaces
    $str = preg_replace('/ +/', ' ', $str);

    // Make interior spaces into commas, and squeeze commas
    $str = str_replace(" ", ",", $str);
    $str = preg_replace('/,+/', ',', $str);

    // Squeeze hyphens
    $str = preg_replace('/-+/', '-', $str);

    $sub = explode(",", $str);
    $list = array();
    foreach ($sub as $s) {
      $delims = explode("-", $s);
      $start  = $delims[0];
      $end    = $delims[count($delims)-1];
    
      // Check limits
      if ($start > $end) // invalid range
	return null;
      for ($i = $start; $i <= $end; $i++)
	$list[] = (int)$i;
    }
    
    return array_unique($list);
  }

  /**
   * Creates a string representation of the integers in the list
   *
   * @param Array<int> $list the numbers to be made into a range
   * @return String the range as a string
   */
  public static function makeRange(Array $list) {
    // Must be unique
    $list = array_unique($list);
    if (count($list) == 0)
      return "";

    // and sorted
    sort($list, SORT_NUMERIC);
  
    $mid_range = false;
    $last  = $list[0];
    $range = $last;
    for ($i = 1; $i < count($list); $i++) {
      if ($list[$i] == $last + 1)
	$mid_range = true;
      else {
	$mid_range = false;
	if ($last != substr($range,-1))
	  $range .= "-$last";
	$range .= ",$list[$i]";
      }
      $last = $list[$i];
    }
    if ( $mid_range )
      $range .= "-$last";

    return $range;
  }

  /**
   * Perfect for transition, creates ONE and only ONE regatta
   *
   * @param String $id the regatta ID
   * @return Regatta the regatta object
   * @throws InvalidArgumentException if illegal value
   */
  public static function getRegatta($id) {
    require_once('regatta/Regatta.php');
    return DB::get(DB::$REGATTA, $id);
  }

  /**
   * Returns the season with the given ID, or null.
   *
   * @param String $id the ID of the season
   * @return Season|null
   */
  public static function getSeason($id) {
    return DB::get(DB::$SEASON, $id);
  }
}

/**
 * Encapsulates a conference
 *
 * @author Dayan Paez
 * @version 2012-01-07
 */
class Conference extends DBObject {
  public $name;
  public function __toString() {
    return $this->id;
  }
  protected function db_cache() { return true; }
  
  /**
   * Returns a list of users from this conference
   *
   * @return Array:Account list of users
   */
  public function getUsers() {
    require_once('regatta/Account.php');
    return DB::getAll(DB::$ACCOUNT,
		      new DBCondIn('school', DB::prepGetAll(DB::$SCHOOL, new DBCond('conference', $this), array('id'))));
  }

  /**
   * Returns a list of school objects which are in the specified
   * conference.
   *
   * @return a list of schools in the conference
   */
  public function getSchools() {
    return DB::getAll(DB::$SCHOOL, new DBCond('conference', $this));
  }
}

/**
 * Burgees: primary key matches with (and is a foreign key) to school.
 *
 * @author Dayan Paez
 * @version 2012-01-07
 */
class Burgee extends DBObject {
  public $filedata;
  protected $last_updated;
  protected $school;
  public $updated_by;

  public function db_type($field) {
    switch ($field) {
    case 'filedata': return DBQuery::A_BLOB;
    case 'last_updated': return DB::$NOW;
    case 'school': return DB::$SCHOOL;
    default:
      return parent::db_type($field);
    }
  }
}

/**
 * Schools
 *
 * @author Dayan Paez
 * @version 2012-01-07
 */
class School extends DBObject {
  public $nick_name;
  public $name;
  public $city;
  public $state;
  protected $conference;
  protected $burgee;

  public function db_name() { return 'school'; }
  public function db_type($field) {
    switch ($field) {
    case 'conference': return DB::$CONFERENCE;
    case 'burgee': return DB::$BURGEE;
    default:
      return parent::db_type($field);
    }
  }
  protected function db_cache() { return true; }
  protected function db_order() { return array('name'=>true); }
  public function __toString() { return $this->name; }
  
  /**
   * Returns a list of sailors for the specified school
   *
   * @param School $school the school object
   * @param Sailor::const $gender null for both or the gender code
   *
   * @param mixed $active default "all", returns ONLY the active ones,
   * false to return ONLY the inactive ones, anything else for all.
   *
   * @return Array:Sailor list of sailors
   */
  public function getSailors($gender = null, $active = "all") {
    $cond = new DBBool(array(new DBCond('icsa_id', null, DBCond::NE), new DBCond('school', $this)));
    if ($active === true)
      $cond->add(new DBCond('active', null, DBCond::NE));
    if ($active === false)
      $cond->add(new DBCond('active', null));
    if ($gender !== null)
      $cond->add(new DBCond('gender', $gender));
    return DB::getAll(DB::$SAILOR, $cond);
  }

  /**
   * Returns a list of unregistered sailors for the specified school
   *
   * @param School $school the school object
   * @param RP::const $gender null for both or the gender code
   *
   * @param mixed $active default "all", returns ONLY the active ones,
   * false to return ONLY the inactive ones, anything else for all.
   *
   * @return Array<Sailor> list of sailors
   */
  public function getUnregisteredSailors($gender = null, $active = "all") {
    $cond = new DBBool(array(new DBCond('icsa_id', null, DBCond::NE), new DBCond('school', $this)));
    if ($active === true)
      $cond->add(new DBCond('active', null, DBCond::NE));
    if ($active === false)
      $cond->add(new DBCond('active', null));
    if ($gender !== null)
      $cond->add(new DBCond('gender', $gender));
    return DB::getAll(DB::$SAILOR, $cond);
  }

  /**
   * Returns a list of coaches as sailor objects for the specified
   * school
   *
   * @param School $school the school object
   *
   * @param mixed $active default "all", returns ONLY the active ones,
   * false to return ONLY the inactive ones, anything else for all.
   *
   * @param boolean $only_registered true to narrow down to ICSA
   *
   * @return Array:Coach list of coaches
   */
  public function getCoaches($active = 'all', $only_registered = false) {
    $cond = new DBBool(array(new DBCond('school', $this)));
    if ($active === true)
      $cond->add(new DBCond('active', null, DBCond::NE));
    if ($active === false)
      $cond->add(new DBCond('active', null));
    if ($only_registered !== false)
      $cond->add(new DBCond('icsa_id', null, DBCond::NE));
    return DB::getAll(DB::$COACH, $cond);
  }

  /**
   * Returns an ordered list of the team names for this school
   *
   * @return Array:String ordered list of the school names
   */
  public function getTeamNames() {
    $list = array();
    foreach (DB::getAll(DB::$TEAM_NAME_PREFS, new DBCond('school', $this)) as $pref)
      $list[] = (string)$pref;
    return $list;
  }

  /**
   * Sets the team names for the given school
   *
   * @param School $school school whose valid team names to set
   * @param Array:String $names an ordered list of team names
   */
  public function setTeamNames(Array $names) {
    // Strategy, update as many as are the same, then remove old extra
    // ones, or add any new ones
    $top_rank = count($names);
    $curr = $this->getTeamNames();
    for ($i = 0; $i < count($names) && $i < count($curr); $i++) {
      $tnp = $curr[$i];
      $tnp->name = $names[$i];
      $tnp->rank = $top_rank--;
      DB::set($tnp);
    }
    for (; $i < count($curr); $i++)
      DB::remove($curr[$i]);
    for (; $i < count($names); $i++) {
      $tnp = new Team_Name_Prefs();
      $tnp->school = $this;
      $tnp->name = $names[$i];
      $tnp->rank = $top_rank--;
      DB::set($tnp);
    }
  }
}

/**
 * A boat class, like Techs and FJs.
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
class Boat extends DBObject {
  public $name;
  public $occupants;

  protected function db_cache() { return true; }
  public function __toString() { return $this->name; }
}

/**
 * Location where a regatta might take place
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
class Venue extends DBObject {
  public $name;
  public $address;
  public $city;
  public $state;
  public $zipcode;

  protected function db_order() { return array('name'=>true); }
  public function __toString() { return $this->name; }
}

/**
 * A division, one of possibly four: A, B, C, and D. Used primarily
 * for type hinting.
 *
 * @author Dayan Paez
 * @version 2009-10-05
 */
class Division {

  private $value;
  public function __construct($val) {
    $this->value = $val;
  }
  public function value() {
    return $this->value;
  }
  public function __toString() {
    return $this->value;
  }

  // Static variables
  private static $A;
  private static $B;
  private static $C;
  private static $D;

  // Static functions
  
  /**
   * Gets A division object
   *
   * @return A division
   */
  public static function A() {
    if (self::$A == null) {
      self::$A = new Division("A");
    }
    return self::$A;
  }
  /**
   * Gets B division object
   *
   * @return B division
   */
  public static function B() {
    if (self::$B == null) {
      self::$B = new Division("B");
    }
    return self::$B;
  }
  /**
   * Gets C division object
   *
   * @return C division
   */
  public static function C() {
    if (self::$C == null) {
      self::$C = new Division("C");
    }
    return self::$C;
  }
  /**
   * Gets D division object
   *
   * @return D division
   */
  public static function D() {
    if (self::$D == null) {
      self::$D = new Division("D");
    }
    return self::$D;
  }
  /**
   * Gets the division object with the given value
   *
   * @param the division value to retrieve
   * @return the division object
   */
  public static function get($val) {
    switch ($val) {
    case "A":
      return self::A();
    case "B":
      return self::B();
    case "C":
      return self::C();
    case "D":
      return self::D();
    default:
      throw new InvalidArgumentException("Invalid division value: $val");
    }
  }

  /**
   * Fetches an associative array indexed by the value of the division
   * mapping to the division object
   *
   * @return Array
   */
  public static function getAssoc() {
    return array("A"=>Division::A(),
		 "B"=>Division::B(),
		 "C"=>Division::C(),
		 "D"=>Division::D());
  }
}

/**
 * Represents either a student or a coach as a member of a school
 *
 * @author Dayan Paez
 * @version 2012-02-07
 */
class Member extends DBObject {
  protected $school;
  public $last_name;
  public $first_name;
  public $year;
  public $role;
  public $icsa_id;
  public $gender;
  public $active;
  public $regatta_added;

  const MALE = 'M';
  const FEMALE = 'F';

  const COACH = 'coach';
  const STUDENT = 'student';

  public function db_type($field) {
    switch ($field) {
    case 'school': return DB::$SCHOOL;
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('last_name'=>true, 'first_name'=>true); }
  public function db_name() { return 'sailor'; }

  public static function getGenders() {
    return array(self::MALE => "Male", self::FEMALE => "Female");
  }

  public function isRegistered() {
    return $this->icsa_id !== null;
  }
  public function __toString() {
    $year = "";
    if ($this->role == 'student')
      $year = " '" . (($this->year > 0) ? substr($this->year, -2) : "??");
    $name = sprintf("%s %s%s",
		    $this->first_name,
		    $this->last_name,
		    $year);
    if (!$this->isRegistered())
      $name .= " *";
    return $name;
  }
}

/**
 * Encapsulates a sailor, whether registered with ICSA or not.
 *
 * @author Dayan Paez
 * @version 2009-10-04
 */
class Sailor extends Member {
  public function __construct() {
    $this->role = Member::STUDENT;
  }
  public function db_where() { return new DBCond('role', 'student'); }
}

/**
 * A coach (a sailor with role=coach)
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
class Coach extends Member {
  public function db_where() { return new DBCond('role', 'coach'); }
  public function __construct() {
    $this->role = Member::COACH;
  }
}

/**
 * Host account for a regatta (as just an ID) [many-to-many]
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
class Scorer extends DBObject {
  public $regatta;
  protected $account;
  public $principal;

  public function db_type($field) {
    switch ($field) {
    case 'account': return DB::$ACCOUNT;
    default:
      return parent::db_type($field);
    }
  }
}

/**
 * Encapsulates a team, or a linking table between schools and regattas
 *
 * @author Dayan Paez
 * @version 2012-01-10
 */
class Team extends DBObject {
  public $name;
  protected $school;
  protected $regatta; // change to protected when using DBM

  public function db_name() { return 'team'; }
  protected function db_order() { return array('school'=>true, 'id'=>true); }
  protected function db_cache() { return true; }
  public function db_type($field) {
    switch ($field) {
    case 'school': return DB::$SCHOOL;
    case 'regatta': return DB::$REGATTA;
    default:
      return parent::db_type($field);
    }
  }
  public function __toString() {
    return $this->__get('school')->nick_name . ' ' . $this->name;
  }
}

/**
 * Team for the purpose of a singlehanded event. For those events, the
 * string representation of a team is the sailor's name, if such exists.
 *
 * @author Dayan Paez
 * @version 2012-01-16
 */
class SinglehandedTeam extends Team {

  /**
   * Overrides the parent's method for retrieving name
   *
   * @param String $name the name of the property, only "name" is overriden
   */
  public function &__get($name) {
    if ($name == 'name')
      return $this->getQualifiedName();
    return parent::__get($name);
  }

  /**
   * Returns either the skipper in A division, or the team name
   *
   * @return String name of the team or sailor
   */
  private function getQualifiedName() {
    if ($this->regatta == null) return parent::__get("name");

    try {
      $rps = $this->__get('regatta')->getRpManager()->getRP($this, Division::A(), RP::SKIPPER);
      if (count($rps) == 0)
	return parent::__get("name");

      // Should be one, but just in case
      $sailors = array();
      foreach ($rps as $rp)
	$sailors[] = $rp->sailor;
      return implode("/", $sailors);
    } catch (Exception $e) {
      return parent::__get("name");
    }
  }

  /**
   * Overrides the parent __toString() method to print the skipper(s)
   * in A Division, or the team name
   *
   * @return String the string representation of the team
   */
  public function __toString() {
    return sprintf("%s %s", $this->__get('school')->name, $this->getQualifiedName());
  }
}

/**
 * Preference for team name, as specified by a school's user
 *
 * @author Dayan Paez
 * @version 2012-01-11
 */
class Team_Name_Prefs extends DBObject {
  protected $school;
  public $name;
  public $rank;

  public function db_name() { return 'team_name_prefs'; }
  public function db_type($field) {
    if ($field == 'school')
      return DB::$SCHOOL;
    return parent::db_type($field);
  }
  protected function db_order() { return array('school'=>true, 'rank'=>false); }
  public function __toString() { return (string)$this->name; }
}

/**
 * Encapsulates a sail: a boat in a given race for a given team
 *
 * @author Dayan Paez
 * @version 2012-01-11
 */
class Sail extends DBObject {
  public $sail;
  protected $race;
  protected $team;

  public function db_name() { return 'rotation'; }
  public function db_type($field) {
    switch ($field) {
    case 'team': return DB::$TEAM;
    case 'race': return DB::$RACE;
    default:
      return parent::db_type($field);
    }
  }
  public function __toString() { return (string)$this->sail; }
}

/**
 * An observation during a race
 *
 * @author Dayan Paez
 * @version 2012-01-11
 */
class Note extends DBObject {
  public $observation;
  public $observer;
  protected $race;
  protected $noted_at;

  protected function db_order() { return array('noted_at' => true); }
  public function db_name() { return 'observation'; }
  public function db_type($field) {
    switch ($field) {
    case 'noted_at': return DB::$NOW;
    case 'race': return DB::$RACE;
    default:
      return parent::db_type($field);
    }
  }
  public function __toString() { return $this->observation; }
}

/**
 * Race object: a number and a division
 *
 * @author Dayan Paez
 * @version 2012-01-12
 */
class Race extends DBObject {
  protected $regatta;
  protected $division;
  protected $boat;
  public $number;
  public $scored_by;

  public function db_name() { return 'race'; }
  public function db_type($field) {
    switch ($field) {
    case 'division': return DBQuery::A_STR;
    case 'boat': return DB::$BOAT;
    case 'regatta': return DB::$REGATTA;
    }
  }
  protected function db_cache() { return true; }
  protected function db_order() {
    return array('number'=>true, 'division'=>true);
  }
  public function &__get($name) {
    if ($name == 'division') {
      if ($this->division === null || $this->division instanceof Division)
	return $this->division;
      $div = Division::get($this->division);
      return $div;
    }
    return parent::__get($name);
  }
  public function __toString() {
    return $this->number . $this->division;
  }
  
  /**
   * Parses the string and returns a Race object with the
   * corresponding division and number. Note that the race object
   * obtained is orphan.
   *
   * @param String $text the text representation of a race (3A, B12)
   * @return Race a race object
   * @throws InvalidArgumentException if unable to parse
   */
  public static function parse($text) {
    $race = (string)$text;
    try {
      $race = str_replace(" ", "", $race);
      $race = str_replace("-", "", $race);
      $race = strtoupper($race);

      if (in_array($race[0], array("A", "B", "C", "D"))) {
	// Move division letter to end of string
	$race = substr($race, 1) . substr($race, 0, 1);
      }

      if (in_array($race[strlen($race)-1], array("A", "B", "C", "D"))) {
	$race_a = sscanf($race, "%d%s");
      }
      else
	throw new InvalidArgumentException("Race is missing division.");;

      if (empty($race_a[0]) || empty($race_a[1])) {
	throw new InvalidArgumentException("Race is missing division or number.");
      }

      $race = new Race();
      $race->division = new Division($race_a[1]);
      $race->number   = $race_a[0];
      return $race;
    }
    catch (Exception $e) {
      throw new InvalidArgumentException("Unable to parse race.");
    }
  }

  /**
   * Compares races by number, then division.
   *
   * @param Race $r1 the first race
   * @param Race $r2 the second race
   * @return negative should $r1 have a lower number, or failing that, a
   * lower division than $r2; positive if the opposite is true; 0 if they
   * are equal
   */
  public static function compareNumber(Race $r1, Race $r2) {
    $diff = $r1->number - $r2->number;
    if ($diff != 0) return $diff;
    return ord((string)$r1->division) - ord((string)$r2->division);
  }
}

/**
 * Race finish: encompasses a team's finish record in a race,
 * including possible penalties, breakdowns, etc. 
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class Finish extends DBObject {
  protected $race;
  protected $team;
  protected $entered;
  public $penalty;
  /**
   * @var int the assigned point value (for breakdowns/penalties)
   */
  public $amount;
  /**
   * @var int the "default" amount in case of dropped penalty
   */
  public $earned;
  public $displace;
  public $comments;
  /**
   * @var int the numerical score
   */
  protected $score;
  public $explanation;

  public function db_name() { return 'finish'; }
  protected function db_order() { return array('entered'=>true); }
  protected function db_cache() { return true; }
  public function db_type($field) {
    switch ($field) {
    case 'race': return DB::$RACE;
    case 'team': return DB::$TEAM;
    case 'entered': return DB::$NOW;
    case 'score': return DBQuery::A_STR;
    default:
      return parent::db_type($field);
    }
  }

  /**
   * Provides for a textual representation of the finish's place
   *
   * @return String
   */
  public function getPlace() {
    return ($this->penalty === null) ? $this->score : $this->penalty;
  }

  public function __set($name, $value) {
    if ($name == 'score') {
      if ($value instanceof Score) {
	$this->score = $value->score;
	$this->explanation = $value->explanation;
      }
      elseif ($value === null) {
	$this->score = null;
	$this->explanation = null;
      }
      else
	throw new InvalidArgumentException("Score property must be Score object.");
      return;
    }
    parent::__set($name, $value);
  }

  /**
   * Creates a new finish with the give id, team and regatta. This is
   * legacy from previous incarnation of TechScore to facilitate
   * migration and manual generation of finish object. Arguments
   * overwrite default values from DBM object creation.
   *
   * @param int $id the id of the finish
   * @param Team $team the team
   * @param Race $race the race
   */
  public function __construct($id = null, Race $race = null, Team $team = null) {
    if ($id !== null) $this->id = $id;
    if ($race !== null) $this->race = $race;
    if ($team !== null) $this->team = $team;
  }

  /**
   * Attaches the given finish modifier to this finish. This is
   * superior to assigning the values directly. Trust me.
   *
   * @param FinishModifier $mod the modifier
   */
  public function setModifier(FinishModifier $mod = null) {
    if ($mod instanceof FinishModifier) {
      $this->amount = $mod->amount;
      $this->penalty = $mod->type;
      $this->comments = $mod->comments;
      $this->displace = $mod->displace;
      $this->earned = $mod->earned;
    }
    else {
      $this->amount = null;
      $this->penalty = null;
      $this->comments = null;
      $this->displace = null;
      $this->earned = null;
    }
  }

  /**
   * Gets the finish modifier, if any, for this finish. This object
   * will be created each time this method is invoked.
   *
   * @return FinishModifier the modifier
   */
  public function getModifier() {
    if ($this->penalty === null)
      return null;
    $pens = Penalty::getList();
    if (isset($pens[$this->penalty]))
      return new Penalty($this->penalty, $this->amount, $this->comments, $this->displace);
    return new Breakdown($this->penalty, $this->amount, $this->comments, $this->displace);
  }
  
  
  // Comparators

  /**
   * Compare by entered value
   *
   * @param Finish $f1 the first finish
   * @param Finish $f2 the second finish
   * @return < 0 if $f1 is less than $f2, 0 if they are the same, 1 if
   * it comes after
   */
  public static function compareEntered(Finish $f1, Finish $f2) {
    return $f1->__get('entered')->format("U") - $f2->__get('entered')->format("U");
  }
}

/**
 * Penalty for a team in a division
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class TeamPenalty extends DBObject {
  // Constants
  const PFD = "PFD";
  const LOP = "LOP";
  const MRP = "MRP";
  const GDQ = "GDQ";

  public static function getList() {
    return array(TeamPenalty::PFD=>"PFD: Illegal lifejacket",
		 TeamPenalty::LOP=>"LOP: Missing pinnie",
		 TeamPenalty::MRP=>"MRP: Missing RP info",
		 TeamPenalty::GDQ=>"GDQ: General disqualification");
  }

  protected $team;
  protected $division;
  public $type;
  public $comments;

  public function db_name() { return 'penalty_team'; }
  public function db_type($field) {
    switch ($field) {
    case 'team': return DB::$TEAM;
    case 'division': return DBQuery::A_STR;
    default:
      return parent::db_type($field);
    }
  }

  public function &__get($name) {
    if ($name == 'division')
      return Division::get($this->division);
    return parent::__get($name);
  }
  public function __set($name, $value) {
    if ($name == 'division')
      $this->division = (string)$value;
    else
      parent::__set($name, $value);
  }
  
  /**
   * String representation, really useful for debugging purposes
   *
   * @return String string representation
   */
  public function __toString() {
    return sprintf("%s|%s|%s|%s",
		   $this->team,
		   $this->division,
		   $this->type,
		   $this->comments);
  }
}

/**
 * Relationship between regatta and hosting school.
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class Host_School extends DBObject {
  protected $regatta;
  protected $school;
  public function db_type($field) {
    switch ($field) {
    case 'school': return DB::$SCHOOL;
    case 'regatta': return DB::$REGATTA;
    default:
      return parent::db_type($field);
    }
  }
}

/**
 * Event summary for a given day of sailing (one to many with regatta)
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class Daily_Summary extends DBObject {
  public $regatta;
  public $summary;
  protected $summary_date;

  public function db_name() { return 'daily_summary'; }
  protected function db_order() { return array('regatta'=>true, 'summary_date'=>true); }
  public function db_type($field) {
    switch ($field) {
    case 'summary_date': return DB::$NOW;
    default:
      return parent::db_type($field);
    }
  }
}

/**
 * Link between Sailor and Team: representative for the RP
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class Representative extends DBObject {
  protected $team;
  protected $sailor;
  
  public function db_type($field) {
    switch ($field) {
    case 'team': return DB::$TEAM;
    case 'sailor': return DB::$MEMBER;
    default:
      return parent::db_type($field);
    }
  }
}

/**
 * An individual record of participation entry: a specific sailor in a
 * specific race for a specific team, in a specific boat_role
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class RPEntry extends DBObject {
  protected $race;
  protected $team;
  protected $sailor;
  public $boat_role;

  public function db_name() { return 'rp'; }
  public function db_type($field) {
    switch ($field) {
    case 'race': return DB::$RACE;
    case 'team': return DB::$TEAM;
    case 'sailor': return DB::$SAILOR;
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('team'=>true, 'race'=>true); }
}

/**
 * Encapsulates a season, either fall/spring, etc, with a start and
 * end date
 *
 * @author Dayan Paez
 * @version 2012-01-16
 */
class Season extends DBObject {
  const FALL = "fall";
  const SUMMER = "summer";
  const SPRING = "spring";
  const WINTER = "winter";

  public $season;
  protected $start_date;
  protected $end_date;

  public function db_type($field) {
    switch ($field) {
    case 'start_date':
    case 'end_date':
      return DB::$NOW;
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('start_date'=>false); }
  protected function db_cache() { return true; }

  /**
   * Wrapper to be deprecated
   *
   */
  public function getSeason() {
    return $this->season;
  }
  public function getYear() {
    return $this->__get('start_date')->format('Y');
  }
  /**
   * For Fall starting in 2011: f11
   *
   */
  public function __toString() {
    return $this->id;
  }
  
  /**
   * For Fall starting in 2011, return "Fall 2011"
   */
  public function fullString() {
    return sprintf("%s %s", ucfirst((string)$this->season), substr($this->getYear(), 2));
  }

  /**
   * Returns a list of week numbers in this season. Note that weeks go
   * Monday through Sunday.
   *
   * @return Array:int the week number in the year
   */
  public function getWeeks() {
    $weeks = array();
    for ($i = $this->start_date->format('W'); $i < $this->end_date->format('W'); $i++)
      $weeks[] = $i;
    return $weeks;
  }

  // ------------------------------------------------------------
  // Regattas
  // ------------------------------------------------------------

  /**
   * Returns all the regattas in this season which are not personal
   *
   * @return Array:Regatta
   */
  public function getRegattas() {
    require_once('regatta/Regatta.php');
    return DB::getAll(DB::$REGATTA,
		      new DBBool(array(new DBCond('start_time', $this->start_date, DBCond::GE),
				       new DBCond('start_time', $this->end_date,   DBCond::LT),
				       new DBCond('type', Regatta::TYPE_PERSONAL, DBCond::NE))));
  }

  /**
   * Get a list of regattas in this season in which the given
   * school participated. This is a convenience method.
   *
   * @param School $school the school whose participation to verify
   * @return Array:Regatta
   */
  public function getParticipation(School $school) {
    return DB::getAll(DB::$REGATTA,
		      new DBBool(array(new DBCond('start_time', $this->start_date, DBCond::GE),
				       new DBCond('start_time', $this->end_date,   DBCond::LT),
				       new DBCondIn('id', DB::prepGetAll(DB::$TEAM, new DBCond('school', $school), array('regatta'))))));
  }

  // ------------------------------------------------------------
  // Static methods
  // ------------------------------------------------------------

  /**
   * Fetches all the regattas in all the given seasons
   *
   * @param Array:Season all the seasons to consider
   * @return Array:Regatta
   */
  public static function getRegattasInSeasons(Array $seasons) {
    if (count($seasons) == 0)
      return array();
    $cond = new DBBool(array(), DBBool::mOR);
    foreach ($seasons as $season) {
      $cond->add(new DBBool(array(new DBCond('start_time', $season->start_date, DBCond::GE),
				  new DBCond('start_time', $season->end_date,   DBCond::LT))));
    }
    return DB::getAll(DB::$REGATTA, $cond);
  }
  
  /**
   * Returns the season object, if any, that surrounds the given date.
   *
   * This method replaces the former constructor for Season, for which
   * there was no guarantee of a season existing.
   *
   * @param DateTime $date the date whose season to get
   * @return Season|null the season for $date
   */
  public static function forDate(DateTime $date) {
    $res = DB::getAll(DB::$SEASON, new DBBool(array(new DBCond('start_date', $date, DBCond::LE),
						    new DBCond('end_date', $date, DBCond::GE))));
    $r = (count($res) == 0) ? null : $res[0];
    unset($res);
    return $r;
  }

  /**
   * Returns a list of the seasons for which there are public
   * regattas, ordered in ascending chronological order.
   *
   * @return Array:Season the list
   */
  public static function getActive() {
    require_once('regatta/PublicDB.php');
    DB::$SEASON->db_set_order(array('start_date'=>true));
    $res = DB::getAll(DB::$SEASON, new DBCondIn('id', DB::prepGetAll(DB::$DT_REGATTA, null, array('season'))));
    DB::$SEASON->db_set_order();
    return $res;
  }
}

// ------------------------------------------------------------
// Useful non-DBObjects
// ------------------------------------------------------------

/**
 * Encapsulates a score. Objects can only be created. Their
 * attributes are not setable after that.
 *
 * @author Dayan Paez
 * @version 2010-01-30
 */
class Score {

  private $score;
  private $explanation;

  /**
   * Create a score with the given parameters
   *
   * @param int $score the numerical score
   * @param String $exp the explanation
   */
  public function __construct($score, $exp = "") {
    $this->score = (int)$score;
    $this->explanation = $exp;
  }

  /**
   * Fetches the given value
   *
   */
  public function __get($name) {
    return $this->$name;
  }
}

/**
 * Encapsulates an immutable penalty or breakdown
 *
 * @author Dayan Paez
 * @version 2011-01-31
 */
abstract class FinishModifier {

  public $amount;
  public $type;
  public $comments;
  /**
   * @var boolean when scoring penalty or breakdown, should the score
   * displace other finishers behind this one? Note that for penalty,
   * this is usually 'yes', which leads to everyone else being bumped
   * up. For breakdowns, however, this is usually 'no'. Note that this
   * is invalid if the 'amount' is non-positive.
   */
  public $displace;
  
  /**
   * @var int the minimum score than an averaged breakdown
   * deserves. This is tracked by the scoring algorithm so that an
   * entire race need not be re-scored just to determine a handicapped
   * team's finish average score; and to keep that average from never
   * being worse than that team's EARNED score, sans breakdown.
   */
  public $earned;
  
  /**
   * Fetches an associative list of the different penalty types
   *
   * @return Array<Penalty::Const,String> the different penalties
   */
  public static function getList() {
    return array();
  }

  /**
   * Creates a new penalty, of empty type by default
   *
   * @param String $type, one of the class constants
   * @param int $amount (optional) the amount if assigned, or -1 for automatic
   * @param String $comments (optional)
   *
   * @throws InvalidArgumentException if the type is set to an illegal
   * value
   */
  public function __construct($type, $amount = -1, $comments = "", $displace = 0) {
    $this->type = $type;
    $this->amount = (int)$amount;
    $this->comments = $comments;
    $this->displace = $displace;
  }

  /**
   * String representation, really useful for debugging purposes
   *
   * @return String string representation
   */
  public function __toString() {
    return sprintf("%s|%s|%s", $this->type, $this->amount, $this->comments);
  }
}

/**
 * Encapsulates a breakdown
 *
 * @author Dayan Paez
 * @version 2010-01-25
 * @package regatta
 */
class Breakdown extends FinishModifier {

  // Constants
  const RDG = "RDG";
  const BKD = "BKD";
  const BYE = "BYE";

  public static function getList() {
    return array(Breakdown::BKD => "BKD: Breakdown",
		 Breakdown::RDG => "RDG: Yacht Given Redress",
		 Breakdown::BYE => "BYE: Team is awarded average");
  }
}

/**
 * Encapsulates a penalty
 *
 * @author Dayan Paez
 * @version 2010-01-25
 */
class Penalty extends FinishModifier {

  // Constants
  const DSQ = "DSQ";
  const RAF = "RAF";
  const OCS = "OCS";
  const DNF = "DNF";
  const DNS = "DNS";

  /**
   * Fetches an associative list of the different penalty types
   *
   * @return Array<Penalty::Const,String> the different penalties
   */
  public static function getList() {
    return array(Penalty::DSQ => "DSQ: Disqualification",
		 Penalty::RAF => "RAF: Retire After Finishing",
		 Penalty::OCS => "OCS: On Course Side after start",
		 Penalty::DNF => "DNF: Did Not Finish",
		 Penalty::DNS => "DNS: Did Not Start");
  }
}
?>