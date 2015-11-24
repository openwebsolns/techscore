<?php
namespace model;

use \AbstractUnitTester;
use \DB;
use \SailsList;

/**
 * Test the goodness of the FleetRotation class.
 *
 * @author Dayan Paez
 * @version 2015-10-16
 */
class FleetRotationTest extends AbstractUnitTester {

  private static $DIVISION_ORDER = array('C', 'B', 'A');
  private static $ROTATION_TYPE = FleetRotation::TYPE_STANDARD;
  private static $ROTATION_STYLE = FleetRotation::STYLE_NAVY;
  private static $RACES_PER_SET = 2;
  private static $SAILS = array('1', '2', '3');
  private static $COLORS = array('#ff0000', '#00ff00', '#0000ff');

  public function testSerialization() {
    $regatta = $this->getRegatta();

    $testObject = new FleetRotation();
    $testObject->regatta = $regatta;
    $testObject->division_order = self::$DIVISION_ORDER;
    $testObject->rotation_type = self::$ROTATION_TYPE;
    $testObject->rotation_style = self::$ROTATION_STYLE;
    $testObject->races_per_set = self::$RACES_PER_SET;

    $sailsList = new SailsList();
    $sailsList->sails = self::$SAILS;
    $sailsList->colors = self::$COLORS;
    $testObject->sails_list = $sailsList;

    DB::set($testObject);

    // Assert
    $id = $testObject->id;
    $this->assertNotNull($id);

    $fromDb = DB::get(DB::T(DB::FLEET_ROTATION), $id);
    $this->assertNotNull($fromDb);
    $this->assertEquals($regatta->id, $fromDb->regatta->id);
    $this->assertEquals(self::$DIVISION_ORDER, $fromDb->division_order);
    $this->assertEquals(self::$ROTATION_TYPE, $fromDb->rotation_type);
    $this->assertEquals(self::$ROTATION_STYLE, $fromDb->rotation_style);
    $this->assertEquals(self::$RACES_PER_SET, $fromDb->races_per_set);

    $sailsListFromDb = $fromDb->sails_list;
    $this->assertNotNull($sailsListFromDb);
    $this->assertEquals(self::$SAILS, $sailsListFromDb->sails);
    $this->assertEquals(self::$COLORS, $sailsListFromDb->colors);

    // Also assert AbstractObject fields
    $this->assertNotNull($fromDb->created_on);
    $this->assertEquals(self::$USER->id, $fromDb->created_by);
  }
}