<?php
namespace rotation\validators;

use \AbstractUnitTester;
use \model\FleetRotation;

class RequireRotationTypeValidatorTest extends AbstractUnitTester {

  private $testObject;
  private $rotation;

  protected function setUp() {
    $this->testObject = new RequireRotationTypeValidator();
    $this->rotation = new FleetRotation();
  }

  /**
   * @expectedException SoterException
   */
  public function testInvalid(): void {
    $this->rotation->rotation_type = null;
    $this->testObject->validateFleetRotation($this->rotation);
  }

  public function testValid(): void {
    $this->rotation->rotation_type = FleetRotation::TYPE_NONE;
    $this->testObject->validateFleetRotation($this->rotation);
  }
}