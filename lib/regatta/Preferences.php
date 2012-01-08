<?php
/**
 * Manages some of the global preferences needed by certain aspects
 * of the program. For example, the parameters from the database
 * that describe what is permissible, or not...
 *
 * @author Dayan Paez
 * @version 2009-09-29
 * @package regatta
 */

/**
 * Connects to database and provides methods for extracting available
 * parameters.
 *
 * @author Dayan Paez
 * @version 2009-10-04
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
      self::$con = new MySQLi(Conf::$SQL_HOST,
			      Conf::$SQL_USER,
			      Conf::$SQL_PASS,
			      Conf::$SQL_DB);
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
      if (Conf::$LOG_QUERIES !== null)
	@error_log(sprintf("(%7.5f) %s\n", microtime(true) - $t, $query), 3, Conf::$LOG_QUERIES);
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
    return DB::getBoat(1);    
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
      DB::mail($acc->id, $sub, $mes);

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
    $res = DB::mail(Conf::$ADMIN_MAIL, "[TechScore] Message reply", $body);
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
