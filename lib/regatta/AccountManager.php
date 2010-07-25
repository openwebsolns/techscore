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

  private static $con;

  /**
   * Sends query. Returns result object
   *
   */
  private static function query($q) {
    if (self::$con === null)
      self::$con = Preferences::getConnection();
    
    $res = self::$con->query($q);
    if (!empty(self::$con->error))
      throw new BadFunctionCallException(sprintf("MySQL error (%s): %s", $q, self::$con->error));
    return $res;
  }

  /**
   * Returns the account with the given username
   *
   * @return Account the account with the given username, null if none
   * exist
   */
  public static function getAccount($id) {
    $q = sprintf('select %s from %s where username like "%s"',
		 Account::FIELDS, Account::TABLES, $id);
    $q = self::query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    return $q->fetch_object("Account");
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
    $q = self::query($q);
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
    $q = sprintf('select * from account where username like "%s" and password = sha1("%s")',
		 $id, $pass);
    $q = self::query($q);
    if ($q->num_rows == 0) {
      return null;
    }
    return new User($id);
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
    self::query($q);
  }
}
?>