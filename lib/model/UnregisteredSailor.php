<?php
/**
 * Sailor whose status is unregistered.
 *
 * @author Dayan Paez
 * @version 2015-12-12
 */
class UnregisteredSailor extends Sailor {

  public function __construct() {
    parent::__construct();
    $this->register_status = self::STATUS_UNREGISTERED;
  }

  public function db_where() {
    $myCond = new DBCond('register_status', self::STATUS_UNREGISTERED);
    $parentCond = parent::db_where();
    if ($parentCond !== null) {
      $myCond = new DBBool(array($parentCond, $myCond));
    }
    return $myCond;
  }

}