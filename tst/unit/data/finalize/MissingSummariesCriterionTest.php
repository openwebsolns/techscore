<?php
use \data\finalize\FinalizeStatus;
use \data\finalize\MissingSummariesCriterion;

require_once(dirname(dirname(dirname(__FILE__))) . '/AbstractUnitTester.php');

/**
 * Test the missing summaries criterion.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class MissingSummariesCriterionTest extends AbstractUnitTester {

  public function testGetMissingSummaries() {
    $regatta = new MissingSummariesCriterionRegatta();
    $regatta->summaries = array("Not-null", null);
    $testObject = new MissingSummariesCriterion();

    $this->assertTrue($testObject->canApplyTo($regatta));

    $list = $testObject->getFinalizeStatuses($regatta);
    $this->assertCount(1, $list);

    $status = $list[0];
    $this->assertEquals(FinalizeStatus::ERROR, $status->getType());
    $this->assertNotNull($status->getMessage());
  }

  public function testValidGetMissingSummaries() {
    $regatta = new MissingSummariesCriterionRegatta();
    $regatta->summaries = array("Not-null", "Not-null either");
    $testObject = new MissingSummariesCriterion();

    $this->assertTrue($testObject->canApplyTo($regatta));

    $list = $testObject->getFinalizeStatuses($regatta);
    $this->assertCount(1, $list);

    $status = $list[0];
    $this->assertEquals(FinalizeStatus::VALID, $status->getType());
    $this->assertNotNull($status->getMessage());
  }
}

/**
 * Mock regatta
 */
class MissingSummariesCriterionRegatta extends Regatta {
  public $start_time;
  public $summaries = array(null, null);
  public function __construct() {
    $this->start_time = new DateTime();
  }
  public function getDuration() {
    return 2;
  }
  public function getSummary(DateTime $day) {
    if ($day->format('Y-m-d') == $this->start_time->format('Y-m-d')) {
      return $this->summaries[0];
    }
    return $this->summaries[1];
  }
}