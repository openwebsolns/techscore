<?php
use \model\FleetRotation;
use \rotation\validators\SwapSailsListCountValidator;

require_once(dirname(dirname(dirname(__FILE__))) . '/AbstractUnitTester.php');

class SwapSailsListCountValidatorTest extends AbstractUnitTester {

  private $testObject;
  private $rotation;

  protected function setUp() {
    $this->testObject = new SwapSailsListCountValidator();
    $this->rotation = new FleetRotation();
  }

  /**
   * @expectedException SoterException
   */
  public function testInvalid() {
    $this->rotation->rotation_type = FleetRotation::TYPE_SWAP;
    $this->rotation->sails_list = new SailsList();
    $this->rotation->sails_list->sails = array(1, 2, 3);
    $this->testObject->validateFleetRotation($this->rotation);
  }

  public function testValid() {
    $this->rotation->rotation_type = FleetRotation::TYPE_SWAP;
    $this->rotation->sails_list = new SailsList();
    $this->rotation->sails_list->sails = array(1, 2, 3, 4);
    $this->testObject->validateFleetRotation($this->rotation);
  }

  public function testNotApplicable() {
    $this->rotation->rotation_type = FleetRotation::TYPE_NONE;
    $this->rotation->sails_list = new SailsList();
    $this->rotation->sails_list->sails = array(1, 2, 3);
    $this->testObject->validateFleetRotation($this->rotation);
  }
}