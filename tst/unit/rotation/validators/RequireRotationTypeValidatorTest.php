<?php
use \model\FleetRotation;
use \rotation\validators\RequireRotationTypeValidator;

require_once(dirname(dirname(dirname(__FILE__))) . '/AbstractUnitTester.php');

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
  public function testInvalid() {
    $this->rotation->rotation_type = null;
    $this->testObject->validateFleetRotation($this->rotation);
  }

  public function testValid() {
    $this->rotation->rotation_type = FleetRotation::TYPE_NONE;
    $this->testObject->validateFleetRotation($this->rotation);
  }
}