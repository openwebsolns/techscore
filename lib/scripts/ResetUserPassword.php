<?php
namespace scripts;

use \DB;
use \TSScriptException;

/**
 * Updates the password for a given account.
 *
 * Ideally, and usually, users should update their own passwords. This script
 * is provided mostly to facilitate local development, when importing databases
 * with different password salts, which would cause logins to fail.
 *
 * @author Dayan Paez
 * @created 2025-03-04
 */
class ResetUserPassword extends AbstractScript {

  public function __construct() {
    parent::__construct();
    $this->cli_opts = '<email> <passwd>';
    $this->cli_usage = ' <email>   Email of user to update
 <passwd>  New password';
  }

  /**
   * Update password for user with given ID.
   *
   * @param String $email account email to update
   * @param String $passwd the new password
   */
  public function run($email, $passwd) {
    $account = DB::getAccountByEmail($email);
    if ($account === null) {
      throw new TSScriptException(sprintf("No account found with ID '%s'", $email));
    }

    $account->password = DB::createPasswordHash($account, $passwd);
    DB::set($account);
    self::errln("UPDATED account password");
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);

    // Validate inputs
    if (count($opts) !== 2) {
      throw new TSScriptException("Email and password are required");
    }

    $email = $opts[0];
    $password = $opts[1];
    $this->run($email, $password);
  }
}
