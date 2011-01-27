<?php
/**
 * This file is part of TechScore
 *
 * @package users
 */

/**
 * Takes care of dealing with user accounts in a static fashion
 *
 * @author Dayan Paez
 * @version 2010-04-20
 */
class AccountManager {

  /**
   * Returns the account with the given username
   *
   * @return Account the account with the given username, null if none
   * exist
   */
  public static function getAccount($id) {
    $q = sprintf('select %s from %s where username like "%s"',
		 Account::FIELDS, Account::TABLES, $id);
    $q = Preferences::query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    return $q->fetch_object("Account");
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
   * @return Array<Account>
   * @throws InvalidArgumentException if one of the parameters is wrong
   */
  public static function getPendingUsers($start = null, $end = null) {
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

    // Setup the query
    $q = sprintf('select %s from %s where status = "pending" %s',
		 Account::FIELDS, Account::TABLES, $limit);
    $q = Preferences::query($q);
    $list = array();
    while ($obj = $q->fetch_object("Account"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Returns just the number of total pending users
   *
   * @return int the total number of pending users
   * @see getPendingUsers
   */
  public static function getNumPendingUsers() {
    $q = Preferences::query('select username from account where status = "pending"');
    return $q->num_rows;
  }

  /**
   * Returns the unique MD5 hash for the given account
   *
   * @param Account $acc the account to hash
   * @return String the hash
   * @see getAccountFromHash
   */
  public static function getHash(Account $acc) {
    return md5($acc->last_name.$acc->username.$acc->first_name);
  }

  /**
   * Fetches the account which has the hash provided. This hash is
   * calculated as an MD5 sum of last name, username, and first name
   *
   * @param String $hash the hash
   * @return Account|null the matching account or null if none match
   */
  public static function getAccountFromHash($hash) {
    $q = sprintf('select %s from %s where md5(concat(last_name, username, first_name)) like "%s"',
		 Account::FIELDS, Account::TABLES, $hash);
    $q = Preferences::query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    return $q->fetch_object("Account");
  }

  /**
   * Returns the user with the specified id if the password matches,
   * or null otherwise. The user account status must be either
   * accepted or active.
   *
   * @param string $id the user id
   * @param string $pass the password in the system
   *
   * @return User the user object
   * @return null if invalid userid or password
   */
  public static function approveUser($id, $pass) {
    $q = sprintf('select password from account where username like "%s"' .
		 '  and status in ("accepted", "active")',
		 $id);
    $q = Preferences::query($q);
    if ($q->num_rows == 0)
      return null;
    $r = $q->fetch_object();
    if ($r->password == sha1($pass))
      return new User($id);
    return null;
  }

  /**
   * Resets the password for the given user
   *
   * @param User $user the user whose password to reset
   * @param String $new_pass the password to set it to.
   */
  public static function resetPassword(User $user, $new_pass) {
    $q = sprintf('update account set password = sha1("%s") where username = "%s"',
		 addslashes($new_pass), $user->username());
    Preferences::query($q);
  }

  /**
   * Adds the given account object to the database, REPLACING whatever
   * is there by the same username. It is imperative that client code
   * check that the username does not already exist!
   *
   * @param Account $acc the account to set/update/add
   * @throws BadFunctionCallException associated with the database
   * @see getAccount
   */
  public static function setAccount(Account $acc) {
    $q = sprintf('replace into account (first_name, last_name, username, role, school, status) ' .
		 'values ("%s", "%s", "%s", "%s", "%s", "%s")',
		 $acc->first_name,
		 $acc->last_name,
		 $acc->username,
		 $acc->role,
		 $acc->school->id,
		 $acc->status);
    Preferences::query($q);
  }

  /**
   * Checks that the account holder is active. Otherwise, redirect to
   * license. Otherwise, redirect out
   *
   * @param User $user the user to check
   * @throws InvalidArgumentException if invalid parameter
   */
  public static function requireActive(User $user) {
    switch ($user->get(User::STATUS)) {
    case "active":
      return;

    case "accepted":
      WebServer::go("license");

    default:
      WebServer::go('/');
    }
  }
}
?>