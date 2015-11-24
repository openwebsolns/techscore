<?php
namespace data\finalize;

use \AbstractUnitTester;
use \InvalidArgumentException;
use \Regatta;

/**
 * Test the aggregated nature of this criterion.
 *
 * @author Dayan Paez
 * @version 2015-10-28
 */
class AggregatedFinalizeCriteriaTest extends AbstractUnitTester {

  public function testCanApplyTo() {
    $expectedStatuses = array(
      new FinalizeStatus(FinalizeStatus::VALID, "Message1"),
      new FinalizeStatus(FinalizeStatus::ERROR, "Message2"),
      new FinalizeStatus(FinalizeStatus::WARN, "Message3"),
    );

    $criterion1 = new AggregatedFinalizeCriteriaTestFinalizeCriterion1();
    $criterion2 = new AggregatedFinalizeCriteriaTestFinalizeCriterion2(array($expectedStatuses[0]));
    $criterion3 = new AggregatedFinalizeCriteriaTestFinalizeCriterion2(array($expectedStatuses[1], $expectedStatuses[2]));
    $criterion4 = new AggregatedFinalizeCriteriaTestFinalizeCriterion1();

    $testObject = new AggregatedFinalizeCriteria();
    $testObject->setCriteria(array($criterion1, $criterion2, $criterion3, $criterion4));

    $regatta = new Regatta();
    $this->assertTrue($testObject->canApplyTo($regatta));

    $statuses = $testObject->getFinalizeStatuses($regatta);
    $this->assertEquals($expectedStatuses, $statuses);
  }

}

class AggregatedFinalizeCriteriaTestFinalizeCriterion1 extends FinalizeCriterion {
  public function canApplyTo(Regatta $regatta) {
    return false;
  }
  public function getFinalizeStatuses(Regatta $regatta) {
    throw new InvalidArgumentException("Should never be called because it doesn't apply.");
  }
}

class AggregatedFinalizeCriteriaTestFinalizeCriterion2 extends FinalizeCriterion {

  private $statuses;

  public function __construct(Array $statuses) {
    $this->statuses = $statuses;
  }

  public function canApplyTo(Regatta $regatta) {
    return true;
  }
  public function getFinalizeStatuses(Regatta $regatta) {
    return $this->statuses;
  }
}
