<?php
/**
 * This file is part of TechScore
 *
 * @package users
 */

/**
 * Takes care of dealing with user accounts
 *
 * @author Dayan Paez
 * @version 2010-04-20
 */
class AccountManager {

  private $con;

  public function __construct() {
    $this->con = Preferences::getConnection();
  }

  /**
   * Sends query. Returns result object
   *
   */
  private function query($q) {
    $res = $this->con->query($q);
    if (!empty($this->con->error))
      throw new BadFunctionCallException("MySQL error: " . $this->con->error);
    return $res;
  }
}
?>