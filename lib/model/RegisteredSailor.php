<?php
/**
 * Sailor whose status is registered.
 *
 * @author Dayan Paez
 * @version 2015-12-12
 */
class RegisteredSailor extends Sailor {

  public function db_where() {
    $myCond = new DBCond('register_status', self::STATUS_REGISTERED);
    $parentCond = parent::db_where();
    if ($parentCond !== null) {
      $myCond = new DBBool(array($parentCond, $myCond));
    }
    return $myCond;
  }

}