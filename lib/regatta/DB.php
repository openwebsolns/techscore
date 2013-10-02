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
  public static $TYPE = null;
  public static $ACTIVE_TYPE = null;
  public static $CONFERENCE = null;
  public static $SCHOOL = null;
  public static $ACTIVE_SCHOOL = null;
  public static $BURGEE = null;
  public static $BOAT = null;
  public static $VENUE = null;
  public static $MEMBER = null;
  public static $SAILOR = null;
  public static $COACH = null;
  public static $SCORER = null;
  public static $TEAM = null;
  public static $RANKED_TEAM = null;
  public static $SINGLEHANDED_TEAM = null;
  public static $RANKED_SINGLEHANDED_TEAM = null;
  public static $TEAM_NAME_PREFS = null;
  public static $SAIL = null;
  public static $NOTE = null;
  public static $ROUND = null;
  public static $ROUND_GROUP = null;
  public static $RACE = null;
  public static $RACE_ROUND = null;
  public static $FINISH = null;
  public static $FINISH_MODIFIER = null;
  public static $TEAM_PENALTY = null;
  public static $HOST_SCHOOL = null;
  public static $DAILY_SUMMARY = null;
  public static $REPRESENTATIVE = null;
  public static $RP_ENTRY = null;
  public static $SEASON = null;
  public static $NOW = null;
  public static $DT_TEAM_DIVISION = null;
  public static $DT_RP = null;
  public static $TEXT_ENTRY = null;
  public static $RACE_ORDER = null;
  public static $REGATTA_ROTATION = null;
  public static $ROUND_SLAVE = null;
  public static $AA_REPORT = null;

  public static $PERMISSION = null;
  public static $ROLE = null;
  public static $ROLE_PERMISSION = null;
  public static $SETTING = null;

  public static $OUTBOX = null;
  public static $MESSAGE = null;
  public static $ACCOUNT = null;
  public static $ACCOUNT_SCHOOL = null;
  public static $FULL_REGATTA = null;
  public static $REGATTA = null;
  public static $PUBLIC_REGATTA = null;
  public static $RP_LOG = null; // RpManager.php
  public static $RP_FORM = null; // RpManager.php
  public static $UPDATE_REQUEST = null; // UpdateRequest.php
  public static $UPDATE_SCHOOL = null; // UpdateRequest.php
  public static $UPDATE_SEASON = null; // UpdateRequest.php

  // The validation engine
  public static $V = null;

  public static function setConnectionParams($host, $user, $pass, $db) {
    // Template objects serialization
    self::$TYPE = new Type();
    self::$ACTIVE_TYPE = new Active_Type();
    self::$CONFERENCE = new Conference();
    self::$SCHOOL = new School();
    self::$ACTIVE_SCHOOL = new Active_School();
    self::$BURGEE = new Burgee();
    self::$BOAT = new Boat();
    self::$VENUE = new Venue();
    self::$MEMBER = new Member();
    self::$SAILOR = new Sailor();
    self::$COACH = new Coach();
    self::$SCORER = new Scorer();
    self::$TEAM = new Team();
    self::$RANKED_TEAM = new RankedTeam();
    self::$SINGLEHANDED_TEAM = new SinglehandedTeam();
    self::$RANKED_SINGLEHANDED_TEAM = new RankedSinglehandedTeam();
    self::$TEAM_NAME_PREFS = new Team_Name_Prefs();
    self::$SAIL = new Sail();
    self::$NOTE = new Note();
    self::$ROUND = new Round();
    self::$ROUND_GROUP = new Round_Group();
    self::$RACE = new Race();
    self::$RACE_ROUND = new Race_Round();
    self::$FINISH = new Finish();
    self::$FINISH_MODIFIER = new FinishModifier();
    self::$TEAM_PENALTY = new TeamPenalty();
    self::$HOST_SCHOOL = new Host_School();
    self::$DAILY_SUMMARY = new Daily_Summary();
    self::$REPRESENTATIVE = new Representative();
    self::$RP_ENTRY = new RPEntry();
    self::$SEASON = new Season();
    self::$TEXT_ENTRY = new Text_Entry();
    self::$RACE_ORDER = new Race_Order();
    self::$REGATTA_ROTATION = new Regatta_Rotation();
    self::$ROUND_SLAVE = new Round_Slave();
    self::$AA_REPORT = new AA_Report();

    self::$PERMISSION = new Permission();
    self::$ROLE = new Role();
    self::$ROLE_PERMISSION = new Role_Permission();
    self::$SETTING = new STN();

    self::$DT_TEAM_DIVISION = new Dt_Team_Division();
    self::$DT_RP = new Dt_Rp();
    self::$NOW = new DateTime();

    DBM::setConnectionParams($host, $user, $pass, $db);

    require_once('regatta/TSSoter.php');
    self::$V = new TSSoter();
    self::$V->setDBM('DB');
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
   * Sets the inactive flag on all the schools in the DB.
   *
   */
  public static function inactivateSchools() {
    $q = self::createQuery(DBQuery::UPDATE);
    $q->values(array(new DBField('inactive')),
               array(DBQuery::A_STR),
               array(DB::$NOW->format('Y-m-d H:i:s')),
               DB::$SCHOOL->db_name());
    $q->where(new DBCond('inactive', null));
    self::query($q);
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
   * @param boolean $wrap whether to wrap message (default = true)
   * @return boolean the result, as returned by mail
   */
  public static function mail($to, $subject, $body, $wrap = true) {
    if (Conf::$DIVERT_MAIL !== null) {
      $body = "Message meant for $to\n\n" . $body;
      $to = Conf::$DIVERT_MAIL;
      $subject = 'DIVERTED: ' . $subject;
    }
    if ($wrap)
      $body = wordwrap($body, 72);

    if (!is_array($to))
      $to = array($to);

    $res = true;
    foreach ($to as $recipient)
      $res = $res && @mail($to,
                          $subject,
                          $body,
                          sprintf("From: %s\r\nContent-Type: text/plain; charset=utf8", Conf::$TS_FROM_MAIL));
    return $res;
  }

  /**
   * Sends a multipart (MIME) mail message to the given user with the
   * given subject, appending the correct headers (i.e., the "from"
   * field). This method uses the standard PHP mail function
   *
   * @param String|Array $to the e-mail address(es) to send to
   * @param String $subject the subject
   * @param Array $parts the different MIME parts, indexed by MIME type.
   * @return boolean the result, as returned by mail
   */
  public static function multipartMail($to, $subject, Array $parts) {
    if (Conf::$DIVERT_MAIL !== null) {
      $to = Conf::$DIVERT_MAIL;
      $subject = 'DIVERTED: ' . $subject;
    }

    $segments = array();
    foreach ($parts as $mime => $part) {
      $segment = sprintf("Content-Type: %s\n", $mime);
      if (substr($mime, 0, strlen('text/plain')) != 'text/plain') {
        $segment .= "Content-Transfer-Encoding: base64\n";
        $part = base64_encode($part);
      }
      $segment .= "\n";
      $segment .= $part;
      $segments[] = $segment;
    }

    $found = true;
    while ($found) {
      $bdry = uniqid(rand(100, 999), true);
      $found = false;
      foreach ($segments as $segment) {
        if (strstr($segment, $bdry) !== false) {
          $found = true;
          break;
        }
      }
    }

    $headers = sprintf("From: %s\nMIME-Version: 1.0\nContent-Type: multipart/alternative; boundary=%s\n", Conf::$TS_FROM_MAIL, $bdry);
    $body = "This is a message with multiple parts in MIME format.\n";
    foreach ($segments as $segment)
      $body .= sprintf("--%s\n%s\n", $bdry, $segment);
    $body .= sprintf("--%s--", $bdry);

    if (!is_array($to))
      $to = array($to);
    $res = true;
    foreach ($to as $recipient)
      $res = $res && @mail($recipient, $subject, $body, $headers);
    return $res;
  }

  /**
   * Get all non-completed outgoing messages
   *
   * @return Array:Outbox the messages
   */
  public static function getPendingOutgoing() {
    require_once('regatta/Outbox.php');
    return self::getAll(self::$OUTBOX, new DBCond('completion_time', null));
  }

  // ------------------------------------------------------------
  // Messages
  // ------------------------------------------------------------

  /**
   * Retrieves the message with the given ID. Note that the Message
   * class is not auto-loaded. Using this method ascertains that the
   * class is loaded, and that DB::$MESSAGE is not null.
   *
   * @param String $id the id of the message to retrieve
   * @return Message|null the message, if any
   */
  public static function getMessage($id) {
    require_once('regatta/Message.php');
    return self::get(self::$MESSAGE, $id);
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
  public static function queueMessage(Account $acc, $sub, $con, $email = false) {
    require_once('regatta/Message.php');
    $mes = new Message();
    $mes->account = $acc;
    $mes->subject = $sub;
    $mes->content = $con;
    self::set($mes, false);

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
    $mes->inactive = 1;
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
    $res = self::mail(Conf::$ADMIN_MAIL, sprintf("[%s] Message reply", Conf::$NAME), $body);
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
    $r = DB::getAll(DB::$MEMBER, new DBCond('icsa_id', $id));
    $s = (count($r) == 0) ? null : $r[0];
    unset($r);
    return $s;
  }

  /**
   * Searches for the sailor's first, last, or full name
   *
   * @param String $str the string to search
   * @param mixed $registered true|false to filter, or anything else
   * to ignore registration status
   */
  public static function searchSailors($str, $registered = 'all') {
    $q = self::prepSearch(self::$SAILOR, $str, array('first_name', 'last_name', 'concat(first_name, " ", last_name)'));
    if ($registered === true)
      $q->where(new DBCond('icsa_id', null, DBCond::NE));
    elseif ($registered === false)
      $q->where(new DBCond('icsa_id', null));
    return new DBDelegate(self::query($q), new DBObject_Delegate(get_class(DB::$SAILOR)));
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
   * Create a new hash for the given user using the plain-text password.
   *
   * @param Account $user the user
   * @param String $passwd the plain text password
   */
  public static function createPasswordHash(Account $user, $passwd) {
    return hash('sha512', $user->id . "\0" . sha1($passwd) . "\0" . Conf::$PASSWORD_SALT);
  }

  /**
   * Returns all the pending users, using the given optional indices
   * to limit the list, like the range function in Python.
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
   * @param String|null $role a possible Account role
   * @param String|null $status a possible Account status
   * @return Array:Account the list of accounts
   * @throws InvalidArgumentException if provided role is invalid
   */
  public static function getAccounts($role = null, $status = null) {
    require_once('regatta/Account.php');
    $cond = null;
    if ($role !== null) {
      $roles = Account::getRoles();
      if (!isset($roles[$role]))
        throw new InvalidArgumentException("Invalid role provided: $role.");
      $cond = new DBCond('role', $role);
    }
    if ($status !== null) {
      $statuses = Account::getStatuses();
      if (!isset($statuses[$status]))
        throw new InvalidArgumentException("Invalid status provided: $status.");
      if ($cond === null)
        $cond = new DBCond('status', $status);
      else
        $cond = new DBBool(array($cond, new DBCond('status', $status)));
    }
    return self::getAll(self::$ACCOUNT, $cond);
  }

  /**
   * Search accounts, with optional role and/or status filter
   *
   * @param String|null $role a possible Account role
   * @param String|null $status a possible Account status
   * @return Array:Account the list of accounts
   * @throws InvalidArgumentException if provided role is invalid
   */
  public static function searchAccounts($qry, $role = null, $status = null) {
    require_once('regatta/Account.php');
    if ($role === null && $status === null)
      return self::search(DB::$ACCOUNT, $qry);

    $cond = new DBBool(array());
    if ($role !== null) {
      $roles = Account::getRoles();
      if (!isset($roles[$role]))
        throw new InvalidArgumentException("Invalid role provided: $role.");
      $cond->add(new DBCond('role', $role));
    }
    if ($status !== null) {
      $statuses = Account::getStatuses();
      if (!isset($statuses[$status]))
        throw new InvalidArgumentException("Invalid status provided: $status.");
      $cond->add(new DBCond('status', $status));
    }

    $q = self::prepSearch(DB::$ACCOUNT, $qry);
    $q->where($cond);
    $r = self::query($q);
    return new DBDelegate($r, new DBObject_Delegate(get_class(DB::$ACCOUNT)));
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
    case Account::STAT_ACTIVE:
      return;

    case Account::STAT_ACCEPTED:
      WS::go('/license');

    default:
    case Account::STAT_INACTIVE:
      WS::go('/logout');
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
   * Creates array of range from string.
   *
   * Expects argument to contain only spaces, commas, dashes and
   * numbers, greater than 0
   *
   * @param String $str the range to parse
   * @return Array the numbers in the string in numerical order
   */
  public static function parseRange($str) {
    // Check for valid characters
    if (preg_match('/[^0-9 ,-]/', $str) == 1)
      return array();

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

    if (strlen($str) == 0)
      return array();

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
    // Must be unique and sorted
    sort($list, SORT_NUMERIC);
    $list = array_unique($list);
    if (count($list) == 0)
      return "";

    $range_start = null;
    $last = null;
    $range = "";
    foreach ($list as $next) {
      if ($last === null) {
        $range .= $next;
        $range_start = $next;
      }
      elseif ($next != $last + 1) {
        if ($range_start != $last)
          $range .= "-$last";
        $range .= ",$next";
        $range_start = $next;
      }
      $last = $next;
    }
    if ($range_start != $last)
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

  /**
   * Fetches all the race order templates for given parameters
   *
   * @param int $num_teams how many teams in the template
   * @param int $num_divisions how many divisions
   * @return Array:Race_Order
   */
  public static function getRaceOrders($num_teams, $num_divisions) {
    return DB::getAll(DB::$RACE_ORDER,
                      new DBBool(array(new DBCond('num_teams', $num_teams),
                                       new DBCond('num_divisions', $num_divisions))));
  }

  // ------------------------------------------------------------
  // Settings
  // ------------------------------------------------------------

  public static function g($key) {
    $attrs = self::getSettingNames();
    if (!in_array($key, $attrs))
      throw new InvalidArgumentException("Invalid setting $key.");
    if (!array_key_exists($key, self::$settings)) {
      self::$settings[$key] = null;
      $res = DB::get(DB::$SETTING, $key);
      if ($res !== null && $res->value !== null)
        self::$settings[$key] = $res->value;
    }
    return self::$settings[$key];
  }

  public static function s($key, $value) {
    $attrs = self::getSettingNames();
    if (!in_array($key, $attrs))
      throw new InvalidArgumentException("Invalid setting $key.");
    if ($value !== null)
      $value = (string)$value;

    $res = DB::get(DB::$SETTING, $key);
    $upd = true;
    if ($res === null) {
      $res = new STN();
      $res->id = $key;
      $upd = false;
    }
    $res->value = $value;
    DB::set($res, $upd);
    self::$settings[$key] = $value;
  }

  public static function getSettingNames() {
    if (self::$setting_names === null) {
      $r = new ReflectionClass(DB::$SETTING);
      self::$setting_names = $r->getConstants();
    }
    return self::$setting_names;
  }
  private static $settings = array();
  private static $setting_names;

  // ------------------------------------------------------------
  // Tweet
  // ------------------------------------------------------------

  private static $twitterer = null;

  /**
   * Wrapper around TwitterWriter
   *
   * @param String $mes
   */
  public static function tweet($mes) {
    if (self::$twitterer === null) {
      require_once('twitter/TwitterWriter.php');
      self::$twitterer = new TwitterWriter(DB::g(STN::TWITTER_CONSUMER_KEY),
                                           DB::g(STN::TWITTER_CONSUMER_SECRET),
                                           DB::g(STN::TWITTER_OAUTH_TOKEN),
                                           DB::g(STN::TWITTER_OAUTH_SECRET));
    }
    self::$twitterer->tweet($mes);
  }
}

/**
 * Regatta type, which may be ranked
 *
 * @author Dayan Paez
 * @version 2012-11-05
 */
class Type extends DBObject {
  public $title;
  public $description;
  /**
   * @var int the display rank (lower = more important)
   */
  public $rank;
  public $inactive;
  protected $mail_lists;

  public function db_name() { return 'type'; }
  public function db_type($field) {
    if ($field == 'mail_lists')
      return array();
    return parent::db_type($field);
  }
  protected function db_order() { return array('rank'=>true, 'title'=>true); }
  protected function db_cache() { return true; }
  public function __toString() { return $this->title; }
}

/**
 * Active type: different per installation
 *
 * @author Dayan Paez
 * @version 2012-11-05
 */
class Active_Type extends Type {
  public function db_where() {
    return new DBCond('inactive', null);
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
  protected $inactive;

  public function db_name() { return 'school'; }
  public function db_type($field) {
    switch ($field) {
    case 'conference': return DB::$CONFERENCE;
    case 'burgee': return DB::$BURGEE;
    case 'inactive': return DB::$NOW;
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
   * @param mixed $active default "all", true returns ONLY the active ones,
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
    $cond = new DBBool(array(new DBCond('icsa_id', null), new DBCond('school', $this)));
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
    elseif ($active === false)
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
    $curr = DB::getAll(DB::$TEAM_NAME_PREFS, new DBCond('school', $this));
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

  /**
   * Fetches list of regattas this school has a team in
   *
   * @param boolean $inc_private true to include private regattas
   * @return Array:Regatta the regatta list
   */
  public function getRegattas($inc_private = false) {
    require_once('regatta/Regatta.php');
    return DB::getAll(($inc_private !== false) ? DB::$REGATTA : DB::$PUBLIC_REGATTA,
                      new DBCondIn('id', DB::prepGetAll(DB::$TEAM,
                                                        new DBCond('school', $this),
                                                        array('regatta'))));
  }

  /**
   * Creates and returns a nick name for the school, which is of
   * appropriate length (no greater than 20 chars)
   *
   * @param String $str the name, usually
   * @return String the display name
   */
  public static function createNick($str) {
    $str = trim($str);
    $str = str_replace('University of', 'U', $str);
    $str = str_replace(' University', '', $str);
    if (mb_strlen($str) > 20)
      $str = mb_substr($str, 0, 20);
    return $str;
  }
}

/**
 * Active schools are useful when creating a new regatta (or one for
 * the current season), so that users are only choosing from active
 * schools for regatta participation.
 *
 * @author Dayan Paez
 * @version 2012-04-01
 */
class Active_School extends School {
  public function db_where() { return new DBCond('inactive', null); }
}

/**
 * A boat class, like Techs and FJs.
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
class Boat extends DBObject {
  public $name;
  public $min_crews;
  public $max_crews;

  protected function db_cache() { return true; }
  protected function db_order() { return array('name'=>true); }
  public function __toString() { return $this->name; }

  public function getRaces() {
    return DB::getAll(DB::$RACE, new DBCond('boat', $this));
  }

  public function getNumCrews() {
    $num = (int)$this->min_crews;
    if ($this->max_crews != $this->min_crews)
      $num .= '-' . (int)$this->max_crews;
    return $num;
  }
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

  /**
   * Returns the value for this division as a relative level, one of
   * "High", "Mid", or "Low".
   *
   * @param int $num the number of divisions participating
   * @return String the level
   */
  public function getLevel($num = 3) {
    if ($this->value == Division::A())
      return "High";
    if ($this->value == Division::D())
      return "Lowest";
    if ($this->value == Division::C())
      return "Low";
    // B division
    if ($num == 2)
      return "Low";
    return "Mid";
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
  public function getName() {
    $name = "";
    if ($this->first_name !== null)
      $name = $this->first_name;
    if ($this->last_name !== null) {
      if ($name != "")
        $name .= " ";
      $name .= $this->last_name;
    }
    if ($name == "")
      return "[No Name]";
    return $name;
  }
  public function __toString() {
    $year = "";
    if ($this->role == 'student')
      $year = " '" . (($this->year > 0) ? substr($this->year, -2) : "??");
    $name = $this->getName() . $year;
    if (!$this->isRegistered())
      $name .= " *";
    return $name;
  }

  /**
   * Fetch list of regattas member has participated in
   *
   */
  public function getRegattas($inc_private = false) {
    $cond = new DBCondIn('id', DB::prepGetAll(DB::$RP_ENTRY, new DBCond('sailor', $this), array('race')));
    require_once('regatta/Regatta.php');
    return DB::getAll(($inc_private !== false) ? DB::$REGATTA : DB::$PUBLIC_REGATTA,
                      new DBCondIn('id', DB::prepGetAll(DB::$RACE, $cond, array('regatta'))));
  }

  /**
   * Compares two members based on last name, then first name
   *
   */
  public static function compare(Member $m1, Member $m2) {
    if ($m1->last_name != $m2->last_name)
      return strcmp($m1->last_name, $m2->last_name);
    return strcmp($m1->first_name, $m2->first_name);
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

  public $rank_group;
  public $lock_rank;

  public $dt_rank;
  public $dt_explanation;
  public $dt_score;
  public $dt_wins;
  public $dt_losses;
  public $dt_ties;

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
  public function &getQualifiedName() {
    return $this->name;
  }
  public function __toString() {
    return $this->__get('school')->nick_name . ' ' . $this->getQualifiedName();
  }

  /**
   * Gets the team's "team racing win percentage"
   */
  public function getWinPercentage() {
    $total = $this->dt_wins + $this->dt_losses + $this->dt_ties;
    if ($total == 0)
      return 0;
    return $this->dt_wins / $total;
  }

  /**
   * Display this team's "team racing record": wins-losses
   */
  public function getRecord() {
    $txt = sprintf('%d-%d', $this->dt_wins, $this->dt_losses);
    if ($this->dt_ties > 0)
      $txt .= sprintf('-%d', $this->dt_ties);
    return $txt;
  }

  /**
   * Returns this team's rank within the given division, if one exists
   *
   * @param String $division the possible division
   * @return Dt_Team_Division|null the rank
   */
  public function getRank(Division $division) {
    $r = DB::getAll(DB::$DT_TEAM_DIVISION, new DBBool(array(new DBCond('team', $this),
                                                            new DBCond('division', $division))));
    $b;
    if (count($r) == 0) $b = null;
    else $b = $r[0];
    unset($r);
    return $b;
  }

  // ------------------------------------------------------------
  // RP
  // ------------------------------------------------------------

  /**
   * Gets the Dt_RP for this team in the given division and role
   *
   * @param String $div the division, or null for all divisions
   * @param String $role 'skipper', or 'crew'
   * @return Array:Dt_RP the rp for that team
   */
  public function getRpData(Division $div = null, $role = Dt_Rp::SKIPPER) {
    if ($div !== null) {
      $rank = $this->getRank($div);
      if ($rank === null)
        return array();
      return $rank->getRP($role);
    }
    $q = DB::prepGetAll(DB::$DT_TEAM_DIVISION, new DBCond('team', $this->id), array('id'));
    return DB::getAll(DB::$DT_RP, new DBBool(array(new DBCond('boat_role', $role),
                                                   new DBCondIn('team_division', $q))));
  }

  /**
   * Removes all Dt_RP entries for this team from the database
   *
   * @param Division $div the division whose RP info to reset
   */
  public function resetRpData(Division $div) {
    $q = DB::prepGetAll(DB::$DT_TEAM_DIVISION,
                        new DBBool(array(new DBCond('team', $this->id), new DBCond('division', $div))),
                        array('id'));
    foreach (DB::getAll(DB::$DT_RP, new DBCondIn('team_division', $q)) as $rp)
      DB::remove($rp);
  }
}

/**
 * Same as team, but ordered by rank, by default
 *
 * @author Dayan Paez
 * @version 2012-11-14
 */
class RankedTeam extends Team {
  protected function db_order() { return array('dt_rank'=>true, 'school'=>true, 'id'=>true); }
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
   * Returns either the skipper in A division, or the team name
   *
   * @return String name of the team or sailor
   */
  public function &getQualifiedName() {
    if ($this->regatta == null) return parent::__get("name");

    try {
      $rps = $this->__get('regatta')->getRpManager()->getRP($this, Division::A(), RP::SKIPPER);
      if (count($rps) == 0)
        return parent::__get("name");

      // Should be one, but just in case
      $sailors = array();
      foreach ($rps as $rp)
        $sailors[] = $rp->sailor;
      $sailors = implode("/", $sailors);
      return $sailors;
    } catch (Exception $e) {
      return parent::__get("name");
    }
  }
}

/**
 * Same as team, but ordered by rank, by default
 *
 * @author Dayan Paez
 * @version 2012-11-14
 */
class RankedSinglehandedTeam extends SinglehandedTeam {
  protected function db_order() { return array('dt_rank'=>true, 'school'=>true, 'id'=>true); }
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
  public $color;

  protected function db_order() { return array('sail'=>true); }
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
  /**
   * Returns a string representation of sail that is unique for a
   * given race, team pairing
   *
   * @return String race_id-team_id
   */
  public function hash() {
    $r = ($this->race instanceof Race) ? $this->race->id : $this->race;
    $t = ($this->team instanceof Team) ? $this->team->id : $this->team;
    return sprintf('%s-%s', $r, $t);
  }
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
 * Group of races (team racing only)
 *
 * @author Dayan Paez
 * @version 2013-05-04
 */
class Round_Group extends DBObject {
  protected function db_cache() { return true; }
  public function __toString() { return $this->id; }

  public function getRounds() {
    return DB::getAll(DB::$ROUND, new DBCond('round_group', $this));
  }

  /**
   * Returns string concatenation of round's titles
   *
   */
  public function getTitle() {
    $label = "";
    foreach ($this->getRounds() as $i => $round) {
      if ($i > 0)
        $label .= ", ";
      $label .= $round;
    }
    return $label;
  }
}

/**
 * A round-robin of races, for team racing applications
 *
 * @author Dayan Paez
 * @version 2012-12-06
 */
class Round extends DBObject {
  protected $regatta;
  public $title;
  public $scoring;
  public $relative_order;
  /**
   * @var Race_Group since team races can be "grouped" for ordering purposes
   */
  protected $round_group;

  public function db_type($field) {
    if ($field == 'regatta')
      return DB::$REGATTA;
    if ($field == 'round_group')
      return DB::$ROUND_GROUP;
    return parent::db_type($field);
  }
  protected function db_order() { return array('relative_order'=>true); }
  protected function db_cache() { return true; }
  // No indication as to natural ordering
  public function __toString() { return $this->title; }

  public static function compare(Round $r1, Round $r2) {
    return (int)$r1->relative_order - (int)$r2->relative_order;
  }

  /**
   * Sets the given round as master of this round
   *
   * If provided round is already a master, silently ignore
   *
   * @param Round $master the master
   * @param int $num_teams the number of teams to migrate
   * @throws InvalidArgumentException if $master not in same regatta, etc
   */
  public function addMaster(Round $master, $num_teams) {
    if ($master->__get('regatta') != $this->__get('regatta'))
      throw new InvalidArgumentException("Only rounds from same regatta can be masters.");
    if ($master->relative_order >= $this->relative_order)
      throw new InvalidArgumentException("Master rounds muts come before slave rounds.");

    // Is it already a master?
    foreach ($this->getMasters() as $old_master) {
      if ($old_master->id == $master->id)
        return;
    }

    $s = new Round_Slave();
    $s->slave = $this;
    $s->master = $master;
    $s->num_teams = $num_teams;
    DB::set($s);
    $this->_masters = null;
  }

  public function getMasters() {
    if ($this->_masters === null) {
      $this->_masters = array();
      foreach (DB::getAll(DB::$ROUND_SLAVE, new DBCond('slave', $this->id)) as $rel)
        $this->_masters[] = $rel->master;
    }
    return $this->_masters;
  }

  public function getSlaves() {
    if ($this->_slaves === null) {
      $this->_slaves = array();
      foreach (DB::getAll(DB::$ROUND_SLAVE, new DBCond('master', $this->id)) as $rel)
        $this->_slaves[] = $rel->slave;
    }
    return $this->_slaves;
  }

  private $_masters;
  private $_slaves;
}

/**
 * Rounds (slaves) that carry over from other rounds (masters)
 *
 * When carrying over races from other rounds, those teams that have
 * already met do not race again. The slave round will only include
 * the races necessary to complete the impartial round-robins from the
 * master rounds.
 *
 * Because of this, the net number of races that are created for the
 * slave round depends on the number of teams that carry over from all
 * of the master rounds. It is imperative that this number of races
 * remain the same, even after teams are substituted. As a result,
 * each master-slave record must also indicate the number of teams
 * that are to "advance" from one round to another.
 *
 * @author Dayan Paez
 * @version 2013-05-20
 */
class Round_Slave extends DBObject {
  protected $master;
  protected $slave;
  public $num_teams;

  public function db_type($field) {
    switch ($field) {
    case 'master':
    case 'slave':
      return DB::$ROUND;
    default:
      return parent::db_type($field);
    }
  }
}

/**
 * Linking table between round and races.
 *
 * This is applicable for races that are carried over from one round
 * to another one.
 *
 * @author Dayan Paez
 * @version 2013-04-29
 */
class Race_Round extends DBObject {
  protected $race;
  protected $round;

  public function db_type($field) {
    switch ($field) {
    case 'race': return DB::$RACE;
    case 'round': return DB::$ROUND;
    default:
      return parent::db_type($field);
    }
  }
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
  protected $round;
  public $number;
  public $scored_day;
  public $scored_by;

  /**
   * When the regatta scoring is "Team", then these are the two teams
   * that participate in this race
   */
  protected $tr_team1;
  protected $tr_team2;
  /**
   * @var int|null (team racing) ignore the race when creating
   * win-loss record for first team
   */
  public $tr_ignore1;
  /**
   * @var int|null (team racing) ignore the race when creating
   * win-loss record for second team
   */
  public $tr_ignore2;

  public function db_name() { return 'race'; }
  public function db_type($field) {
    switch ($field) {
    case 'division': return DBQuery::A_STR;
    case 'boat': return DB::$BOAT;
    case 'regatta': return DB::$REGATTA;
    case 'round': return DB::$ROUND;
    case 'tr_team1':
    case 'tr_team2':
      return DB::$TEAM;
    default:
      return parent::db_type($field);
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
  /**
   * Normal behavior is to have number and division, as in "3B".  But
   * if the regatta is combined division (or equivalent), then only
   * the race number is necessary.
   *
   * @return String the representation of the race
   */
  public function __toString() {
    if ($this->regatta !== null &&
        ($this->__get('regatta')->scoring != Regatta::SCORING_STANDARD ||
         count($this->__get('regatta')->getDivisions()) == 1))
      return (string)$this->number;
    return $this->number . $this->division;
  }

  // ------------------------------------------------------------
  // Rounds carried over
  // ------------------------------------------------------------

  /**
   * @var Array internal cache of extra rounds for this given race
   */
  private $_rounds = null;

  /**
   * Get list of rounds for this race
   *
   * @return Array:Round the rounds
   */
  public function getRounds() {
    if ($this->_rounds === null) {
      $this->_rounds = array();
      foreach (DB::getAll(DB::$RACE_ROUND, new DBCond('race', $this)) as $r)
        $this->_rounds[] = $r->round;
      usort($this->_rounds, 'Round::compare');
    }
    return $this->_rounds;
  }

  /**
   * Set the rounds to use for this race
   *
   * @param Array:Round $rounds the rounds to use
   */
  public function setRounds(Array $rounds) {
    $this->_rounds = array();
    $queue = array();
    foreach ($rounds as $round) {
      if ($round->id === null)
        DB::set($round, false);

      if (isset($queue[$round->id]))
        continue;

      $r = new Race_Round();
      $r->race = $this;
      $r->round = $round;
      $queue[$round->id] = $r;
      $this->_rounds[] = $round;
    }
    DB::removeAll(DB::$RACE_ROUND, new DBCond('race', $this));
    DB::insertAll($queue);
  }

  /**
   * Adds the given round to this race's list of extra rounds
   *
   * Only if the round is not already in the list of rounds
   *
   * @param Round $newRound the round to add
   */
  public function addRound(Round $newRound) {
    if ($newRound->id === null)
      DB::set($newRound, false);

    // check for uniqueness
    $curr = array();
    foreach ($this->getRounds() as $round)
      $curr[$round->id] = $round;
    if (isset($curr[$newRound->id]))
      return;

    // create new one
    $r = new Race_Round();
    $r->race = $this;
    $r->round = $newRound;
    DB::set($r, false);
  }

  /**
   * Removes any race-round relationship
   *
   * @param Round $oldRound
   */
  public function deleteRound(Round $oldRound) {
    DB::removeAll(DB::$RACE_ROUND, new DBBool(array(new DBCond('race', $this), new DBCond('round', $oldRound))));
    $this->_rounds = null;
  }

  /**
   * Parses the string and returns a Race object with the
   * corresponding division and number. Note that the race object
   * obtained is orphan. If no division is found, "A" is chosen by
   * default. This should suffice for combined scoring regattas and
   * the like.
   *
   * @param String $text the text representation of a race (3A, B12)
   * @return Race a race object
   * @throws InvalidArgumentException if unable to parse
   */
  public static function parse($text) {
    $race = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$text));
    $len = strlen($race);
    if ($len == 0)
      throw new InvalidArgumentException("Race missing number.");

    // ASCII: A = 65, D = 68, Z = 90
    $first = ord($race[0]);
    $last = ord($race[$len - 1]);

    $div = Division::A();
    if ($first >= 65 && $first <= 90) {
      if ($last > 68)
        throw new InvalidArgumentException(sprintf("Invalid division (%s).", $race[0]));
      $div = Division::get($race[0]);
      $race = substr($race, 1);
    }
    elseif ($last >= 65 && $last <= 90) {
      if ($last > 68)
        throw new InvalidArgumentException(sprintf("Invalid division (%s).", $race[$len - 1]));
      $div = Division::get($race[$len - 1]);
      $race = substr($race, 0, $len - 1);
    }

    if (!is_numeric($race))
      throw new InvalidArgumentException("Missing number for race.");

    $r = new Race();
    $r->division = $div;
    $r->number = (int)$race;
    return $r;
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
  public $earned;
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
    if (($modifier = $this->getModifier()) !== null)
      return $modifier->type;
    return $this->score;
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
   * @var Array:FinishModifier the modifiers (if any) for this
   * finish. The default value (null) is a flag that they have not yet
   * been deserialized from the database
   *
   * @see getModifier
   */
  private $modifiers = null;
  /**
   * @var boolean convenient flag to determine if the list of
   * modifiers has been changed
   */
  private $changed_modifier = false;

  /**
   * Convenience method removes other modifiers and optional adds new one
   *
   * @param FinishModifier $mod the modifier
   */
  public function setModifier(FinishModifier $mod = null) {
    $this->modifiers = array();
    $this->changed_modifier = true;
    if ($mod !== null) {
      $mod->finish = $this;
      $this->modifiers[] = $mod;
    }
  }

  /**
   * Adds the given modifier to the list of modifiers
   *
   * @param FinishModifier $mod the modifier to add
   */
  public function addModifier(FinishModifier $mod) {
    $this->getModifiers();
    $mod->finish = $this;
    $this->modifiers[] = $mod;
    $this->changed_modifier = true;
  }

  /**
   * Returns list of all modifiers associated with this finish
   *
   * @return Array:FinishModifier the list of modifiers
   */
  public function getModifiers() {
    if ($this->modifiers === null) {
      $this->modifiers = array();
      foreach (DB::getAll(DB::$FINISH_MODIFIER, new DBCond('finish', $this)) as $mod)
        $this->modifiers[] = $mod;
    }
    return $this->modifiers;
  }

  /**
   * Removes the given modifier from list, comparing by ID
   *
   * @param FinishModifier $mod the modifier to remove
   * @return boolean true if it was removed
   */
  public function removeModifier(FinishModifier $mod) {
    foreach ($this->getModifiers() as $i => $other) {
      if ($other->id == $mod->id) {
        unset($this->modifiers[$i]);
        $this->changed_modifier = true;
        return true;
      }
    }
    return false;
  }

  /**
   * Gets the first finish modifier, if any, for this finish.
   *
   * @return FinishModifier|null the modifier
   */
  public function getModifier() {
    $mods = $this->getModifiers();
    return (count($mods) == 0) ? null : $mods[0];
  }

  public function hasChangedModifier() { return $this->changed_modifier; }

  /**
   * Creates a hash for this finish consisting of race-team
   *
   */
  public function hash() {
    $rid = ($this->race instanceof Race) ? $this->race->id : $this->race;
    $tid = ($this->team instanceof Team) ? $this->team->id : $this->team;
    return $rid . '-' . $tid;
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

  public static function compareEarned(Finish $f1, Finish $f2) {
    if ($f1->earned === null || $f2->earned === null)
      return self::compareEntered($f1, $f2);
    return $f1->earned - $f2->earned;
  }

  
  /**
   * Helper method for team racing regattas.
   *
   * Returns string representation of finishes, such as 1-2-5.
   *
   * @param Array:Finish $places the list of finishes.
   */
  public static function displayPlaces(Array $places = array()) {
    usort($places, 'Finish::compareEarned');
    $disp = "";
    $pens = array();
    foreach ($places as $i => $finish) {
      if ($i > 0)
        $disp .= "-";
      $modifiers = $finish->getModifiers();
      if (count($modifiers) > 0) {
        $disp .= $finish->earned;
        foreach ($modifiers as $modifier)
          $pens[] = $modifier->type;
      }
      else
        $disp .= $finish->score;
    }
    if (count($pens) > 0)
      $disp .= " " . implode(",", $pens);
    return $disp;
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
    if ($name == 'division') {
      $div = Division::get($this->division);
      return $div;
    }
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
  public $mail_sent;
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
  public function __toString() { return (string)$this->summary; }
}

/**
 * Link between Sailor and Team: representative for the RP
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class Representative extends DBObject {
  protected $team;
  public $name;

  public function db_type($field) {
    switch ($field) {
    case 'team': return DB::$TEAM;
    default:
      return parent::db_type($field);
    }
  }

  public function __toString() { return $this->name; }
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
   * Fetches the first Saturday in the season.
   *
   * The first Saturday is the Saturday AFTER the start of the season,
   * except that for Spring and Fall, these are pre-defined as the
   * "First Saturday in February" and "September", respectively.
   *
   * @return DateTime
   */
  public function getFirstSaturday() {
    $start = $this->__get('start_date');
    if ($this->season == Season::FALL)
      $start = new DateTime(sprintf('%s-09-01', $this->getYear()));
    elseif ($this->season == Season::SPRING)
      $start = new DateTime(sprintf('%s-02-01', $this->getYear()));
    $start->add(new DateInterval(sprintf('P%sDT0H', (6 - $start->format('w')))));
    return $start;
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
    return sprintf("%s %s", ucfirst((string)$this->season), $this->getYear());
  }

  /**
   * Is this the season for the given date (or right now)?
   *
   * @param DateTime|null $time if given, the time to check, or "now"
   * @return boolean true if so
   */
  public function isCurrent(DateTime $time = null) {
    if ($this->__get('start_date') === null || $this->__get('end_date') === null)
      return false;
    if ($time === null)
      $time = DB::$NOW;
    return ($time > $this->__get('start_date') && $time < $this->__get('end_date'));
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
   * @param boolean $inc_private true to include private regatta in result
   * @return Array:Regatta
   */
  public function getRegattas($inc_private = false) {
    require_once('regatta/Regatta.php');
    return DB::getAll(($inc_private !== false) ? DB::$REGATTA : DB::$PUBLIC_REGATTA,
                      new DBBool(array(new DBCond('start_time', $this->start_date, DBCond::GE),
                                       new DBCond('start_time', $this->end_date,   DBCond::LT))));
  }

  /**
   * Get a list of regattas in this season in which the given
   * school participated. This is a convenience method.
   *
   * Only non-personal regattas are fetched
   *
   * @param School $school the school whose participation to verify
   * @param boolean $inc_private true to include private regattas
   * @return Array:Regatta
   */
  public function getParticipation(School $school, $inc_private = false) {
    require_once('regatta/Regatta.php');
    return DB::getAll(($inc_private !== false) ? DB::$REGATTA : DB::$PUBLIC_REGATTA,
                      new DBBool(array(new DBCond('start_time', $this->start_date, DBCond::GE),
                                       new DBCond('start_time', $this->end_date,   DBCond::LT),
                                       new DBCondIn('id', DB::prepGetAll(DB::$TEAM, new DBCond('school', $school), array('regatta'))))));
  }

  /**
   * Return the next season if it exists in the database.
   *
   * The "next" season is one of either spring or fall.
   *
   * @return Season|null the season
   * @throws InvalidArgumentException if used with either spring/fall
   */
  public function nextSeason() {
    $next = null;
    $year = $this->__get('start_date');
    if ($year === null)
      throw new InvalidArgumentException("There is no date this season. Thus, no nextSeason!");
    $year = $year->format('y');
    switch ($this->season) {
    case Season::SPRING:
      $next = 'f';
      break;

    case Season::FALL:
      $next = 's';
      $year++;
      break;

    default:
      throw new InvalidArgumentException("Next season only valid for spring and fall.");
    }
    return DB::get(DB::$SEASON, $next . $year);
  }

  /**
   * Return the previous season if it exists in the database.
   *
   * The "previous" season is one of either spring or fall.
   *
   * @return Season|null the season
   * @throws InvalidArgumentException if used with either spring/fall
   */
  public function previousSeason() {
    $next = null;
    $year = $this->__get('start_date');
    if ($year === null)
      throw new InvalidArgumentException("There is no date this season. Thus, no previousSeason!");
    $year = $year->format('y');
    switch ($this->season) {
    case Season::SPRING:
      $next = 'f';
      $year--;
      break;

    case Season::FALL:
      $next = 's';
      break;

    default:
      throw new InvalidArgumentException("Next season only valid for spring and fall.");
    }
    return DB::get(DB::$SEASON, $next . $year);
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
    require_once('regatta/Regatta.php');
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
    $time = clone $date;
    $time->setTime(0, 0, 0);
    $res = DB::getAll(DB::$SEASON, new DBBool(array(new DBCond('start_date', $time, DBCond::LE),
                                                    new DBCond('end_date', $time, DBCond::GE))));
    $r = (count($res) == 0) ? null : $res[0];
    unset($res);
    return $r;
  }

  /**
   * Returns a list of the seasons for which there are public
   * regattas, ordered in descending chronological order.
   *
   * @return Array:Season the list
   */
  public static function getActive() {
    $cond = new DBBool(array(new DBCond('private', null),
                             new DBCond('dt_status',Regatta::STAT_SCHEDULED, DBCond::NE)));
    return DB::getAll(DB::$SEASON, new DBCondIn('id', DB::prepGetAll(DB::$REGATTA, $cond, array('dt_season'))));
  }

  /**
   * Creates appropriate ID for given Season object.
   *
   * Does not assign the ID, but uses the object's start_date and
   * reported "season" to determine the appropriate ID, such as f11
   * for "Fall 2011"
   *
   * @param Season $obj the object whose ID to create
   * @return String the suitable ID
   * @throws InvalidArgumentException if attributes missing
   */
  public static function createID(Season $obj) {
    if ($obj->start_date === null || $obj->season === null)
      throw new InvalidArgumentException("Missing either start_date or season.");
    switch ($obj->season) {
    case Season::SPRING: $text = 's'; break;
    case Season::SUMMER: $text = 'm'; break;
    case Season::FALL:   $text = 'f'; break;
    case Season::WINTER: $text = 'w'; break;
    default:
      throw new InvalidArgumentException("Invalid season type.");
    }
    return $text . $obj->start_date->format('y');
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
class FinishModifier extends DBObject {

  protected $finish;
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
   * Fetches an associative list of the different penalty types
   *
   * @return Array<Penalty::Const,String> the different penalties
   */
  public static function getList() {
    return array();
  }

  public function db_name() { return 'finish_modifier'; }
  public function db_type($field) {
    if ($field == 'finish')
      return DB::$FINISH;
    return parent::db_type($field);
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
  public function __construct($type = null, $amount = -1, $comments = "", $displace = 0) {
    if ($this->type === null)     $this->type = $type;
    if ($this->amount === null)   $this->amount = (int)$amount;
    if ($this->comments === null) $this->comments = $comments;
    if ($this->displace === null) $this->displace = $displace;
  }

  /**
   * String representation, really useful for debugging purposes
   *
   * @return String string representation
   */
  public function __toString() {
    return $this->type;
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

class Dt_Rp extends DBObject {
  const SKIPPER = 'skipper';
  const CREW = 'crew';

  protected $team_division;
  protected $race_nums;
  protected $sailor;
  public $boat_role;
  public $rank;
  public $explanation;

  public function db_type($field) {
    if ($field == 'sailor') return DB::$MEMBER;
    if ($field == 'race_nums') return array();
    if ($field == 'team_division') return DB::$DT_TEAM_DIVISION;
    return parent::db_type($field);
  }
  protected function db_order() { return array('race_nums'=>true); }
}

/**
 * Team rank within division, and possible penalty
 *
 * @author Dayan Paez
 * @version 2011-03-06
 */
class Dt_Team_Division extends DBObject {
  protected $team;
  public $division;
  public $rank;
  public $explanation;
  public $penalty;
  public $comments;
  public $score;
  public $wins;
  public $losses;
  public $ties;

  public function db_name() { return 'dt_team_division'; }
  public function db_type($field) {
    if ($field == 'team') return DB::$TEAM;
    return parent::db_type($field);
  }
  protected function db_order() { return array('rank'=>true); }

  public function getRP($role = Dt_Rp::SKIPPER) {
    return DB::getAll(DB::$DT_RP, new DBBool(array(new DBCond('boat_role', $role),
                                                   new DBCond('team_division', $this->id))));
  }
}

/**
 * User editable, DPEditor-enabled, text element
 *
 * @author Dayan Paez
 * @version 2013-03-12
 */
class Text_Entry extends DBObject {
  public $plain;
  public $html;

  const ANNOUNCEMENTS = 'announcements';
  const WELCOME = 'welcome';

  /**
   * Fetches list of known sections
   *
   * @return Map
   */
  public static function getSections() {
    return array(self::ANNOUNCEMENTS => "Announcements",
                 self::WELCOME => "Public Welcome");
  }

}

/**
 * Template for ordering races in team racing
 *
 * The ID of the entry is a varchar that encodes the four parameters
 * which define a template:
 *
 * (# of divs)-(# of teams)-(# of boats)-(in/frequent)
 *
 * A value of '0' for the last entry means 'infrequent
 * rotation'. Thus, a template that defines 6 teams in 18 boats,
 * rotating frequently, would have ID = 6-18-1.
 *
 * The 'template' property is an array, each successive entry of which
 * is the next "race", encoded as a string. The string is of the form
 * "X-Y", where X and Y represent the (n+1)th team in the round.
 *
 * Thus, if MIT and Harvard are the second and fifth team in the
 * round, respectively, then the race "MIT vs. Harvard" would be
 * encoded as "2-5", and the opposite ("Harvard vs. MIT") would be
 * encoded "5-2". Note that indices are 1-based.
 *
 * @author Dayan Paez
 * @version 2013-05-08
 */
class Race_Order extends DBObject {
  public $num_teams;
  public $num_divisions;
  public $num_boats;
  public $name;
  public $description;
  protected $template;
  protected $author;

  public function db_type($field) {
    switch ($field) {
    case 'template':
      return array();
    case 'author':
      require_once('regatta/Account.php');
      return DB::$ACCOUNT;
    default:
      return parent::db_type($field);
    }
  }

  public function getPair($index) {
    if ($this->template === null || $index < 0  || $index > count($this->__get('template')))
      return array(null, null);
    $pairings = $this->__get('template');
    return explode('-', $pairings[$index]);
  }
}

/**
 * Rotation used for team racing regatta
 *
 * @author Dayan Paez
 * @version 2013-05-16
 */
class Regatta_Rotation extends DBObject {
  public $name;
  protected $regatta;
  protected $rotation;

  public function db_type($field) {
    switch ($field) {
    case 'regatta':
      return DB::$REGATTA;
    case 'rotation':
      return new TeamRotation();
    default:
      return parent::db_type($field);
    }
  }
}

/**
 * Saved All-America report parameters
 *
 * @author Dayan Paez
 * @version 2013-06-13
 */
class AA_Report extends DBObject {
  public $type;
  public $role;
  protected $seasons;
  protected $conferences;
  public $min_regattas;
  protected $regattas;
  protected $sailors;
  protected $last_updated;
  public $author;

  public function db_type($field) {
    switch ($field) {
    case 'regattas':
    case 'sailors':
    case 'conferences':
    case 'seasons':
      return array();
    case 'last_updated':
      return DB::$NOW;
    default:
      return parent::db_type($field);
    }
  }
}

/**
 * A permission line, used to regulate access to areas of the site
 *
 * @author Dayan Paez
 * @version 2013-07-17
 */
class Permission extends DBObject {
  public $title;
  public $description;
  protected function db_cache() { return true; }
  public function __toString() { return $this->title; }
}

/**
 * A bundle of permission entries, as assigned to an account
 *
 * @author Dayan Paez
 * @version 2013-07-17
 */
class Role extends DBObject {
  public $title;
  public $description;
  protected function db_cache() { return true; }
  public function __toString() { return $this->title; }

  /**
   * @var Array:Permission internal cache of permissions
   */
  private $permissions = null;
  
  /**
   * Returns list of Permission objects associated with this role
   *
   * @return Array:Permission
   */
  public function getPermissions() {
    if ($this->permissions === null) {
      $this->permissions = array();
      foreach (DB::getAll(DB::$ROLE_PERMISSION, new DBCond('role', $this)) as $link) {
        $this->permissions[] = $link->permission;
      }
    }
    return $this->permissions;
  }

  /**
   * Sets the list of permissions associated with this role
   *
   * @param Array:Permission $persm the list of permissions
   */
  public function setPermissions(Array $perms) {
    DB::removeAll(DB::$ROLE_PERMISSION, new DBCond('role', $this));
    foreach ($perms as $perm) {
      $link = new Role_Permission();
      $link->role = $this;
      $link->permission = $perm;
      DB::set($link);
    }
    $this->permissions = $perms;
  }
}

/**
 * Link between Permission and Role
 *
 * @author Dayan Paez
 * @version 2013-07-17
 */
class Role_Permission extends DBObject {
  protected $role;
  protected $permission;

  public function db_type($field) {
    switch ($field) {
    case 'role': return DB::$ROLE;
    case 'permission': return DB::$PERMISSION;
    default: return parent::db_type($field);
    }
  }
}

/**
 * "Sticky" key-value pairs for the application, as handled by DB
 *
 * @author Dayan Paez
 * @version 2013-09-16
 */
class STN extends DBObject {
  const TWITTER_URL_LENGTH = 'twitter_url_length';
  const SEND_MAIL = 'send_mail';
  const ALLOW_REGISTER = 'allow_register';

  const GCSE_ID = 'gcse_id';
  const FACEBOOK = 'facebook';
  const FACEBOOK_APP_ID = 'facebook_app_id';
  const TWITTER = 'twitter';
  const TWITTER_CONSUMER_KEY = 'twitter_consumer_key';
  const TWITTER_CONSUMER_SECRET = 'twitter_consumer_secret';
  const TWITTER_OAUTH_TOKEN = 'twitter_oauth_token';
  const TWITTER_OAUTH_SECRET = 'twitter_oauth_secret';
  const USERVOICE_ID = 'uservoice_id';
  const USERVOICE_FORUM = 'uservoice_forum';
  const FLICKR_NAME = 'flickr_name';
  const FLICKR_ID = 'flickr_id';

  public $value;
  public function db_name() { return 'setting'; }
  protected function db_cache() { return true; }
  public function __toString() { return $this->value; }
}
?>