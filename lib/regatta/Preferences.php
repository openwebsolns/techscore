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
   * Sends the requested query to the database, throwing an Exception
   * if something went wrong.
   *
   * @param String $query the query to send
   * @return MySQLi_Result the result set
   */
  public static function query($query) {
    $con = self::getConnection();
    $t = microtime(true);
    if ($q = $con->query($query)) {
      if (defined('LOG_QUERIES'))
	@error_log(sprintf("(%7.5f) %s\n", microtime(true) - $t, $query), 3, LOG_QUERIES);
      return $q;
    }
    throw new BadFunctionCallException(self::$con->error . ": " . $query);
  }

  /**
   * Gets an assoc. array of the possible regatta types
   *
   * @return Array a dict of regatta types
   */
  public static function getRegattaTypeAssoc() {
    return array(Preferences::TYPE_CHAMPIONSHIP=>"National Championship",
		 Preferences::TYPE_CONF_CHAMPIONSHIP=>"Conference Championship",
		 Preferences::TYPE_INTERSECTIONAL=>"Intersectional",
		 Preferences::TYPE_TWO_CONFERENCE=>"Two-Conference",
		 Preferences::TYPE_CONFERENCE=>"In-Conference",
		 Preferences::TYPE_PROMOTIONAL=>"Promotional",
		 Preferences::TYPE_PERSONAL=>"Personal");
  }
  const TYPE_PERSONAL = "personal";
  const TYPE_CONFERENCE = "conference";
  const TYPE_CHAMPIONSHIP = "championship";
  const TYPE_INTERSECTIONAL = "intersectional";
  const TYPE_CONF_CHAMPIONSHIP = "conference-championship";
  const TYPE_TWO_CONFERENCE = "two-conference";
  const TYPE_PROMOTIONAL = "promotional";

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
   * Gets an assoc. array of the possible participant values
   *
   * @return Array a dict of scoring rules
   */
  public static function getRegattaParticipantAssoc() {
    return array(Regatta::PARTICIPANT_COED => "Coed",
		 Regatta::PARTICIPANT_WOMEN => "Women");
  }

  /**
   * Returns a list of available boats
   *
   * @return Array<Boat> list of boats
   */
  public static function getBoats() {
    $q = sprintf('select %s from %s', Boat::FIELDS, Boat::TABLES);
    $q = self::query($q);
    
    $list = array();
    while ($obj = $q->fetch_object("Boat"))
      $list[] = $obj;
    return $list;
  }

  // attempt to cache boats
  private static $boats = array();
  /**
   * Fetches the boat with the given ID
   *
   * @param int $id the ID of the boat
   * @return Boat|null
   */
  public static function getBoat($id) {
    if (isset(self::$boats[$id]))
      return self::$boats[$id];
    
    $q = sprintf('select %s from %s where id = %d limit 1',
		 Boat::FIELDS, Boat::TABLES, $id);
    $q = self::query($q);
    if ($q->num_rows == 0)
      return null;
    self::$boats[$id] = $q->fetch_object("Boat");
    return self::$boats[$id];
  }

  /**
   * Sets the given boat in the database, whether that is inserting a
   * new boat (the argument will be updated with the database ID), or
   * updating an existing one.
   *
   * @param Boat $boat the boat to either add or update
   */
  public static function setBoat(Boat $boat) {
    $exist = Preferences::getBoat($boat->id);
    if ($exist === null) {
      self::query(sprintf('insert into boat (name, occupants) values ("%s", %d)',
			  $boat->name, $boat->occupants));
      $boat->id = self::$con->insert_id;
      self::$boats[$boat->id] = $boat;
    }
    else {
      self::query(sprintf('update boat set name = "%s", occupants = %d where id = %d limit 1',
			  $boat->name, $boat->occupants, $exist->id));
    }
  }

  /**
   * Adds a venue to the database
   *
   * @param Venue $venue the venue to set to the database
   */
  public static function setVenue(Venue $venue) {
    $exist = self::getVenue((int)$venue->id);
    if ($exist === null) {
      $q = sprintf('insert into venue (name, address, city, state, zipcode) ' .
		   'values ("%s", "%s", "%s", "%s", "%s")',
		   $venue->name, $venue->address, $venue->city, $venue->state, $venue->zipcode);
      self::query($q);
      $venue->id = self::$con->insert_id;
    }
    else {
      $q = sprintf('update venue set name = "%s", address = "%s", ' .
		   'city = "%s", state = "%s", zipcode = "%s" where id = %d',
		   $venue->name, $venue->address, $venue->city,
		   $venue->state, $venue->zipcode, $exist->id);
      self::query($q);
    }
  }

  /**
   * Returns the venue object with the given ID
   *
   * @param String $id the id of the object
   * @return Venue the venue object, or null
   */
  public static function getVenue($id) {
    $q = sprintf('select %s from %s where id = "%s"',
		 Venue::FIELDS, Venue::TABLES, $id);
    $q = self::query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    return $q->fetch_object("Venue");
  }

  /**
   * Get a list of registered venues.
   *
   * @see 
   * @return Array of Venue objects
   */
  public static function getVenues($start = null, $end = null) {
    $limit = "";
    if ($start === null)
      $limit = "";
    else {
      $start = (int)$start;
      if ($start < 0)
	throw new InvalidArgumentException("Start index ($start) must be greater than zero.");
    
      if ($end === null)
	$limit = "limit $start";
      elseif ((int)$end < $start)
	throw new InvalidArgumentException("End index ($end) must be greater than start ($start).");
      else {
	$range = (int)$end - $start;
	$limit = "limit $start, $range";
      }
    }
    
    $q = sprintf('select %s from %s order by name, state %s', Venue::FIELDS, Venue::TABLES, $limit);
    $q = self::query($q);
    
    $list = array();
    while ($obj = $q->fetch_object("Venue"))
      $list[] = $obj;
    return $list;
  }

  public static function getNumVenues() {
    $q = sprintf('select * from %s', Venue::TABLES);
    $q = self::query($q);
    $n = $q->num_rows;
    $q->free();
    return $n;
  }

  /**
   * Returns a list of users from the given conference
   *
   * @param Conference $conf the conference to search
   * @return Array<Account> list of users
   */
  public static function getUsersFromConference(Conference $conf) {
    $q = sprintf('select %s from %s where school in (select id from school where conference = "%s") ' .
		 'order by account.last_name',
		 Account::FIELDS, Account::TABLES, $conf->id);
    $q = self::query($q);
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
    $q = sprintf('select conference.id, conference.name ' .
		 'from conference where id = "%s"', $id);
    $q = self::query($q);
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
    $q = self::query('select conference.id, conference.name from conference');
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
    $q = sprintf('select %s from %s where conference = "%s"',
		 School::FIELDS, School::TABLES, $conf->id);
    $q = self::query($q);
    $list = array();
    while ($obj = $q->fetch_object("School")) {
      $list[] = $obj;
    }
    return $list;
  }

  // attempt to cache
  private static $schools = array();
  /**
   * Returns the school with the given ID, or null if none exists
   *
   * @return School $school with the given ID
   */
  public static function getSchool($id) {
    if (isset(self::$schools[$id])) return self::$schools[$id];
    
    $q = sprintf('select %s from %s where id like "%s"',
		 School::FIELDS, School::TABLES, $id);
    $q = self::query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    self::$schools[$id] = $q->fetch_object("School");
    return self::$schools[$id];
  }

  /**
   * Fetches the burgee, if any, for the given school. This method is
   * called internally by the School class when retrieving its burgee.
   *
   * @param School $school the school whose burgee to return
   * @return Burgee|null
   */
  public static function getBurgee(School $school) {
    $sql = sprintf('select %s from %s where school = "%s" order by last_updated desc limit 1',
		   Burgee::FIELDS, Burgee::TABLES, $school->id);
    $res = self::query($sql);
    if ($res->num_rows == 0)
      return null;
    return $res->fetch_object("Burgee");
  }

  /**
   * Updates the field for a school in the database. If the field is
   * null, then this method will update the entire school object,
   * except for the burgee itself.
   *
   * @param School $school the school to update
   * @param String $field the name of the field to update. Looks at
   * this field in the school object for the new value. If null,
   * updates the entire record
   */
  public static function updateSchool(School $school, $field = null) {
    if ($field != null && $field != "burgee")
      $q = sprintf('update school set %s = "%s" where id = "%s"',
		   $field, $school->$field, $school->id);
    elseif ($field == "burgee") {
      $q = sprintf('replace into burgee (school, filedata, last_updated, updated_by) ' .
		   'values ("%s", "%s", "%s", "%s")',
		   $school->id,
		   $school->burgee->filedata,
		   $school->burgee->last_updated->format('Y-m-d H:i:s'),
		   $_SESSION['user']);
    }
    else {
      $upd = array();
      foreach (get_class_vars("School") as $key => $val) {
	if ($key != "burgee")
	  $upd[] = sprintf('%s = "%s"', $key, $school->$key);
      }
      $q = sprintf('update school set %s where id = "%s"',
		   implode(', ', $upd), $school->id);
    }
    $q = self::query($q);
  }

  /**
   * Returns an ordered list of the team names for the given school
   *
   * @param School $school the school whose team names to fetch
   * @return Array ordered list of the school names
   */
  public static function getTeamNames(School $school) {
    $q = sprintf('select name from team_name_prefs where school = "%s" order by rank desc',
		 $school->id);
    $q = self::query($q);
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

    // 1. Remove existing names
    $q = sprintf('delete from team_name_prefs where school = "%s"', $school->id);
    self::query($q);

    // 2. Add new names
    $q = array();
    $rank = count($names);
    foreach ($names as $name) {
      $q[] = sprintf('("%s", "%s", %s)', $school->id, $name, $rank--);
    }
    self::query(sprintf('insert into team_name_prefs values %s', implode(', ', $q)));
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

    $q = sprintf('select %s from school inner join account ' .
		 'on (account.school = school.id) ' .
		 'where account.id like "%s"',
		 School::FIELDS, $user->id);
    $q = self::query($q);
    $list = array();
    while ($obj = $q->fetch_object("School"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Returns the boat that designated as the default for the school
   *
   * @param School $school the school whose default boat to fetch
   * @return Boat the boat
   */
  public static function getPreferredBoat(School $school) {
    // @TODO
    return Preferences::getBoat(1);    
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
		 Message::FIELDS, Message::TABLES, $acc->id);
    $res = self::query($q);
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
		 Message::FIELDS, Message::TABLES, $acc->id);
    $res = self::query($q);
    $list = array();
    while ($obj = $res->fetch_object("Message", array($acc)))
      $list[] = $obj;
    return $list;
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
    $con = self::getConnection();
    $q = sprintf('insert into message (account, subject, content) values ("%s", "%s", "%s")',
		 $acc->id,
		 $con->real_escape_string($sub),
		 $con->real_escape_string($mes));
    self::query($q);

    if ($email !== false)
      Preferences::mail($acc->id, $sub, $mes);

    // fetch the last message
    $id = self::query('select last_insert_id() as id');
    $id = $id->fetch_object()->id;
    $q = sprintf('select %s from %s where id = "%s"',
		 Message::FIELDS, Message::TABLES, $id);
    $re = self::query($q);
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
    self::query($q);
    $mes->read_time = $time;
  }

  /**
   * Deletes the message (actually, marks it as "inactive")
   *
   * @param Message $mes the message to "delete"
   */
  public static function deleteMessage(Message $mes) {
    $q = sprintf('update message set active = 0 where id = "%s"', $mes->id);
    self::query($q);
  }

  /**
   * Sends mail to the authorities replying to the user
   *
   * @param Message $mes the message being replied
   * @param String $reply the reply
   */
  public static function reply(Message $mes, $reply) {
    $body = sprintf("Reply from: %s\n---------------------\n%s\n-------------------\n%s",
		    $mes->account->id,
		    $mes->content,
		    $reply);
    $res = self::mail(ADMIN_MAIL, "[TechScore] Message reply", $body);
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
    if (defined('DIVERT_MAIL')) {
      $body = "Message meant for $to\n\n" . $body;
      $to = DIVERT_MAIL;
      $subject = 'DIVERTED: ' . $subject;
    }
    return mail($to,
		$subject,
		wordwrap($body, 72),
		sprintf('From: %s', TS_FROM_MAIL));
  }

  /**
   * Queues an outgoing message to be processed by CLI script
   *
   * @param Outbox $out the message parameters to send
   */
  public static function queueOutgoing(Outbox $out) {
    $con = self::getConnection();
    self::query(sprintf('insert into outbox (sender, recipients, arguments, copy_sender, subject, content) values ("%s", "%s", "%s", "%s", "%s", "%s")',
			$out->sender,
			$out->recipients,
			$out->arguments,
			$out->copy_sender,
			$con->real_escape_string($out->subject),
			$con->real_escape_string($out->content)));
  }

  /**
   * Marks the given Outbox as completed (updates completion_time with
   * current time)
   *
   * @param Outbox the outgoing message to update
   */
  public static function unpendOutgoing(Outbox $out) {
    $con = self::getConnection();
    self::query(sprintf('update outbox set completion_time = "%s" where id = "%s"', date('Y-m-d H:i:s'), $out->id));
  }

  /**
   * Get all non-completed outgoing messages
   *
   * @return Array:Outbox the messages
   */
  public static function getPendingOutgoing() {
    $q = self::query(sprintf('select * from %s where completion_time is null', Outbox::TABLES));
    $l = array();
    while ($obj = $q->fetch_object("Outbox"))
      $l[] = $obj;
    return $l;
  }

  /**
   * Returns a list of the years for which there are regattas in the
   * database
   *
   * @return Array:int the list of years, indexed by the years
   */
  public static function getYears() {
    $q = sprintf('select distinct year(start_time) as year from regatta order by year desc');
    $r = self::query($q);
    $l = array();
    while ($i = $r->fetch_object())
      $l[$i->year] = $i->year;
    return $l;
  }

  /**
   * Returns a list of the seasons for which there are public regattas
   *
   * @return Array:Season the list
   */
  public static function getActiveSeasons() {
    $r = self::query('select distinct season from dt_regatta order by start_time desc');
    $l = array();
    while ($i = $r->fetch_object())
      $l[] = Season::parse($i->season);
    return $l;
  }
}
?>
