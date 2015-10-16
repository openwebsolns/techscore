<?php
use \model\FleetRotation;
use \rotation\validators\MultiDivisionalValidator;

require_once(dirname(dirname(dirname(__FILE__))) . '/AbstractUnitTester.php');

class MultiDivisionalValidatorTest extends AbstractUnitTester {

  private $testObject;
  private $rotation;

  protected function setUp() {
    $this->testObject = new MultiDivisionalValidator();
    $this->rotation = new FleetRotation();
    $this->rotation->regatta = new MultiDivisionalValidatorTestRegatta(2);
  }

  /**
   * @expectedException SoterException
   */
  public function testMissingStyle() {
    $this->rotation->rotation_style = null;
    $this->rotation->division_order = array('A', 'B');
    $this->testObject->validateFleetRotation($this->rotation);
  }

  /**
   * @expectedException SoterException
   */
  public function testMissingDivisionOrder() {
    $this->rotation->rotation_style = FleetRotation::STYLE_SIMILAR;
    $this->rotation->division_order = null;
    $this->testObject->validateFleetRotation($this->rotation);
  }

  public function testValid() {
    $this->rotation->rotation_style = FleetRotation::STYLE_SIMILAR;
    $this->rotation->division_order = array('A', 'B');
    $this->testObject->validateFleetRotation($this->rotation);
  }

  public function testInapplicable() {
    $this->rotation->rotation_style = null;
    $this->rotation->division_order = null;
    $this->rotation->regatta = new MultiDivisionalValidatorTestRegatta(1);
    $this->testObject->validateFleetRotation($this->rotation);
  }
}

class MultiDivisionalValidatorTestRegatta extends Regatta {

  private $numDivisions;

  public function __construct($numDivisions) {
    $this->numDivisions = $numDivisions;
  }

  public function getEffectiveDivisionCount() {
    return $this->numDivisions;
  }
}