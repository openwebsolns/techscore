<?php
/**
 * Manages some of the global preferences needed by certain aspects
 * of the program. For example, the parameters from the database
 * that describe what is permissible, or not...
 *
 * @author Dayan Paez
 * @created 2009-09-29
 * @package regatta
 */
require_once('conf.php');

/**
 * Connects to database and provides methods for extracting available
 * parameters.
 *
 * @author Dayan Paez
 * @created 2009-10-04
 */
class Preferences {

  private static $con;

  /**
   * Returns the one connection to the database that should be used.
   *
   * @return MySQLi the connection object
   */
  public static function getConnection() {
    if (self::$con == null) {
      self::$con = new MySQLi(SQL_HOST,
			      SQL_USER,
			      SQL_PASS,
			      SQL_DB);
    }
    return self::$con;
  }

  /**
   * Gets an assoc. array of the possible regatta types
   *
   * @return Array a dict of regatta types
   */
  public static function getRegattaTypeAssoc() {
    return array("personal"=>"Personal",
		 "conference"=>"Conference",
		 "intersectional"=>"Intersectional",
		 "championship"=>"Championship");
  }

  /**
   * Gets an assoc. array of the possible scoring rules
   *
   * @return Array a dict of scoring rules
   */
  public static function getRegattaScoringAssoc() {
    return array(Regatta::SCORING_STANDARD => "Standard",
		 Regatta::SCORING_COMBINED => "Combined divisions");
  }

  /**
   * Returns a list of available boats
   *
   * @return Array<Boat> list of boats
   */
  public static function getBoats() {
    $con = self::getConnection();
    $q = sprintf('select %s from %s', Boat::FIELDS, Boat::TABLES);
    $q = $con->query($q);
    
    $list = array();
    while ($obj = $q->fetch_object("Boat"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Returns the venue object with the given ID
   *
   * @param String $id the id of the object
   * @return Venue the venue object, or null
   */
  public static function getVenue($id) {
    $con = self::getConnection();
    $q = sprintf('select %s from %s where id = "%s"',
		 Venue::FIELDS, Venue::TABLES, $id);
    $q = $con->query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    return $q->fetch_object("Venue");
  }

  /**
   * Get a list of registered venues
   *
   * @return Array of Venue objects
   */
  public static function getVenues() {
    $con = self::getConnection();
    $q = sprintf('select %s from %s', Venue::FIELDS, Venue::TABLES);
    $q = $con->query($q);
    
    $list = array();
    while ($obj = $q->fetch_object("Venue"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Returns a list of users from the given conference
   *
   * @param Conference $conf the conference to search
   * @return Array<Account> list of users
   */
  public static function getUsersFromConference(Conference $conf) {
    $con = self::getConnection();
    $q = sprintf('select %s from %s where school.conference = "%s" order by account.last_name',
		 Account::FIELDS, Account::TABLES, $conf->id);
    $q = $con->query($q);
    $list = array();
    while ($obj = $q->fetch_object("Account"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Returns the conference with the given ID
   *
   * @param String $id the id of the conference
   * @return Conference the conference object
   */
  public static function getConference($id) {
    $con = self::getConnection();
    $q = sprintf('select conference.id, conference.name, conference.nick ' .
		 'from conference where id = "%s"', $id);
    $q = $con->query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    return $q->fetch_object("Conference");
  }

  /**
   * Returns a list of conference objects, with properties id, name,
   * and nick
   *
   * @return a list of conferences
   */
  public static function getConferences() {
    $con = self::getConnection();
    $q = $con->query('select conference.id, conference.name, ' .
		     'conference.nick from conference');
    $list = array();
    while ($conf = $q->fetch_object("Conference"))
      $list[] = $conf;
    return $list;
  }

  /**
   * Returns a list of school objects which are in the specified
   * conference.
   *
   * @return a list of schools in the conference
   */
  public static function getSchoolsInConference(Conference $conf) {
    $con = self::getConnection();
    $q = sprintf('select %s from school where conference = "%s"',
		 School::FIELDS, $conf->id);
    $q = $con->query($q);
    $list = array();
    while ($obj = $q->fetch_object("School")) {
      $list[] = $obj;
    }
    return $list;
  }

  /**
   * Returns the school with the given ID, or null if none exists
   *
   * @return School $school with the given ID
   */
  public static function getSchool($id) {
    $con = self::getConnection();
    $q = sprintf('select %s from school where id like "%s"',
		 School::FIELDS, $id);
    $q = $con->query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    $s = $q->fetch_object("School");
    $s->conference = self::getConference($s->conference);
    return $s;
  }

  /**
   * Updates the field for a school in the database
   *
   * @param School $school the school to update
   * @param String $field the name of the field to update. Looks at
   * this field in the school object for the new value. If null,
   * updates the entire record
   */
  public static function updateSchool(School $school, $field = null) {
    $con = self::getConnection();
    if ($field != null)
      $q = sprintf('update school set %s = "%s" where id = "%s"',
		   $field, $school->$field, $school->id);
    else {
      $upd = array();
      foreach (get_class_vars("School") as $key => $val) {
	$upd[] = sprintf('%s = "%s"', $key, $school->$key);
      }
      $q = sprintf('update school set %s where id = "%s"',
		   implode(', ', $upd), $school->id);
    }
    $q = $con->query($q);
    return (empty($con->error));
  }

  /**
   * Returns an ordered list of the team names for the given school
   *
   * @param School $school the school whose team names to fetch
   * @return Array ordered list of the school names
   */
  public static function getTeamNames(School $school) {
    $con = self::getConnection();
    $q = sprintf('select name from team_name_prefs where school = "%s" order by rank desc',
		 $school->id);
    $q = $con->query($q);
    $list = array();
    while ($obj = $q->fetch_object())
      $list[] = $obj->name;
    return $list;
  }

  /**
   * Sets the team names for the given school
   *
   * @param School $school school whose valid team names to set
   * @param Array $names an ordered list of team names
   */
  public static function setTeamNames(School $school, Array $names) {
    $con = self::getConnection();

    // 1. Remove existing names
    $q = sprintf('delete from team_name_prefs where school = "%s"', $school->id);
    $con->query($q);

    // 2. Add new names
    $q = array();
    $rank = count($names);
    foreach ($names as $name) {
      $q[] = sprintf('("%s", "%s", %s)', $school->id, $name, $rank--);
    }
    $con->query(sprintf('insert into team_name_prefs values %s',
			implode(', ', $q)));
    return (empty($con->error));
  }

  /**
   * Traverses a list and returns the first object with the specified
   * property value for the specified property name, or null otherwise
   *
   * @param Array $array the array of objects
   * @param string $prop_name the property name to check
   * @param mixed  $prop_value the value of the property to check
   * @return the object, or null if not found
   */
  public static function getObjectWithProperty(Array $array,
					       $prop_name,
					       $prop_value) {
    foreach ($array as $obj) {
      if ($obj->$prop_name == $prop_value) {
	return $obj;
      }
    }
    return null;
  }

  /**
   * Returns a list of schools for which the user has jurisdiction
   *
   * @param User $user the user whose jurisdiction to fetch
   * @return Array $schools list of School objects
   */
  public static function getSchoolsForUser(User $user) {
    // 2009-10-14: Return the school from this user
    return array($user->school);

    $con = self::getConnection();
    $q = sprintf('select %s from school inner join account ' .
		 'on (account.school = school.id) ' .
		 'where account.username like "%s"',
		 School::FIELDS, $user->username);
    $q = $con->query($q);
    $list = array();
    while ($obj = $q->fetch_object("School"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Returns user with the given username
   *
   * @return User with the given username, null otherwise
   */
  public static function getUser($id) {
    $con = self::getConnection();
    $q = sprintf('select %s from %s where username like "%s"',
		 User::FIELDS, User::TABLES, $id);
    $q = $con->query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    return $q->fetch_object("User");
  }

  /**
   * Returns the account with the given username
   *
   * @return Account the account with the given username, null if none
   * exist
   */
  public static function getAccount($id) {
    $con = self::getConnection();
    $q = sprintf('select %s from %s where username like "%s"',
		 Account::FIELDS, Account::TABLES, $id);
    $q = $con->query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    return $q->fetch_object("Account");
  }

  /**
   * Returns the user with the specified id if the password matches,
   * or null otherwise
   *
   * @param string $id the user id
   * @param string $pass the password in the system
   *
   * @return User the user object
   * @return null if invalid userid or password
   */
  public static function approveUser($id, $pass) {
    $con = self::getConnection();
    $q = sprintf('select * from account where username like "%s" and password = sha("%s")',
		 $id, $pass);
    $q = $con->query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    return new User($id);
  }

  /**
   * Registers the new sailor into the temporary database. Returns a
   * new Sailor object with the appropriate ID.
   *
   * @param Sailor $sailor the new sailor to register
   * @return Sailor the new unregistered sailor, backed by the database
   */
  public static function addTempSailor(Sailor $sailor) {
    $con = self::getConnection();
    $q = sprintf('insert into sailor ' .
		 '(school, first_name, last_name, year, registered) values ' .
		 '("%s", "%s", "%s", "%s", "0")',
		 $sailor->school->id,
		 $sailor->first_name,
		 $sailor->last_name,
		 $sailor->year);
    $con->query($q);

    // fetch the last ID
    $res = $con->query('select last_insert_id() as id');
    $id  = $res->fetch_object()->id;

    $res = $con->query(sprintf('select %s from %s where id = "%s"',
			       Sailor::FIELDS, Sailor::TABLES, $id));
    return $res->fetch_object("Sailor");
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
    $q = sprintf('select %s from %s where account = "%s" and active = 1 order by created desc',
		 Message::FIELDS, Message::TABLES, $acc->username);
    $con = self::getConnection();
    $res = $con->query($q);
    $list = array();
    while ($obj = $res->fetch_object("Message", array($acc)))
      $list[] = $obj;
    return $list;
  }

  /**
   * Retrieve all messages for the given account in order
   *
   * @param Account $acc the account
   */
  public static function getUnreadMessages(Account $acc) {
    $q = sprintf('select %s from %s where account = "%s" ' .
		 'and read_time is null and active = 1 order by created',
		 Message::FIELDS, Message::TABLES, $acc->username);
    $con = self::getConnection();
    $res = $con->query($q);
    $list = array();
    while ($obj = $res->fetch_object("Message", array($acc)))
      $list[] = $obj;
    return $list;
  }

  /**
   * Adds the given message for the given user
   *
   * @param Account the user
   * @param String $mes the message
   * @return Message the queued message
   */
  public static function queueMessage(Account $acc, $mes) {
    $q = sprintf('insert into message (account, content) values ("%s", "%s")',
		 $acc->username, (string)$mes);
    $con = self::getConnection();
    $con->query($q);

    // fetch the last message
    $id = $con->query('select last_insert_id() as id');
    $id = $id->fetch_object()->id;
    $q = sprintf('select %s from %s where id = "%s"',
		 Message::FIELDS, Message::TABLES, $id);
    $re = $con->query($q);
    return $re->fetch_object("Message", array($acc));
  }

  /**
   * Marks the given message as read using the current timestamp or
   * the one provided. Updates the Message object
   *
   * @param Message $mes
   * @param DateTime $time
   */
  public static function markRead(Message $mes, DateTime $time = null) {
    if ($time === null)
      $time = new DateTime("now");

    $q = sprintf('update message set read_time = "%s" where id = "%s"',
		 $time->format('Y-m-d H:i:s'), $mes->id);
    $con = self::getConnection();
    $con->query($q);
    $mes->read_time = $time;
  }

  /**
   * Deletes the message (actually, marks it as "inactive")
   *
   * @param Message $mes the message to "delete"
   */
  public static function deleteMessage(Message $mes) {
    $q = sprintf('update message set active = 0 where id = "%s"', $mes->id);
    $con = self::getConnection();
    $con->query($q);
  }

  /**
   * Sends mail to the authorities replying to the user
   *
   * @param Message $mes the message being replied
   * @param String $reply the reply
   */
  public static function reply(Message $mes, $reply) {
    $body = sprintf("Reply from: %s\n---------------------\n%s\n-------------------\n%s",
		    $mes->account->username,
		    $mes->content,
		    $reply);
    $res = mail(ADMIN_MAIL, "[TechScore] Message reply", $body, "From: no-reply@techscore.mit.edu");
  }
}

// Main
if (basename($argv[0]) == basename(__FILE__)) {
  $acc = Preferences::getAccount("paez@mit.edu");
  // Preferences::queueMessage($acc, "Hello World");

  $mes = Preferences::getUnreadMessages($acc);
  Preferences::markRead($mes[0]);
  print_r(Preferences::getUnreadMessages($acc));
}
?>