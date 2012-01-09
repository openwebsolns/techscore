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
  public static $SAILOR = null;
  public static $COACH = null;
  public static $SEASON = null;
  public static $NOW = null;

  public static $OUTBOX = null;
  public static $MESSAGE = null;
  public static $ACCOUNT = null;

  public static function setConnectionParams($host, $user, $pass, $db) {
    // Template objects serialization
    self::$CONFERENCE = new Conference();
    self::$SCHOOL = new School();
    self::$BURGEE = new Burgee();
    self::$BOAT = new Boat();
    self::$VENUE = new Venue();
    self::$SAILOR = new Sailor();
    self::$COACH = new Coach();
    self::$SEASON = new Season();
    self::$NOW = new DateTime();

    DBM::setConnectionParams($host, $user, $pass, $db);
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
   * Returns a list of school objects which are in the specified
   * conference.
   *
   * @return a list of schools in the conference
   */
  public static function getSchoolsInConference(Conference $conf) {
    return self::getAll(self::$SCHOOL, new DBCond('conference', $conf));
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
   * @param int id the ID of the person
   * @return Sailor the sailor
   */
  public static function getSailor($id) {
    return DB::get(DB::$SAILOR, $id);
  }
  
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
  public static function getSailors(School $school, $gender = null, $active = "all") {
    $cond = new DBBool(array(new DBCond('icsa_id', null, DBCond::NE), new DBCond('school', $school)));
    if ($active === true)
      $cond->add(new DBCond('active', null, DBCond::NE));
    if ($active === false)
      $cond->add(new DBCond('active', null));
    if ($gender !== null)
      $cond->add(new DBCond('gender', $gender));
    return self::getAll(self::$SAILOR, $cond);
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
  public static function getUnregisteredSailors(School $school, $gender = null, $active = "all") {
    $cond = new DBBool(array(new DBCond('icsa_id', null, DBCond::NE), new DBCond('school', $school)));
    if ($active === true)
      $cond->add(new DBCond('active', null, DBCond::NE));
    if ($active === false)
      $cond->add(new DBCond('active', null));
    if ($gender !== null)
      $cond->add(new DBCond('gender', $gender));
    return self::getAll(self::$SAILOR, $cond);
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
  public static function getCoaches(School $school, $active = 'all', $only_registered = false) {
    $cond = new DBBool(array(new DBCond('school', $school)));
    if ($active === true)
      $cond->add(new DBCond('active', null, DBCond::NE));
    if ($active === false)
      $cond->add(new DBCond('active', null));
    if ($only_registered !== false)
      $cond->add(new DBCond('icsa_id', null, DBCond::NE));
    return self::getAll(self::$COACH, $cond);
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

  public function db_name() { return 'school'; }
  public function db_type($field) {
    if ($field == 'conference')
      return DB::$CONFERENCE;
    return parent::db_type($field);
  }
  protected function db_order() { return array('name'=>true); }
  public function __toString() { return $this->name; }
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
      throw new InvalidArgumentException("Invalid division value");
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
 * Encapsulates a sailor, whether registered with ICSA or not.
 *
 * @author Dayan Paez
 * @version 2009-10-04
 */
class Sailor extends DBObject {
  protected $school;
  public $last_name;
  public $first_name;
  public $year;
  public $role;
  public $icsa_id;
  public $gender;
  public $active;

  const MALE = 'M';
  const FEMALE = 'F';

  public function db_type($field) {
    switch ($field) {
    case 'school': return DB::$SCHOOL;
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('last_name'=>true, 'first_name'=>true); }
  public function db_name() { return 'sailor'; }
  public function db_where() { return new DBCond('role', 'student'); }

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
    if (!$this->__get('registered'))
      $name .= " *";
    return $name;
  }
}

/**
 * A coach (a sailor with role=coach)
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
class Coach extends Sailor {
  public function db_where() { return new DBCond('role', 'coach'); }
}

/**
 * Encapsulates a season
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
/*
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
  protected function db_order() { return array('start_date'=>true); }
  protected function db_cache() { return true; }
}
*/
?>