<?php
namespace users\utils;

use \Account;
use \DB;
use \SoterException;

/**
 * Centralized processing of new accounts.
 *
 * @author Dayan Paez
 * @version 2016-04-01
 */
class RegisterAccountHelper {

  const FIELD_EMAIL = 'email';
  const FIELD_FIRST_NAME = 'first_name';
  const FIELD_LAST_NAME = 'last_name';
  const FIELD_PASSWORD = 'passwd';
  const FIELD_PASSWORD_CONFIRM = 'confirm';

  public function process(Array $args) {
    $acc = new Account();
    $acc->status = Account::STAT_REQUESTED;
    $acc->email = DB::$V->reqEmail($args, self::FIELD_EMAIL, "Invalid email provided.");
    $acc->last_name  = DB::$V->reqString($args, self::FIELD_LAST_NAME, 1, 31, "Last name must not be empty and less than 30 characters.");
    $acc->first_name = DB::$V->reqString($args, self::FIELD_FIRST_NAME, 1, 31, "First name must not be empty and less than 30 characters.");

    $pw1 = DB::$V->reqRaw($args, self::FIELD_PASSWORD, 8, 101, "Invalid password. Must be at least 8 characters long.");
    $pw2 = DB::$V->reqRaw($args, self::FIELD_PASSWORD_CONFIRM, 8, 101, "Invalid password confirmation.");
    if ($pw1 !== $pw2) {
      throw new SoterException("Password confirmation does not match. Please try again.");
    }
    $acc->password = DB::createPasswordHash($acc, $pw1);

    return $acc;
  }

}