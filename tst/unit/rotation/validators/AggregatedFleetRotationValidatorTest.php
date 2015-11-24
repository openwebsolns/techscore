<?php
namespace rotation\validators;

use \AbstractUnitTester;
use \SoterException;
use \model\FleetRotation;

class AggregatedFleetRotationValidatorTest extends AbstractUnitTester {

  public function testAggregation() {
    $rotation = new FleetRotation();

    $expectedErrorMessage = "SECOND";
    $otherErrorMessage = "OtherMessage";
    $validValidator = new AggregatedFleetRotationValidatorTestValid();

    $testObject = new AggregatedFleetRotationValidator();
    $testObject->setFleetRotationValidators(
      array(
        $validValidator,
        new AggregatedFleetRotationValidatorTestError($expectedErrorMessage),
        new AggregatedFleetRotationValidatorTestError($otherErrorMessage),
      )
    );
    try {
      $testObject->validateFleetRotation($rotation);
      $this->assertTrue(false, "Expected SoterException.");
    }
    catch (SoterException $e) {
      $this->assertEquals($expectedErrorMessage, $e->getMessage());
      $this->assertEquals(1, $validValidator->numberTimesCalled(), "First validator was not consulted.");
    }
  }

  /**
   * @expectedException SoterException
   */
  public function testAutoInjection() {
    $testObject = new AggregatedFleetRotationValidator();
    $rotation = new FleetRotation();
    $testObject->validateFleetRotation($rotation);
  }
}

class AggregatedFleetRotationValidatorTestValid implements FleetRotationValidator {

  private $called = 0;

  public function validateFleetRotation(FleetRotation $rotation) {
    $this->called++;
  }

  public function numberTimesCalled() {
    return $this->called;
  }
}

class AggregatedFleetRotationValidatorTestError implements FleetRotationValidator {

  private $errorMessage;

  public function __construct($message) {
    $this->errorMessage = $message;
  }
  public function validateFleetRotation(FleetRotation $rotation) {
    throw new SoterException($this->errorMessage);
  }
}