<?php
/**
 * Sailor that can participate in a regatta.
 *
 * E.g.: a sailor whose status is not requested.
 *
 * @author Dayan Paez
 * @version 2015-12-12
 */
class AvailableSailor extends Sailor {

  public function db_where() {
    $myCond = new DBCond('register_status', self::STATUS_REQUESTED, DBCond::NE);
    $parentCond = parent::db_where();
    if ($parentCond !== null) {
      $myCond = new DBBool(array($parentCond, $myCond));
    }
    return $myCond;
  }

}