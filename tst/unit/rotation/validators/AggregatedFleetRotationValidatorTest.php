<?php
use \model\FleetRotation;
use \rotation\validators\AggregatedFleetRotationValidator;
use \rotation\validators\FleetRotationValidator;

require_once(dirname(dirname(dirname(__FILE__))) . '/AbstractUnitTester.php');

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