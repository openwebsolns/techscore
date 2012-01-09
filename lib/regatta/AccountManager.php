<?php
/*
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
   * Returns the user with the specified id if the password matches,
   * or null otherwise. The user account status must be either
   * accepted or active.
   *
   * @param string $id the user id
   * @param string $pass the password in the system
   *
   * @return User|null the user object or null if invalid
   */
  public static function approveUser($id, $pass) {
    $q = sprintf('select password from account where id like "%s"' .
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
    $q = sprintf('update account set password = sha1("%s") where id = "%s"',
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
    $q = sprintf('insert into account (id, first_name, last_name, role, school, status, password) ' .
		 'values ("%s", "%2$s", "%3$s", "%4$s", "%5$s", "%6$s", "%7$s") on duplicate key update ' .
		 'first_name = "%2$s", last_name = "%3$s", role = "%4$s", school = "%5$s", status = "%6$s", password = "%7$s"',
		 $acc->id,
		 $acc->first_name,
		 $acc->last_name,
		 $acc->role,
		 $acc->school->id,
		 $acc->status,
		 $acc->password);
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

  // ROLE based

  /**
   * Fetches the different roles allowed by TechScore
   *
   * @return Array:String associative map of account role types
   */
  public static function getRoles() {
    return array('coach'=>"Coach",
		 'staff'=>"Staff",
		 'student'=>"Student");
  }

  /**
   * Returns a list of accounts fulfilling the given role
   *
   * @param String $role an index of getRoles
   * @return Array:Account the list of accounts
   * @throws InvalidArgumentException if provided role is invalid
   * @see getRoles
   */
  public static function getAccounts($role) {
    $roles = self::getRoles();
    if (!isset($roles[$role]))
      throw new InvalidArgumentException("Invalid role provided: $role.");

    $q = sprintf('select %s from %s where role = "%s"', Account::FIELDS, Account::TABLES, $role);
    $q = Preferences::query($q);
    $list = array();
    while ($obj = $q->fetch_object("Account"))
      $list[] = $obj;
    return $list;
  }
}
?>