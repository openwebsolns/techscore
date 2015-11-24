<?php
namespace rotation;

use \AbstractUnitTester;
use \InvalidArgumentException;
use \model\FleetRotation;

/**
 * Tests the chooser of sails rotator.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class AbstractFleetRotationCreatorTest extends AbstractUnitTester {

  public function testGetSailsRotator() {
    $testObject = new MockFleetRotationCreator();

    $rotation_type = FleetRotation::TYPE_NONE;
    $sails = array();
    $rotator = $testObject->getSailsRotator($rotation_type, $sails);
    $this->assertTrue($rotator instanceof ConstantSailsRotator);

    $rotation_type = FleetRotation::TYPE_STANDARD;
    $rotator = $testObject->getSailsRotator($rotation_type, $sails);
    $this->assertTrue($rotator instanceof StandardSailsRotator);

    $rotation_type = FleetRotation::TYPE_SWAP;
    $rotator = $testObject->getSailsRotator($rotation_type, $sails);
    $this->assertTrue($rotator instanceof SwapSailsRotator);
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testGetSailsRotatorInvalid() {
    $sails = array();
    $rotation_type = "unknown";

    $testObject = new MockFleetRotationCreator();
    $testObject->getSailsRotator($rotation_type, $sails);
  }

}

/**
 * Mock fleet rotation creator
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class MockFleetRotationCreator extends AbstractFleetRotationCreator {
  public function createRotation(FleetRotation $rotation) {
    throw new InvalidArgumentException("Not implemented.");
  }
}