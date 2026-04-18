<?php
namespace utils;

use \Account;
use \DB;
use \SoterException;

/**
 * Utility for context-aware searching/filtering of accounts.
 *
 * @author Dayan Paez
 * @version 2026-04-18
 * @see SailorSearcher
 */
class AccountSearcher {

  const FIELD_QUERY = 'q';
  const FIELD_TS_ROLE = 'ts_role';
  const FIELD_ROLE = 'role';
  const FIELD_STATUS = 'status';

  /**
   * @var String Search term?
   */
  private $query;
  /**
   * @var const ts_role to filter by (null possible).
   */
  private $ts_role;
  /**
   * @var const role to filter by (null possible).
   */
  private $role;
  /**
   * @var const status of account (null possible).
   */
  private $status;

  private function __construct() {
    $this->query = null;
    $this->ts_role = null;
    $this->role = null;
    $this->status = null;
  }

  /**
   * Performs the search based on settings.
   *
   * @param Account for jurisdiction
   * @return Array:Account
   */
  public function doSearch(Account $user) {
    if ($this->query === null) {
      return DB::getAccounts($this->role, $this->status, $this->ts_role, $user);
    }

    return DB::searchAccounts(
      $this->query,
      $this->role,
      $this->status,
      $this->ts_role,
      $user
    );
  }

  public function __get($field) {
    return $this->$field;
  }

  /**
   * Creates a new AccountSearcher from given GET/POST arguments.
   *
   * @param Array $args with request arguments
   * @return AccountSearcher a new searcher
   * @throws SoterException with invalid args
   */
  public static function fromArgs(Array $args): AccountSearcher {
    // Filter?
    $ts_role = DB::$V->incID($args, self::FIELD_TS_ROLE, DB::T(DB::ROLE));

    $roles = Account::getRoles();
    $role = DB::$V->incKey($args, self::FIELD_ROLE, $roles);

    $statuses = Account::getStatuses();
    $status = DB::$V->incKey($args, self::FIELD_STATUS, $statuses);

    $query = DB::$V->incString($args, self::FIELD_QUERY, 0, 256);
    if ($query !== null && strlen($query) < 3) {
      throw new SoterException("Search query is too short.");
    }

    $searcher = new AccountSearcher();
    $searcher->query = $query;
    $searcher->ts_role = $ts_role;
    $searcher->role = $role;
    $searcher->status = $status;

    return $searcher;
  }

  /**
   * Creates AccountSearcher with no filters.
   */
  public static function createDefault(): AccountSearcher {
    return new AccountSearcher();
  }
}
