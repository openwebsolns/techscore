<?php
use \model\FleetRotation;
use \rotation\validators\RacesPerSetValidator;

require_once(dirname(dirname(dirname(__FILE__))) . '/AbstractUnitTester.php');

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