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
  public static $ROUND_SEED = null;
  public static $ROUND_TEMPLATE = null;
  public static $RACE = null;
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
  public static $REGATTA_DOCUMENT_SUMMARY = null;
  public static $REGATTA_DOCUMENT = null;
  public static $REGATTA_DOCUMENT_RACE = null;
  public static $ROUND_SLAVE = null;
  public static $AA_REPORT = null;
  public static $PUB_REGATTA_URL = null;
  public static $PUB_FILE = null;
  public static $PUB_FILE_SUMMARY = null;
  public static $PUB_SPONSOR = null;
  public static $WEBSESSION = null;

  public static $PERMISSION = null;
  public static $ROLE = null;
  public static $ROLE_PERMISSION = null;
  public static $SETTING = null;
  public static $TEAM_ROTATION = null;
  public static $SYNC_LOG = null;

  public static $OUTBOX = null;
  public static $MESSAGE = null;
  public static $ACCOUNT = null;
  public static $ACCOUNT_SCHOOL = null;
  public static $ACCOUNT_CONFERENCE = null;
  public static $FULL_REGATTA = null;
  public static $REGATTA = null;
  public static $PUBLIC_REGATTA = null;
  public static $RP_LOG = null; // RpManager.php
  public static $RP_FORM = null; // RpManager.php
  public static $UPDATE_REQUEST = null; // UpdateRequest.php
  public static $UPDATE_SCHOOL = null; // UpdateRequest.php
  public static $UPDATE_SEASON = null; // UpdateRequest.php
  public static $UPDATE_FILE = null;   // UpdateRequest.php
  public static $UPDATE_CONFERENCE = null; // UpdateRequest.php

  public static $MERGE_LOG = null; // MergeLog.php
  public static $MERGE_SAILOR_LOG = null; // MergeLog.php
  public static $MERGE_RP_LOG = null; // MergeLog.php

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
    self::$ROUND_SEED = new Round_Seed();
    self::$ROUND_TEMPLATE = new Round_Template();
    self::$RACE = new Race();
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
    self::$REGATTA_DOCUMENT_SUMMARY = new Document_Summary();
    self::$REGATTA_DOCUMENT = new Document();
    self::$REGATTA_DOCUMENT_RACE = new Document_Race();
    self::$ROUND_SLAVE = new Round_Slave();
    self::$AA_REPORT = new AA_Report();
    self::$PUB_REGATTA_URL = new Pub_Regatta_Url();
    self::$PUB_FILE = new Pub_File();
    self::$PUB_FILE_SUMMARY = new Pub_File_Summary();
    self::$PUB_SPONSOR = new Pub_Sponsor();
    self::$WEBSESSION = new Websession();
    self::$SYNC_LOG = new Sync_Log();

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
   * Gets the first Role designated as "is_default"
   *
   * @return Role should always return a Role
   */
  public static function getDefaultRole() {
    $res = self::getAll(self::$ROLE, new DBCond('is_default', 1));
    return (count($res) == 0) ? null : $res[0];
  }

  /**
   * Perform keyword replacement using given account
   *
   * @param String $mes the template message
   * @param Account $to the account whose values to replace in message
   * @param School $school the school involved (optional)
   * @return String the replaced message
   */
  public static function keywordReplace($mes, Account $to, School $school = null) {
    $mes = str_replace('{FIRST_NAME}', $to->first_name, $mes);
    $mes = str_replace('{LAST_NAME}', $to->last_name, $mes);
    $mes = str_replace('{ROLE}', ucfirst($to->role), $mes);
    $mes = str_replace('{FULL_NAME}', $to->getName(), $mes);
    $mes = str_replace('{SCHOOL}',    $school, $mes);
    return $mes;
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
   * @param Array $extra_headers optional map of extra headers to send
   * @return boolean the result, as returned by mail
   */
  public static function mail($to, $subject, $body, $wrap = true, Array $extra_headers = array()) {
    if (DB::g(STN::DIVERT_MAIL) !== null) {
      $meant = $to;
      if (is_array($to))
        $meant = implode(", ", $to);
      $body = "Message meant for $meant\n\n" . $body;
      $to = DB::g(STN::DIVERT_MAIL);
      $subject = 'DIVERTED: ' . $subject;
    }
    if ($wrap)
      $body = wordwrap($body, 72);

    if (!is_array($to))
      $to = array($to);

    $res = true;
    $header = "";
    $extra_headers["From"] = DB::g(STN::TS_FROM_MAIL);
    $extra_headers["Content-Type"] = "text/plain; charset=utf8";
    foreach ($extra_headers as $key => $val)
      $header .= sprintf("%s: %s\r\n", $key, $val);
    foreach ($to as $recipient)
      $res = $res && @mail($recipient, $subject, $body, $header);
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
    if (DB::g(STN::DIVERT_MAIL) !== null) {
      $to = DB::g(STN::DIVERT_MAIL);
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

    $headers = sprintf("From: %s\nMIME-Version: 1.0\nContent-Type: multipart/alternative; boundary=%s\n", DB::g(STN::TS_FROM_MAIL), $bdry);
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
   * @param Account $from the sender
   * @param Account $acc the recipient
   * @param String $sub the subject of the message
   * @param String $mes the message
   * @param boolean $email true to send e-mail message
   * @return Message the queued message
   */
  public static function queueMessage(Account $from, Account $acc, $sub, $con, $email = false) {
    require_once('regatta/Message.php');
    $mes = new Message();
    $mes->sender = $from;
    $mes->account = $acc;
    $mes->subject = $sub;
    $mes->content = $con;
    self::set($mes, false);

    if ($email !== false)
      self::mail($acc->id, $sub, $mes, true, array('Reply-To' => sprintf('%s <%s>', $from, $from->id)));

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
    $to = ($mes->sender === null) ? DB::g(STN::TS_FROM_MAIL) : $mes->sender->id;
    $res = self::mail($to, sprintf("[%s] Message reply", DB::g(STN::APP_NAME)), $body, true, array('Reply-To' => $mes->account->id));
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
   * Fetches the registered Sailor with the given ID
   *
   * @param int $id the ID of the registered sailor
   * @return Sailor|null the sailor
   */
  public static function getRegisteredSailor($id) {
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
   * Fetches the (first) account which has the recovery token provided.
   *
   * NOTE: client should check that the account's token is still active
   *
   * @param String $hash the hash
   * @return Account|null the matching account or null if none match
   * @see Account::isTokenActive
   */
  public static function getAccountFromToken($hash) {
    require_once('regatta/Account.php');
    $res = self::getAll(self::$ACCOUNT, new DBCond('recovery_token', $hash));
    $r = (count($res) == 0) ? null : $res[0];
    unset($res);
    return $r;
  }

  /**
   * Returns a list of accounts fulfilling the given role
   *
   * @param String|null $role a possible Account role
   * @param String|null $status a possible Account status
   * @param Role|null $ts_role the role to limit by
   * @return Array:Account the list of accounts
   * @throws InvalidArgumentException if provided role is invalid
   */
  public static function getAccounts($role = null, $status = null, Role $ts_role = null) {
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
    if ($ts_role !== null) {
      if ($cond === null)
	$cond = new DBCond('ts_role', $ts_role->id);
      else
	$cond = new DBBool(array($cond, new DBCond('ts_role', $ts_role->id)));
    }
    return self::getAll(self::$ACCOUNT, $cond);
  }

  /**
   * Search accounts, with optional role and/or status filter
   *
   * @param String|null $role a possible Account role
   * @param String|null $status a possible Account status
   * @param Role|null $ts_role limit to those roles
   * @return Array:Account the list of accounts
   * @throws InvalidArgumentException if provided role is invalid
   */
  public static function searchAccounts($qry, $role = null, $status = null, Role $ts_role = null) {
    $fields = array('first_name', 'last_name', 'id', 'concat(first_name, " ", last_name)');
    require_once('regatta/Account.php');
    if ($role === null && $status === null && $ts_role === null)
      return self::search(DB::$ACCOUNT, $qry, $fields);

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
    if ($ts_role !== null) {
      $cond->add(new DBCond('ts_role', $ts_role->id));
    }

    $q = self::prepSearch(DB::$ACCOUNT, $qry, $fields);
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
   * Return a human-readable representation of time difference
   *
   * @param DateTime $timestamp the timestamp in question
   * @param DateTime $relative current time
   * @return String e.g. "about 5 minutes ago"
   */
  public static function howLongFrom(DateTime $timestamp, DateTime $relative = null) {
    if ($relative === null)
      $relative = DB::$NOW;
    $interval = $relative->diff($timestamp);
    $result = self::howLong($interval);
    if ($result == '1 day')
      return ($interval->invert) ? "yesterday" : "tomorrow";
    if ($interval->invert)
      return $result . " ago";
    return "in " . $result;
  }

  /**
   * Format a time interval as a human-readable string
   *
   * @param DateTime $relative current time
   * @return String e.g. "about 5 minutes ago"
   */
  public static function howLong(DateInterval $interval) {
    if ($interval->y > 1)
      return sprintf("more than %d years", $interval->y);
    if ($interval->y == 1)
      return "more than a year";
    if ($interval->m > 1)
      return sprintf("%d months", $interval->m);
    if ($interval->d > 1)
      return sprintf("%d days", $interval->d);
    if ($interval->d == 1)
      return "1 day";
    if ($interval->h > 0)
      return sprintf("%d hour%s", $interval->h, ($interval->h > 1) ? "s" : "");
    if ($interval->i > 55)
      return "about an hour";
    if ($interval->i > 1)
      return sprintf("%d minutes", $interval->i);
    if ($interval->i > 0)
      return "a minute";
    return "less than a minute";
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
   * Fetches any existing race order that matches the given fields
   *
   * @param String $num_divisions (3 is the usual)
   * @param String $num_teams the number of teams
   * @param String $num_boats the number of boats
   * @param Const $frequency one of Race_Order::FREQUENCY_*
   * @param Array $master the distribution of teams to carry over
   */
  public static function getRaceOrder($num_divisions, $num_teams, $num_boats, $frequency, Array $master = null) {
    $master_teams = null;
    if ($master !== null && count($master) > 0)
      $master_teams = implode("\0", $master);
    $r = DB::getAll(DB::$RACE_ORDER,
                    new DBBool(array(new DBCond('num_teams', $num_teams),
                                     new DBCond('master_teams', $master_teams),
                                     new DBCond('num_boats', $num_boats),
                                     new DBCond('frequency', $frequency),
                                     new DBCond('num_divisions', $num_divisions))));
    if (count($r) == 0)
      return null;
    return $r[0];
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

  public static function getFile($name) {
    return DB::get(DB::$PUB_FILE, $name);
  }

  /**
   * Creates a suitable URL from given string
   *
   * @param String $seed the input
   * @param boolean $apply_rule_c false to NOT remove short words
   * @param Array $blacklist additional words to remove
   * return String the URL-safe equivalent
   */
  public static function slugify($seed, $apply_rule_c = true, Array $blacklist = array()) {
    // remove spaces, ('s)'s
    $url = strtolower($seed);
    $url = str_replace('\'s', '', $url);
    $url = str_replace('/', '-', $url);
    $url = str_replace(' ', '-', $url);
    $url = str_replace('_', '-', $url);

    // remove unwarranted characters and squeeze dashes
    $url = preg_replace('/[^a-z0-9-]/', '', $url);
    $url = preg_replace('/-+/', '-', $url);

    // short words and blacklist
    $tokens = explode('-', $url);
    $copy = $tokens;
    foreach ($copy as $i => $token) {
      if (in_array($token, $blacklist) || ($apply_rule_c && strlen($token) < 2))
        unset($tokens[$i]);
    }
    $tokens = implode('-', $tokens);
    if (strlen($tokens) < 3)
      return $url;
    return $tokens;
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
      else
        self::$settings[$key] = STN::getDefault($key);
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

  /**
   * Fetches the form to use for the given regatta
   *
   * @return AbstractRpForm the form, if any
   */
  public static function getRpFormWriter(FullRegatta $reg) {
    $divisions = count($reg->getDivisions());
    if ($reg->scoring == Regatta::SCORING_TEAM) {
      $form = self::g(STN::RP_TEAM_RACE);
    }
    elseif ($reg->isSingleHanded()) {
      $form = self::g(STN::RP_SINGLEHANDED);
    }
    elseif ($divisions == 2) {
      $form = self::g(STN::RP_2_DIVISION);
    }
    elseif ($divisions == 3) {
      $form = self::g(STN::RP_3_DIVISION);
    }
    elseif ($divisions == 4) {
      $form = self::g(STN::RP_4_DIVISION);
    }
    elseif ($divisions == 1) {
      $form = self::g(STN::RP_1_DIVISION);
    }
    else
      throw new InvalidArgumentException("Regattas of this type are not supported.");

    if ($form === null)
      return null;

    require_once(sprintf('rpwriter/%s.php', $form));
    return new $form();
  }

  // ------------------------------------------------------------
  // Tweet
  // ------------------------------------------------------------

  private static $twitterer = false;

  /**
   * Wrapper around TwitterWriter
   *
   * @param String $mes
   */
  public static function tweet($mes) {
    if (self::$twitterer === false) {
      $ck = DB::g(STN::TWITTER_CONSUMER_KEY);
      $cs = DB::g(STN::TWITTER_CONSUMER_SECRET);
      $ot = DB::g(STN::TWITTER_OAUTH_TOKEN);
      $os = DB::g(STN::TWITTER_OAUTH_SECRET);
      if ($ck === null || $cs === null || $ot === null || $os === null)
        self::$twitterer = null;
      else {
        require_once('twitter/TwitterWriter.php');
        self::$twitterer = new TwitterWriter($ck, $cs, $ot, $os);
      }
    }
    if (self::$twitterer !== null)
      self::$twitterer->tweet($mes);
  }
}

/**
 * Interface for serializing to a resource
 *
 */
interface Writeable {
  public function write($resource);
}

/**
 * Exception class for permission-related issues
 *
 * @author Dayan Paez
 * @version 2014-05-11
 */
class PermissionException extends Exception {
  public $regatta;
  public function __construct($message = null, Regatta $regatta = null) {
    parent::__construct($message, 1);
    $this->regatta = $regatta;
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
  public $tweet_summary;
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
  public $url;
  protected $mail_lists;
  public function __toString() {
    return $this->id;
  }
  protected function db_cache() { return true; }

  public function db_type($field) {
    if ($field == 'mail_lists')
      return array();
    return parent::db_type($field);
  }

  /**
   * Returns a list of users from this conference
   *
   * @param String|null $status a possible Account status
   * @return Array:Account list of users
   */
  public function getUsers($status = null) {
    require_once('regatta/Account.php');
    $cond = new DBCondIn('school', DB::prepGetAll(DB::$SCHOOL, new DBCond('conference', $this), array('id')));
    if ($status !== null) {
      $statuses = Account::getStatuses();
      if (!isset($statuses[$status]))
        throw new InvalidArgumentException("Invalid status provided: $status.");
      $cond = new DBBool(array($cond, new DBCond('status', $status)));
    }
    return DB::getAll(DB::$ACCOUNT, $cond);
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

  /**
   * Creates the full URL to this conference's public summary page
   *
   * The URL is built from /<STN::CONFERENCE_URL>/<url>/, where <url>
   * is the lowercase version of the ID
   *
   * @return String the full URL
   */
  public function createUrl() {
    if ($this->id === null)
      throw new InvalidArgumentException("No ID exists for this conference.");
    return sprintf('/%s/%s/', DB::g(STN::CONFERENCE_URL), strtolower($this->id));
  }
}

/**
 * Burgees: primary key matches with (and is a foreign key) to school.
 *
 * @author Dayan Paez
 * @version 2012-01-07
 */
class Burgee extends DBObject implements Writeable {
  public $filedata;
  public $width;
  public $height;
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

  public function write($resource) {
    fwrite($resource, base64_decode($this->filedata));
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
  public $url;
  public $city;
  public $state;
  protected $conference;
  protected $burgee;
  protected $burgee_small;
  protected $burgee_square;
  protected $inactive;
  protected $sync_log;

  public function db_name() { return 'school'; }
  public function db_type($field) {
    switch ($field) {
    case 'conference': return DB::$CONFERENCE;
    case 'burgee':
    case 'burgee_small':
    case 'burgee_square':
      return DB::$BURGEE;
    case 'inactive': return DB::$NOW;
    case 'sync_log': return DB::$SYNC_LOG;
    default:
      return parent::db_type($field);
    }
  }
  protected function db_cache() { return true; }
  protected function db_order() { return array('name'=>true); }
  public function __toString() { return $this->name; }

  /**
   * Return IMG element of burgee, if burgee exists
   *
   * @param mixed $def the element to return if no burgee exists
   * @param Array $attrs extra attributes to use for XImg
   * @return XImg|null
   */
  public function drawBurgee($def = null, Array $attrs = array()) {
    if ($this->burgee === null || $this->id === null)
      return $def;

    $img = new XImg(sprintf('/inc/img/schools/%s.png', $this->id), $this->nick_name, $attrs);
    if ($this->__get('burgee')->width !== null) {
      $img->set('width', $this->__get('burgee')->width);
      $img->set('height', $this->__get('burgee')->height);
    }
    return $img;
  }

  /**
   * Returns IMG element of small burgee
   *
   * @param mixed $def the element to return if no burgee exists
   * @param Array $attrs extra attributes to use for XImg
   * @return XImg|null
   * @see drawBurgee
   */
  public function drawSmallBurgee($def = null, Array $attrs = array()) {
    if ($this->burgee_small === null || $this->id === null)
      return $def;

    $img = new XImg(sprintf('/inc/img/schools/%s-40.png', $this->id), $this->nick_name, $attrs);
    if ($this->__get('burgee_small')->width !== null) {
      $img->set('width', $this->__get('burgee_small')->width);
      $img->set('height', $this->__get('burgee_small')->height);
    }
    return $img;
  }

  /**
   * Returns IMG element of square burgee
   *
   * @param mixed $def the element to return if no burgee exists
   * @param Array $attrs extra attributes to use for XImg
   * @return XImg|null
   * @see drawBurgee
   */
  public function drawSquareBurgee($def = null, Array $attrs = array()) {
    if ($this->burgee_square === null || $this->id === null)
      return $def;

    $img = new XImg(sprintf('/inc/img/schools/%s-sq.png', $this->id), $this->nick_name, $attrs);
    if ($this->__get('burgee_square')->width !== null) {
      $img->set('width', $this->__get('burgee_square')->width);
      $img->set('height', $this->__get('burgee_square')->height);
    }
    return $img;
  }

  /**
   * Determines whether this school has the given burgee type.
   *
   * This method saves memory by avoiding the direct serialization of
   * the burgee property, bypassing the magic __get method.
   *
   * Since it should be the case that all versions exist, or none
   * at all, the argument to this method is (usually) unnecessary. It
   * is included for precision control, in order to check against the
   * specific version (e.g. '', 'small', 'square').
   *
   * @param String $type (optional) the burgee version (small, etc)
   * @return boolean true if burgee exists
   */
  public function hasBurgee($type = '') {
    switch ($type) {
    case 'small':  return $this->burgee_small !== null;
    case 'square': return $this->burgee_square !== null;
    default:       return $this->burgee !== null;
    }
  }

  /**
   * Returns the public URL root for this school
   *
   * This is /schools/<url>/, where <url> is the "url" property if one
   * exists, or the ID otherwise
   *
   * @return String the URL
   * @throws InvalidArgumentException if no "url" or "id" provided
   */
  public function getURL() {
    if ($this->url !== null)
      return '/schools/' . $this->url . '/';
    if ($this->id === null)
      throw new InvalidArgumentException("No ID exists for this school.");
    return '/schools/' . $this->id . '/';
  }

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
   * @param boolean $only_registered true to narrow down to registered
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
   * Get all the accounts which have access to this school.
   *
   * Access is either assigned directly or indirectly.
   *
   * @param String|null $status a possible Account status
   * @param boolean $effective falase to ignore permissions and return
   * only assigned values
   *
   * @return Array:Account
   */
  public function getUsers($status = null, $effective = true) {
    require_once('regatta/Account.php');
    $cond = new DBCondIn('id', DB::prepGetAll(DB::$ACCOUNT_SCHOOL, new DBCond('school', $this->id), array('account')));
    if ($effective !== false) {
      $cond = new DBBool(array(new DBCond('admin', null, DBCond::NE), $cond), DBBool::mOR);
    }
    if ($status !== null) {
      $statuses = Account::getStatuses();
      if (!isset($statuses[$status]))
        throw new InvalidArgumentException("Invalid status provided: $status.");
      $cond = new DBBool(array($cond, new DBCond('status', $status)));
    }
    return DB::getAll(DB::$ACCOUNT, $cond);
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

  public function getRounds() {
    return DB::getAll(DB::$ROUND, new DBCond('boat', $this));
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
  protected $sync_log;

  const MALE = 'M';
  const FEMALE = 'F';

  const COACH = 'coach';
  const STUDENT = 'student';

  public function db_type($field) {
    switch ($field) {
    case 'school': return DB::$SCHOOL;
    case 'sync_log': return DB::$SYNC_LOG;
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
 * Encapsulates a sailor, whether registered or not.
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
  public $dt_complete_rp;

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

  // Comparators

  /**
   * Sorts the teams based on school's full name
   *
   * @param Team $t1 the first team
   * @param Team $t2 the second team
   * @return int < 0 if $t1 comes before $t2...
   */
  public static function compare(Team $t1, Team $t2) {
    $diff = strcmp($t1->__get('school')->name, $t2->__get('school')->name);
    if ($diff == 0)
      return $t1->id - $t2->id;
    return $diff;
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
  public $relative_order;

  public $num_teams;
  public $num_boats;
  public $rotation_frequency;
  protected $sailoff_for_round;
  protected $race_order;
  protected $rotation;
  protected $boat;

  /**
   * @var Race_Group since team races can be "grouped" for ordering purposes
   */
  protected $round_group;

  public function db_type($field) {
    if ($field == 'regatta')
      return DB::$REGATTA;
    if ($field == 'sailoff_for_round')
      return DB::$ROUND;
    if ($field == 'round_group')
      return DB::$ROUND_GROUP;
    if ($field == 'race_order')
      return array();
    if ($field == 'boat')
      return DB::$BOAT;
    if ($field == 'rotation') {
      require_once('regatta/TeamRotation.php');
      return DB::$TEAM_ROTATION;
    }
    return parent::db_type($field);
  }
  protected function db_order() { return array('relative_order'=>true); }
  protected function db_cache() { return true; }
  // No indication as to natural ordering
  public function __toString() { return $this->title; }

  /**
   * Does this round have an associated rotation?
   *
   * @return boolean
   */
  public function hasRotation() {
    return ($this->rotation !== null);
  }

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
    foreach ($this->getMasters() as $old_rel) {
      if ($old_rel->master->id == $master->id)
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
        $this->_masters[] = $rel;
    }
    return $this->_masters;
  }

  public function getMasterRounds() {
    $list = array();
    foreach ($this->getMasters() as $rel)
      $list[] = $rel->master;
    return $list;
  }

  public function getSlaves() {
    if ($this->_slaves === null) {
      $this->_slaves = array();
      foreach (DB::getAll(DB::$ROUND_SLAVE, new DBCond('master', $this->id)) as $rel)
        $this->_slaves[] = $rel;
    }
    return $this->_slaves;
  }

  private $_masters;
  private $_slaves;

  // ------------------------------------------------------------
  // Race orders
  // ------------------------------------------------------------

  /**
   * Fetches the pair of team indices
   *
   * @param int $index the index within the race_order
   * @return Array with two indices: team1, and team2
   */
  public function getRaceOrderPair($index) {
    if (($tmpl = $this->getTemplate()) === null || $index < 0  || $index >= count($tmpl))
      return array(null, null);
    return array($tmpl[$index]->team1, $tmpl[$index]->team2);
  }

  /**
   * Fetches the boat for given index
   *
   * @param int $index the index within the race order
   * @return Boat the corresponding boat
   */
  public function getRaceOrderBoat($index) {
    if (($tmpl = $this->getTemplate()) === null || $index < 0  || $index >= count($tmpl))
      return null;
    return $tmpl[$index]->boat;
  }

  /**
   * Convenience method returns first boat in race order
   *
   * @return Boat|null boat in first race
   */
  public function getBoat() {
    return $this->getRaceOrderBoat(0);
  }

  /**
   * Return all the boats used in this round's template
   *
   * @return Array:Boat
   */
  public function getBoats() {
    $list = array();
    for ($i = 0; $i < $this->getRaceOrderCount(); $i++) {
      $boat = $this->getRaceOrderBoat($i);
      if ($boat !== null)
	$list[$boat->id] = $boat;
    }
    return array_values($list);
  }

  /**
   * The number of races in internal order
   *
   * @return int the count
   */
  public function getRaceOrderCount() {
    if (($tmpl = $this->getTemplate()) === null)
      return 0;
    return count($tmpl);
  }

  /**
   * Sets or unsets the race order
   *
   * @param Array:Array list of pairs
   * @param Array:Boat if provided, the boat to use in each race
   * @throws InvalidArgumentException if list of boats is invalid
   */
  public function setRaceOrder(Array $order, Array $boats = null) {
    if ($boats !== null && count($boats) != count($order))
      throw new InvalidArgumentException("List of boats must match list of races.");

    $this->_template = array();
    foreach ($order as $i => $pair) {
      if (!is_array($pair) || count($pair) != 2)
	throw new InvalidArgumentException("Missing pair for index $i.");
      $elem = new Round_Template();
      $elem->round = $this;
      $elem->team1 = array_shift($pair);
      $elem->team2 = array_shift($pair);
      $elem->boat = ($boats === null) ? $this->__get('boat') : $boats[$i];
      $this->_template[] = $elem;
    }
  }

  public function setRaceOrderBoat($index, Boat $boat) {
    $this->getTemplate();
    if ($index < 0 || $index >= count($this->_template))
      return;
    $this->_template[$index]->boat = $boat;
  }

  public function removeRaceOrder() {
    $this->_template = null;
  }

  /**
   * Actually commits the internal race order
   *
   */
  public function saveRaceOrder() {
    if ($this->_template === false)
      return;

    DB::removeAll(DB::$ROUND_TEMPLATE, new DBCond('round', $this));
    if ($this->_template !== null)
      DB::insertAll($this->_template);
  }

  /**
   * Fetches the list of race orders, as pairs
   *
   * @return Array:Array
   */
  public function getRaceOrder() {
    $res = array();
    for ($i = 0; $i < $this->getRaceOrderCount(); $i++) {
      $res[] = $this->getRaceOrderPair($i);
    }
    return $res;
  }

  public function hasRaceOrder() {
    return $this->getTemplate() !== null;
  }

  public function getRaceBoats() {
    $res = array();
    for ($i = 0; $i < $this->getRaceOrderCount(); $i++) {
      $res[] = $this->getRaceOrderBoat($i);
    }
    return $res;
  }

  public function getTemplate() {
    if ($this->_template === false) {
      $this->_template = null;
      $list = array();
      foreach (DB::getAll(DB::$ROUND_TEMPLATE, new DBCond('round', $this)) as $entry)
	$list[] = $entry;
      if (count($list) > 0)
	$this->_template = $list;
    }
    return $this->_template;
  }

  private $_template = false;

  // ------------------------------------------------------------
  // Rotation
  // ------------------------------------------------------------

  /**
   * Fetch the list of sails
   *
   * @return Array:String list of sails
   */
  public function getSails() {
    if ($this->rotation === null)
      return array();
    return $this->__get('rotation')->sails;
  }

  /**
   * Fetch the list of colors
   *
   * @return Array:String corresponding list of colors
   */
  public function getColors() {
    if ($this->rotation === null)
      return array();
    return $this->__get('rotation')->colors;
  }

  /**
   * Sets the list of sails
   *
   * @param Array:String $sails
   */
  public function setSails(Array $sails = array()) {
    if ($this->rotation === null)
      $this->rotation = new TeamRotation();
    $this->__get('rotation')->sails = $sails;
  }

  /**
   * Sets the list of colors
   *
   * @param Array:String $colors
   */
  public function setColors(Array $colors = array()) {
    if ($this->rotation === null)
      $this->rotation = new TeamRotation();
    $this->__get('rotation')->colors = $colors;
  }

  public function setRotation(Array $sails, Array $colors) {
    $this->rotation = new TeamRotation();
    $this->setSails($sails);
    $this->setColors($colors);
  }

  public function removeRotation() {
    $this->rotation = null;
  }

  public function getRotationCount() {
    if ($this->rotation === null)
      return 0;
    return $this->__get('rotation')->count();
  }

  public function getSailAt($i) {
    if ($this->rotation === null)
      return null;
    return $this->__get('rotation')->sailAt($i);
  }

  public function getColorAt($i) {
    if ($this->rotation === null)
      return null;
    return $this->__get('rotation')->colorAt($i);
  }

  /**
   * Creates and returns sail #/color assignment for given frequency
   *
   * @param Round $round the round whose race_order to use
   * @param Array:Team $teams ordered list of teams
   * @param Array:Division the number of divisions
   * @param Const $frequency one of Race_Order::FREQUENCY_*
   * @return Array a map of sails indexed first by race number, and then by
   *   team index, and then by divisions
   * @throws InvalidArgumentException
   */
  public function assignSails(Array $teams, Array $divisions, $frequency = null) {
    if ($frequency === null)
      $frequency = $this->rotation_frequency;
    if ($frequency === null)
      throw new InvalidArgumentException("No rotation frequency provided.");
    if ($this->rotation === null)
      return array();
    return $this->__get('rotation')->assignSails($this, $teams, $divisions, $frequency);
  }

  // ------------------------------------------------------------
  // Seeding
  // ------------------------------------------------------------

  /**
   * Set the ordered list of teams
   *
   * @param Array:Round_Seed $seeds the seeds (need not be continuous)
   */
  public function setSeeds(Array $seeds) {
    DB::removeAll(DB::$ROUND_SEED, new DBCond('round', $this));
    $list = array();
    foreach ($seeds as $seed) {
      $seed->round = $this;
      if ($seed->id === null)
        $list[] = $seed;
      else
        DB::set($seed, true);
    }
    if (count($list) > 0)
      DB::insertAll($list);
  }

  /**
   * Retrieve the list of ordered teams for this round, if any
   *
   */
  public function getSeeds() {
    return DB::getAll(DB::$ROUND_SEED, new DBCond('round', $this));
  }
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
 * The seeded teams for a round
 *
 * @author Dayan Paez
 * @version 2014-01-13
 */
class Round_Seed extends DBObject {
  public $seed;
  protected $round;
  protected $original_round;
  protected $team;

  public function db_type($field) {
    if ($field == 'round' || $field == 'original_round')
      return DB::$ROUND;
    if ($field == 'team')
      return DB::$TEAM;
    return parent::db_type($field);
  }

  protected function db_order() {
    return array('seed'=>true);
  }
}

/**
 * Race order for round
 *
 * @author Dayan Paez
 * @version 2014-04-02
 */
class Round_Template extends DBObject {
  public $team1;
  public $team2;
  protected $round;
  protected $boat;

  public function db_type($field) {
    if ($field == 'round')
      return DB::$ROUND;
    if ($field == 'boat')
      return DB::$BOAT;
    return parent::db_type($field);
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
  public $tweet_sent;
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

  /**
   * Returns textual representation of sailor.
   *
   * Because sailor might be null (which means no-show) calling this
   * method will return "No show" rather than the empty String.
   *
   * @param boolean $xml true to wrap No shows in XSpan
   * @return String the sailor
   */
  public function getSailor($xml = false) {
    if ($this->sailor === null) {
      if ($xml !== false)
        return new XSpan("No show", array('class'=>'noshow'));
      return "No show";
    }
    return (string)$this->__get('sailor');
  }
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
   * Fetches the regatta, if any, with given URL
   *
   * @param String $url the URL to fetch
   * @return Regatta|null
   */
  public function getRegattaWithURL($url) {
    require_once('regatta/Regatta.php');
    $res = DB::getAll(DB::$PUBLIC_REGATTA,
                      new DBBool(array(new DBCond('start_time', $this->start_date, DBCond::GE),
                                       new DBCond('start_time', $this->end_date,   DBCond::LT),
                                       new DBCond('nick', $url))));
    if (count($res) == 0)
      return null;
    return $res[0];
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
   * Get a list of regattas in this season in which any school from
   * the given conference participated. This is a convenience method.
   *
   * Only non-personal regattas are fetched
   *
   * @param Conference $conference the conference whose participation to verify
   * @param boolean $inc_private true to include private regattas
   * @return Array:Regatta
   * @see getParticipation
   */
  public function getConferenceParticipation(Conference $conference, $inc_private = false) {
    require_once('regatta/Regatta.php');
    return DB::getAll(($inc_private !== false) ? DB::$REGATTA : DB::$PUBLIC_REGATTA,
                      new DBBool(array(new DBCond('start_time', $this->start_date, DBCond::GE),
                                       new DBCond('start_time', $this->end_date,   DBCond::LT),
                                       new DBCondIn('id', DB::prepGetAll(
                                                      DB::$TEAM,
                                                      new DBCondIn('school', DB::prepGetAll(
                                                                     DB::$SCHOOL,
                                                                     new DBCond('conference', $conference),
                                                                     array('id'))),
                                                      array('regatta'))))));
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
  const GENERAL_404 = '404';
  const SCHOOL_404 = 'school404';
  const EULA = 'eula';
  const REGISTER_MESSAGE = 'register';

  /**
   * Fetches list of known sections
   *
   * @return Map
   */
  public static function getSections() {
    return array(self::ANNOUNCEMENTS => "Announcement",
                 self::REGISTER_MESSAGE => "Registration Message",
                 self::EULA => "EULA",
                 self::WELCOME => "Public Welcome",
                 self::GENERAL_404 => "404 Page",
                 self::SCHOOL_404 => "School 404 Page");
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
class Race_Order extends DBObject implements Countable {

  const FREQUENCY_FREQUENT = 'frequent';
  const FREQUENCY_INFREQUENT = 'infrequent';
  const FREQUENCY_NONE = 'none';

  public $num_teams;
  public $num_divisions;
  public $num_boats;
  public $frequency;
  public $description;
  protected $template;
  protected $author;
  protected $master_teams;

  public function db_type($field) {
    switch ($field) {
    case 'template':
    case 'master_teams':
      return array();
    case 'author':
      require_once('regatta/Account.php');
      return DB::$ACCOUNT;
    default:
      return parent::db_type($field);
    }
  }

  protected function db_order() {
    return array('num_divisions'=>true, 'num_teams'=>true, 'num_boats'=>true, 'master_teams' => true, 'frequency' => true);
  }

  public function getPair($index) {
    if ($this->template === null || $index < 0  || $index > count($this->__get('template')))
      return array(null, null);
    $pairings = $this->__get('template');
    return explode('-', $pairings[$index]);
  }

  public function count() {
    if ($this->template === null)
      return 0;
    return count($this->__get('template'));
  }

  public function setPairs(Array $pairs = array()) {
    $this->template = array();
    foreach ($pairs as $i => $pair) {
      if (!is_array($pair) || count($pair) != 2)
	throw new InvalidArgumentException("Invalid pair entry with index $i.");
      $this->template[] = implode('-', $pair);
    }
  }

  public static function getFrequencyTypes() {
    return array(self::FREQUENCY_FREQUENT => "Frequent rotation",
                 self::FREQUENCY_INFREQUENT => "Infrequent rotation",
                 self::FREQUENCY_NONE => "No rotation");
  }

  /**
   * Concatenation of num_divisions, num_teams, num_boats, and frequency
   *
   * These values ought to be globally unique per race order.
   *
   * @return String the hash
   */
  public function hash() {
    return sprintf('%s-%s-%s-%s', $this->num_divisions, $this->num_teams, $this->num_boats, $this->frequency);
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
  public $category;
  public $description;
  protected function db_cache() { return true; }
  protected function db_order() { return array('category'=>true, 'title'=>true); }
  public function __toString() { return $this->title; }

  /**
   * Gets the permission specified by the given ID
   *
   * @param Const $id the ID of the permission
   * @return Permission|null
   */
  public static function g($id) {
    return DB::get(DB::$PERMISSION, $id);
  }

  // List of permissions. There should be a corresponding entry in the
  // database with ID matching that of the constant below. Permissions
  // that don't exist in the database are implied reserved for super
  // admins.
  const EDIT_ANNOUNCEMENTS = 'edit_announcements';
  const EDIT_BOATS = 'edit_boats';
  const EDIT_EMAIL_TEMPLATES = 'edit_email_templates';
  const EDIT_MAILING_LISTS = 'edit_mailing_lists';
  const EDIT_ORGANIZATION = 'edit_organization';
  const EDIT_PERMISSIONS = 'edit_permissions';
  const EDIT_PUBLIC_FILES = 'edit_public_files';
  const EDIT_REGATTA_TYPES = 'edit_regatta_types';
  const EDIT_SEASONS = 'edit_seasons';
  const EDIT_SPONSORS = 'edit_sponsors';
  const EDIT_TR_TEMPLATES = 'edit_tr_templates';
  const EDIT_USERS = 'edit_users';
  const EDIT_VENUES = 'edit_venues';
  const EDIT_WELCOME = 'edit_welcome';
  const SEND_MESSAGE = 'send_message';
  const SYNC_DATABASE = 'sync_database';
  const DEFINE_PERMISSIONS = 'define_permission';

  const DOWNLOAD_AA_REPORT = 'download_aa_report';
  const EDIT_AA_REPORT = 'edit_aa_report';
  const USE_HEAD_TO_HEAD_REPORT = 'use_head_to_head_report';
  const USE_TEAM_RECORD_REPORT = 'use_team_record_report';
  const USE_MEMBERSHIP_REPORT = 'use_membership_report';
  const USE_BILLING_REPORT = 'use_billing_report';

  const EDIT_REGATTA = 'edit_regatta';
  const FINALIZE_REGATTA = 'finalize_regatta';
  const CREATE_REGATTA = 'create_regatta';
  const DELETE_REGATTA = 'delete_regatta';

  const EDIT_SCHOOL_LOGO = 'edit_school_logo';
  const EDIT_UNREGISTERED_SAILORS = 'edit_unregistered_sailors';
  const EDIT_TEAM_NAMES = 'edit_team_names';

  const EDIT_GLOBAL_CONF = 'edit_global_conf';
  const USURP_USER = 'usurp_user';

  public static function getPossible() {
    $reflection = new ReflectionClass(DB::$PERMISSION);
    return $reflection->getConstants();
  }
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
  public $has_all;
  public $is_default;
  protected function db_cache() { return true; }
  protected function db_order() { return array('title' => true); }
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
      if ($this->has_all)
	$this->permissions = DB::getAll(DB::$PERMISSION);
      else {
	$this->permissions = array();
	foreach (DB::getAll(DB::$ROLE_PERMISSION, new DBCond('role', $this)) as $link) {
	  $this->permissions[] = $link->permission;
	}
      }
    }
    return $this->permissions;
  }

  /**
   * Sets the list of permissions associated with this role
   *
   * @param Array:Permission $persm the list of permissions
   */
  public function setPermissions(Array $perms = array()) {
    DB::removeAll(DB::$ROLE_PERMISSION, new DBCond('role', $this));
    foreach ($perms as $perm) {
      $link = new Role_Permission();
      $link->role = $this;
      $link->permission = $perm;
      DB::set($link);
    }
    $this->permissions = $perms;
  }

  /**
   * Indicate that this role has all permissions
   *
   * This method will set the 'has_all' attribute, AND remove any
   * individual permissions associated with this role, if true
   *
   * @param boolean $flag true to set this role as having all
   */
  public function setHasAll($flag = true) {
    if ($flag !== false) {
      $this->has_all = 1;
      $this->setPermissions();
    }
    else {
      $this->has_all = null;
    }
  }

  /**
   * Get accounts that have this role
   *
   * @return Array:Account the account list
   */
  public function getAccounts() {
    require_once('regatta/Account.php');
    return DB::getAll(DB::$ACCOUNT, new DBCond('ts_role', $this));
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
  const APP_NAME = 'app_name';
  const APP_VERSION = 'app_version';
  const APP_COPYRIGHT = 'app_copyright';
  const TS_FROM_MAIL = 'ts_from_mail';
  const SAILOR_API_URL = 'sailor_api_url';
  const COACH_API_URL = 'coach_api_url';
  const SCHOOL_API_URL = 'school_api_url';
  const HELP_HOME = 'help_home';
  const DIVERT_MAIL = 'divert_mail';
  const SCORING_OPTIONS = 'scoring_options';
  const CONFERENCE_TITLE = 'conference_title';
  const CONFERENCE_SHORT = 'conference_short';
  const CONFERENCE_URL = 'conference_url';
  const ALLOW_CROSS_RP = 'allow_cross_rp';
  const PDFLATEX_SOCKET = 'pdflatex_socket';
  const LONG_SESSION_LIMIT = 'long_session_limit';
  const NOTICE_BOARD_SIZE = 'notice_board_size';

  const RP_SINGLEHANDED = 'rp-singlehanded';
  const RP_1_DIVISION = 'rp-1-division';
  const RP_2_DIVISION = 'rp-2-division';
  const RP_3_DIVISION = 'rp-3-division';
  const RP_4_DIVISION = 'rp-4-division';
  const RP_TEAM_RACE = 'rp-team-race';

  const TWITTER_URL_LENGTH = 'twitter_url_length';
  const SEND_MAIL = 'send_mail';
  const ALLOW_REGISTER = 'allow_register';
  const ORG_NAME = 'org_name';
  const ORG_URL = 'org_url';
  const ORG_TEAMS_URL = 'org_teams_url';

  const GCSE_ID = 'gcse_id';
  const GOOGLE_ANALYTICS = 'google_analytics';
  const GOOGLE_PLUS = 'google_plus';
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
  const PAYPAL_HOSTED_BUTTON_ID = 'paypal_hosted_button_id';

  const MAIL_REGISTER_USER = 'mail_register_user';
  const MAIL_REGISTER_ADMIN = 'mail_register_admin';
  const MAIL_APPROVED_USER = 'mail_approved_user';
  const MAIL_UNFINALIZED_REMINDER = 'mail_unfinalized_reminder';

  const DEFAULT_START_TIME = 'default_start_time';
  const ALLOW_HOST_VENUE = 'allow_host_venue';
  const PUBLISH_CONFERENCE_SUMMARY = 'publish_conference_summary';

  public $value;
  public function db_name() { return 'setting'; }
  protected function db_cache() { return true; }
  public function __toString() { return $this->value; }

  /**
   * Fetches default value (if none found in database)
   *
   * @param Const $name the setting
   * @return String|null the default value
   */
  public static function getDefault($name) {
    switch ($name) {
    case self::TS_FROM_MAIL:
      return Conf::$ADMIN_MAIL;

    case self::APP_NAME:
      return "Techscore";

    case self::APP_VERSION:
      return "3.3";

    case self::APP_COPYRIGHT:
      return "© OpenWeb Solutions, LLC 2008-2013";

    case self::SCORING_OPTIONS:
      return sprintf("%s\0%s\0%s", Regatta::SCORING_STANDARD, Regatta::SCORING_COMBINED, Regatta::SCORING_TEAM);

    case self::CONFERENCE_TITLE:
      return "Conference";

    case self::CONFERENCE_SHORT:
      return "Conf.";

    case self::CONFERENCE_URL:
      return 'conferences';

    case self::DEFAULT_START_TIME:
      return "10:00";

    case self::LONG_SESSION_LIMIT:
      return 3;

    case self::NOTICE_BOARD_SIZE:
      return 5242880; // 5MB

    default:
      return null;
    }
  }
}

/**
 * Cache of (local) URLs which have been serialized for a regatta
 *
 * @author Dayan Paez
 * @version 2013-10-02
 */
class Pub_Regatta_Url extends DBObject {
  protected $regatta;
  public $url;

  public function db_type($field) {
    if ($field == 'regatta')
      return DB::$FULL_REGATTA;
    return parent::db_type($field);
  }
}

/**
 * Skeletal public site (missing filedata)
 *
 * @author Dayan Paez
 * @version 2013-10-04
 */
class Pub_File_Summary extends DBObject implements Writeable {
  public $filetype;
  public $width;
  public $height;
  public function db_name() { return 'pub_file'; }
  protected function db_order() { return array('filetype'=>true, 'id'=>true); }

  public function getFile() {
    return DB::get(DB::$PUB_FILE, $this->id);
  }

  public function __toString() {
    return $this->id;
  }

  /**
   * Creates an XImg object with width/height attrs (if available)
   *
   * @param String $src the source to use
   * @param String $alt the alt text to use
   * @param Array $attrs optional list of other attributes
   */
  public function asImg($src, $alt, Array $attrs = array()) {
    $img = new XImg($src, $alt, $attrs);
    if ($this->width !== null) {
      $img->set('width', $this->width);
      $img->set('height', $this->height);
    }
    return $img;
  }

  public function write($resource) {
    $file = $this->getFile();
    fwrite($resource, $file->filedata);
  }
}

/**
 * Public site file
 *
 * @author Dayan Paez
 * @version 2013-10-04
 */
class Pub_File extends Pub_File_Summary {
  public $filedata;
  protected function db_cache() { return true; }
  public function getFile() {
    return $this;
  }
}

/**
 * A sponsor to be used on the public site
 *
 * @author Dayan Paez
 * @version 2013-10-31
 */
class Pub_Sponsor extends DBObject {
  public $name;
  public $url;
  public $relative_order;
  protected $logo;

  public function db_type($field) {
    if ($field == 'logo')
      return DB::$PUB_FILE_SUMMARY;
    return parent::db_type($field);
  }

  protected function db_order() { return array('relative_order'=>true); }
}

/**
 * Web-based session object
 *
 * @author Dayan Paez
 * @version 2013-10-29
 */
class Websession extends DBObject {
  public $sessiondata;
  protected $created;
  protected $last_modified;
  protected $expires;

  protected function db_order() { return array('last_modified'=>false); }
  protected function db_cache() { return true; }
  public function db_type($field) {
    switch ($field) {
    case 'created':
    case 'last_modified':
    case 'expires':
      return DB::$NOW;
    }
  }
}

/**
 * All information about a regatta document, except for the data.
 *
 * @author Dayan Paez
 * @version 2013-11-21
 */
class Document_Summary extends DBObject implements Writeable {
  public $name;
  public $description;
  public $url;
  public $filetype;
  public $relative_order;
  public $category;
  public $width;
  public $height;
  protected $regatta;
  protected $author;
  protected $last_updated;

  const CATEGORY_NOTICE = 'notice';
  const CATEGORY_PROTEST = 'protest';
  const CATEGORY_COURSE_FORMAT = 'course_format';

  public function db_name() { return 'regatta_document'; }
  protected function db_order() { return array('relative_order'=>true); }
  public function db_type($field) {
    switch ($field) {
    case 'regatta':
      require_once('regatta/Regatta.php');
      return DB::$REGATTA;
    case 'author':
      require_once('regatta/Account.php');
      return DB::$ACCOUNT;
    case 'last_updated':
      return DB::$NOW;
    default:
      return parent::db_type($field);
    }
  }

  public function getFile() {
    return DB::get(DB::$REGATTA_DOCUMENT, $this->id);
  }

  /**
   * Creates an XImg object with width/height attrs (if available)
   *
   * @param String $src the source to use
   * @param String $alt the alt text to use
   * @param Array $attrs optional list of other attributes
   */
  public function asImg($src, $alt, Array $attrs = array()) {
    $img = new XImg($src, $alt, $attrs);
    if ($this->width !== null) {
      $img->set('width', $this->width);
      $img->set('height', $this->height);
    }
    return $img;
  }

  public function write($resource) {
    $file = $this->getFile();
    fwrite($resource, $file->filedata);
  }

  public static function getCategories() {
    return array(self::CATEGORY_NOTICE => "General notice",
                 self::CATEGORY_PROTEST => "Protest",
                 self::CATEGORY_COURSE_FORMAT => "Course format",
    );
  }
}

/**
 * Full version of the document, includes the filedata
 *
 * @author Dayan Paez
 * @version 2013-11-21
 */
class Document extends Document_Summary {
  public $filedata;
  public function getFile() { return $this; }
}

/**
 * Linking table between documents and races
 *
 * @author Dayan Paez
 * @version 2014-04-26
 */
class Document_Race extends DBObject {
  protected $race;
  protected $document;

  public function db_name() { return 'regatta_document_race'; }
  public function db_type($field) {
    if ($field == 'race')
      return DB::$RACE;
    if ($field == 'document')
      return DB::$REGATTA_DOCUMENT_SUMMARY;
    return parent::db_type($field);
  }
}

/**
 * Log of every database sync process run
 *
 * @author Dayan Paez
 * @version 2014-05-31
 */
class Sync_Log extends DBObject {
  protected $started_at;
  protected $ended_at;
  protected $updated;
  protected $error;

  const SCHOOLS = 'schools';
  const SAILORS = 'sailors';
  const COACHES = 'coaches';

  public function db_type($field) {
    switch ($field) {
    case 'started_at':
    case 'ended_at':
      return DB::$NOW;
    case 'updated':
    case 'error':
      return array();
    default:
      return parent::db_type($field);
    }
  }

  protected function db_order() {
    return array('started_at' => false);
  }

  /**
   * Gets the schools added in this sync process
   *
   * @return Array:School
   */
  public function getSchools() {
    return DB::getAll(DB::$SCHOOL, new DBCond('sync_log', $this));
  }

  /**
   * Gets the sailors added in this sync process
   *
   * @return Array:Sailor
   */
  public function getSailors() {
    return DB::getAll(DB::$SAILOR, new DBCond('sync_log', $this));
  }

  /**
   * Gets the coaches added in this sync process
   *
   * @return Array:Coach
   */
  public function getCoaches() {
    return DB::getAll(DB::$COACH, new DBCond('sync_log', $this));
  }
}
?>
