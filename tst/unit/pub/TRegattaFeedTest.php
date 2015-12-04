<?php
namespace pub;

use \AbstractUnitTester;
use \FullRegatta;
use \Regatta;
use \DB;
use \DBM;
use \DBObject;
use \DBExpression;
use \DateTime;

/**
 * Test the regatta feed; it could be cool.
 *
 * @author Dayan Paez
 * @version 2015-12-05
 */
class TRegattaFeedTest extends AbstractUnitTester {

  protected function setUp() {
    DB::setDbm(new TRegattaFeedTestDBM());
    TRegattaFeedTestDBM::resetForTest();
  }

  public function test() {
    $currentRegatta = new TRegattaFeedTestRegatta();
    $currentRegatta->dt_status = Regatta::STAT_FINAL;
    $currentRegatta->start_time = new DateTime('yesterday');
    $currentRegatta->end_date = new DateTime('today');
    $currentRegatta->finalized = new DateTime('today');
    $currentRegatta->nick = 'current-regatta';

    TRegattaFeedTestDBM::setRegattas(
      array(
        $currentRegatta,
      )
    );

    $testObject = new TRegattaFeed();
  }
}

/**
 * Mock DBM.
 */
class TRegattaFeedTestDBM extends DBM {

  private static $regattas;

  public static function resetForTest() {
    self::setRegattas(array());
  }

  public static function setRegattas(Array $regattas) {
    self::$regattas = $regattas;
  }

  public static function getAll(DBObject $obj, DBExpression $cond = null, $limit = null) {
    if ($obj instanceof FullRegatta) {
      return self::$regattas;
    }
    return DBM::getAll($obj, $cond, $limit);
  }
}

/**
 * Mock regatta.
 */
class TRegattaFeedTestRegatta extends Regatta {

  private static $counter = 1;

  public function __construct() {
    $this->id = self::$counter++;
  }

  const DATA_SCORING = 'DataScoring';
  const HOST_VENUE = 'HostVenue';

  public function getDataScoring() {
    return self::DATA_SCORING;
  }

  public function getHostVenue() {
    return self::HOST_VENUE;
  }
}