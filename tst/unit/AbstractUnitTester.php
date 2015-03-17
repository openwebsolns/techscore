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

  /**
   * Regattas created during testing.
   */
  protected static $REGATTAS = array();

  /**
   * Has the cleanup function been registered?
   *
   * @see setSession
   * @see cleanup
   */
  private static $isCleanupRegistered = false;

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

    if (!self::$isCleanupRegistered) {
      register_shutdown_function('AbstractUnitTester::cleanup');
      self::$isCleanupRegistered = true;
    }
  }

  /**
   * Removes regattas created during testing.
   *
   */
  public static function cleanup() {
    foreach (self::$REGATTAS as $regatta) {
      DB::remove($regatta);
    }
  }

  /**
   * Create test regatta.
   *
   */
  protected function getRegatta($scoring = Regatta::SCORING_STANDARD) {
    if (!array_key_exists($scoring, self::$REGATTAS)) {
      $seasons = DB::getAll(DB::T(DB::SEASON));
      if (count($seasons) == 0) {
        throw new InvalidArgumentException("No seasons available for regatta creation!");
      }
      $season = $seasons[count($seasons) - 1];
      $end = clone($season->start_date);
      $end->add(new DateInterval('P2DT0H'));

      $types = DB::getAll(DB::T(DB::ACTIVE_TYPE));
      if (count($types) == 0) {
        throw new InvalidArgumentException("No regatta types available for regatta creation!");
      }
      $type = $types[0];

      $reg = new Regatta();
      $reg->name = 'UnitTest-' . $scoring;
      $reg->start_time = $season->start_date;
      $reg->end_date = $end;
      $reg->private = 1; // CRITICAL
      $reg->participant = Regatta::PARTICIPANT_COED;
      $reg->scoring = $scoring;
      $reg->type = $type;
      
      DB::set($reg);
      self::$REGATTAS[$scoring] = $reg;
    }
    return self::$REGATTAS[$scoring];
  }
}