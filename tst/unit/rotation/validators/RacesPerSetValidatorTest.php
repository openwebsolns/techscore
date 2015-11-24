<?php
namespace rotation\validators;

use \AbstractUnitTester;
use \model\FleetRotation;

class RacesPerSetValidatorTest extends AbstractUnitTester {

  private $testObject;
  private $rotation;

  protected function setUp() {
    $this->testObject = new RacesPerSetValidator();
    $this->rotation = new FleetRotation();
  }

  /**
   * @expectedException SoterException
   */
  public function testInvalid() {
    $this->rotation->rotation_type = FleetRotation::TYPE_STANDARD;
    $this->rotation->races_per_set = null;
    $this->testObject->validateFleetRotation($this->rotation);
  }

  public function testValid() {
    $this->rotation->rotation_type = FleetRotation::TYPE_STANDARD;
    $this->rotation->races_per_set = 2;
    $this->testObject->validateFleetRotation($this->rotation);
  }

  public function testNotApplicable() {
    $this->rotation->rotation_type = FleetRotation::TYPE_NONE;
    $this->rotation->races_per_set = null;
    $this->testObject->validateFleetRotation($this->rotation);
  }
}