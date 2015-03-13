<?php
/**
 * Collection of Techscore utilities that child test classes can
 * leverage while unit testing.
 *
 * @author Dayan Paez
 * @version 2015-03-12
 */
abstract class AbstractUnitTester extends PHPUnit_Framework_TestCase {

  protected static $USER;

  public static function setUpBeforeClass() {
    if (self::$USER === null) {
      $obj = DB::T(DB::ACCOUNT);
      $obj->db_set_order(array('ts_role'=>false));
      $users = DB::getAdmins();
      $obj->db_set_order();
      if (count($users) == 0) {
        throw new InvalidArgumentException("No super/admin user exists!");
      }
      self::$USER = $users[0];
    }
  }
}