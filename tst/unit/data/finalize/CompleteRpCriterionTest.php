<?php
namespace data\finalize;

use \AbstractUnitTester;
use \Regatta;

/**
 * Test functionality of complete RP criterion.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class CompleteRpCriterionTest extends AbstractUnitTester {

  public function testGetFinalizeStatuses() {
    $testObject = new CompleteRpCriterion();

    $regatta = new CompleteRpCriterionRegatta();

    // Valid
    $regatta->isRpComplete = true;

    $this->assertTrue($testObject->canApplyTo($regatta));
    $statuses = $testObject->getFinalizeStatuses($regatta);
    $this->assertCount(1, $statuses);

    $status = $statuses[0];
    $this->assertEquals(FinalizeStatus::VALID, $status->getType());
    $this->assertNotNull($status->getMessage());

    // Invalid
    $regatta->isRpComplete = false;

    $this->assertTrue($testObject->canApplyTo($regatta));
    $statuses = $testObject->getFinalizeStatuses($regatta);
    $this->assertCount(1, $statuses);

    $status = $statuses[0];
    $this->assertEquals(FinalizeStatus::WARN, $status->getType());
    $this->assertNotNull($status->getMessage());
  }
}

/**
 * Mock regatta
 */
class CompleteRpCriterionRegatta extends Regatta {
  public $isRpComplete = true;
  public function isRpComplete() {
    return $this->isRpComplete;
  }
}