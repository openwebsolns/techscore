<?php
use \data\finalize\FinalizeStatus;
use \data\finalize\UnsailedMiddleRacesCriterion;

require_once(dirname(dirname(dirname(__FILE__))) . '/AbstractUnitTester.php');

/**
 * Test the unsailed middle races criterion.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class UnsailedMiddleRacesCriterionTest extends AbstractUnitTester {

  public function testNoMissingRaces() {
    $race1 = new Race(); $race1->division = Division::A(); $race1->number = 1;
    $race2 = new Race(); $race2->division = Division::A(); $race2->number = 2;
    $race3 = new Race(); $race3->division = Division::A(); $race3->number = 3;

    $regatta = new UnsailedMiddleRacesCriterionRegatta();
    $regatta->scoredRaces = array($race1, $race2, $race3);
    $testObject = new UnsailedMiddleRacesCriterion();

    $this->assertTrue($testObject->canApplyTo($regatta));

    $list = $testObject->getFinalizeStatuses($regatta);
    $this->assertCount(1, $list);

    $status = $list[0];
    $this->assertEquals(FinalizeStatus::VALID, $status->getType());
    $this->assertNotNull($status->getMessage());
  }

  public function testIsMissingRaces() {
    $race1 = new Race(); $race1->division = Division::A(); $race1->number = 1;
    $race3 = new Race(); $race3->division = Division::A(); $race3->number = 3;

    $regatta = new UnsailedMiddleRacesCriterionRegatta();
    $regatta->scoredRaces = array($race1, $race3);
    $testObject = new UnsailedMiddleRacesCriterion();

    $this->assertTrue($testObject->canApplyTo($regatta));

    $list = $testObject->getFinalizeStatuses($regatta);
    $this->assertCount(1, $list);

    $status = $list[0];
    $this->assertEquals(FinalizeStatus::ERROR, $status->getType());
    $this->assertNotNull($status->getMessage());
  }
}

/**
 * Mock regatta
 */
class UnsailedMiddleRacesCriterionRegatta extends Regatta {
  public $scoring = Regatta::SCORING_STANDARD;

  public $scoredRaces = array();

  public function getDivisions() {
    return array(Division::A());
  }

  public function getScoredRaces(Division $division = null) {
    return $this->scoredRaces;
  }

  public function getRace(Division $division, $number) {
    return $this->scoredRaces[$number - 1];
  }
}