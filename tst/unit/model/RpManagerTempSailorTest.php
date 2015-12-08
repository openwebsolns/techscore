<?php
namespace model;

use \AbstractUnitTester;
use \Attendee;
use \DB;
use \DBExpression;
use \DBM;
use \DBObject;
use \InvalidArgumentException;
use \Regatta;
use \RpManager;
use \Sailor;
use \School;

/**
 * Test some DB-backed methods of RpManager.
 *
 * @author Dayan Paez
 * @version 2015-12-08
 */
class RpManagerTempSailorTest extends AbstractUnitTester {

  private $regatta;
  private $testObject;

  protected function setUp() {
    DB::setDbm(new RpManagerTempSailorTestDBM());
    RpManagerTempSailorTestDBM::resetForTest();

    $this->regatta = new RpManagerTempSailorTestRegatta();
    $this->testObject = new RpManager($this->regatta);
  }

  public function testAddTempSailor() {
    $this->regatta->id = "RegattaId";

    $internal_id = "IgnoredId";
    $external_id = "IgnoredExternalId";
    $school = new School();

    $sailor = new Sailor();
    $sailor->id = $internal_id;
    $sailor->external_id = $external_id;
    $sailor->active = null;
    $sailor->school = $school;

    $this->testObject->addTempSailor($sailor);
    $this->assertNotEquals($internal_id, $sailor->id);
    $this->assertNotEquals($external_id, $sailor->external_id);
    $this->assertSame($school, $sailor->school);
    $this->assertEquals($this->regatta->id, $sailor->regatta_added);
    $this->assertNotNull($sailor->active);

    $calls = RpManagerTempSailorTestDBM::getSetCalls();
    $this->assertEquals(1, count($calls));
    $this->assertSame($sailor, $calls[0]['object']);
  }

  public function testRemoveTempSailor() {
    $this->regatta->id = "RegattaId";

    $sailor = new Sailor();
    $sailor->id = "Id";
    $sailor->external_id = null;
    $sailor->regatta_added = $this->regatta->id;

    $result = $this->testObject->removeTempSailor($sailor);
    $this->assertTrue($result);
    $calls = RpManagerTempSailorTestDBM::getRemoveCalls();
    $this->assertEquals(1, count($calls));
    $this->assertSame($sailor, $calls[0]['object']);
  }

  public function testRemoveTempSailorRegistered() {
    $this->regatta->id = "RegattaId";

    $sailor = new Sailor();
    $sailor->id = "Id";
    $sailor->external_id = "Registered";
    $sailor->regatta_added = $this->regatta->id;

    $result = $this->testObject->removeTempSailor($sailor);
    $this->assertFalse($result);
    $calls = RpManagerTempSailorTestDBM::getRemoveCalls();
    $this->assertEquals(0, count($calls));
  }

  public function testRemoveTempSailorDifferentRegatta() {
    $this->regatta->id = "RegattaId";

    $sailor = new Sailor();
    $sailor->id = "Id";
    $sailor->external_id = null;
    $sailor->regatta_added = "OtherRegattaId";

    $result = $this->testObject->removeTempSailor($sailor);
    $this->assertFalse($result);
    $calls = RpManagerTempSailorTestDBM::getRemoveCalls();
    $this->assertEquals(0, count($calls));
  }

  public function testRemoveTempSailorAttendance() {
    $this->regatta->id = "RegattaId";

    $sailor = new Sailor();
    $sailor->id = "Id";
    $sailor->external_id = null;
    $sailor->regatta_added = $this->regatta->id;

    $attendee = new Attendee();
    $attendee->sailor = $sailor;
    RpManagerTempSailorTestDBM::setAttendees(array($attendee));

    $result = $this->testObject->removeTempSailor($sailor);
    $this->assertFalse($result);
    $calls = RpManagerTempSailorTestDBM::getRemoveCalls();
    $this->assertEquals(0, count($calls));
  }

}

/**
 * Mock regatta.
 */
class RpManagerTempSailorTestRegatta extends Regatta {
}

/**
 * Mock DBM.
 */
class RpManagerTempSailorTestDBM extends DBM {

  private static $idCounter = 1;
  private static $setCalls = array();
  private static $removeCalls = array();
  private static $attendees = array();

  public static function resetForTest() {
    self::$setCalls = array();
    self::$removeCalls = array();
    self::$attendees = array();
  }

  public static function set(DBObject $obj, $update = 'guess') {
    $obj->id = self::$idCounter++;
    self::$setCalls[] = array(
      'object' => $obj,
      'update' => $update
    );
  }

  public static function getAll(DBObject $obj, DBExpression $where = null, $limit = null) {
    if ($obj instanceof Attendee) {
      return self::$attendees;
    }
    throw new InvalidArgumentException(
      sprintf("Did not expect ::getAll(%s)", get_class($obj))
    );
  }

  public static function remove(DBObject $obj) {
    self::$removeCalls[] = array('object' => $obj);
  }

  public static function setAttendees(Array $attendees) {
    self::$attendees = $attendees;
  }

  public static function getRemoveCalls() {
    return self::$removeCalls;
  }

  public static function getSetCalls() {
    return self::$setCalls;
  }

}