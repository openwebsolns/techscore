<?php
use \data\finalize\FinalizeStatus;
use \data\finalize\Pr24Criterion;

require_once(dirname(dirname(dirname(__FILE__))) . '/AbstractUnitTester.php');

/**
 * Tests the PR24 finalize criterion.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class Pr24CriterionTest extends AbstractUnitTester {

  public function testCanApply() {
    $testObject = new Pr24Criterion();

    $regatta = new Pr24CriterionRegatta();
    $regatta->scoring = Regatta::SCORING_COMBINED;
    $this->assertFalse($testObject->canApplyTo($regatta));

    $regatta->scoring = Regatta::SCORING_STANDARD;
    $regatta->divisions = array(Division::A());
    $this->assertFalse($testObject->canApplyTo($regatta));

    $regatta->divisions = array(Division::A(), Division::B());
    $this->assertTrue($testObject->canApplyTo($regatta));
  }

  public function testSameDay() {
    $testObject = new Pr24Criterion();

    $regatta = new Pr24CriterionRegatta();
    $regatta->duration = 1;
    $regatta->scoring = Regatta::SCORING_STANDARD;
    $regatta->divisions = array(Division::A(), Division::B());

    // Valid
    $regatta->scoredRaces = array(
      'A' => array(new Race(), new Race(), new Race()),
      'B' => array(new Race(), new Race(), new Race()),
    );

    $statuses = $testObject->getFinalizeStatuses($regatta);
    $this->assertCount(1, $statuses);

    $status = $statuses[0];
    $this->assertEquals(FinalizeStatus::VALID, $status->getType());
    $this->assertNull($status->getMessage());

    // Invalid
    $regatta->scoredRaces = array(
      'A' => array(new Race(), new Race(), new Race()),
      'B' => array(new Race(), new Race()),
    );

    $statuses = $testObject->getFinalizeStatuses($regatta);
    $this->assertCount(1, $statuses);

    $status = $statuses[0];
    $this->assertEquals(FinalizeStatus::ERROR, $status->getType());
    $this->assertNotNull($status->getMessage());
  }

  public function testMultiDay() {
    $testObject = new Pr24Criterion();

    $regatta = new Pr24CriterionRegatta();
    $regatta->duration = 2;
    $regatta->scoring = Regatta::SCORING_STANDARD;
    $regatta->divisions = array(Division::A(), Division::B());

    // Valid
    $regatta->scoredRaces = array(
      'A' => array(new Race(), new Race()),
      'B' => array(new Race(), new Race(), new Race(), new Race()),
    );

    $statuses = $testObject->getFinalizeStatuses($regatta);
    $this->assertCount(1, $statuses);

    $status = $statuses[0];
    $this->assertEquals(FinalizeStatus::VALID, $status->getType());
    $this->assertNull($status->getMessage());

    // Invalid
    $regatta->scoredRaces = array(
      'A' => array(new Race(), new Race()),
      'B' => array(new Race(), new Race(), new Race(), new Race(), new Race()),
    );

    $statuses = $testObject->getFinalizeStatuses($regatta);
    $this->assertCount(1, $statuses);

    $status = $statuses[0];
    $this->assertEquals(FinalizeStatus::ERROR, $status->getType());
    $this->assertNotNull($status->getMessage());
  }
}

/**
 * Mock regatta
 */
class Pr24CriterionRegatta extends Regatta {
  public $duration;
  public $divisions = array();
  public $scoredRaces = array();

  public function getDuration() {
    return $this->duration;
  }

  public function getDivisions() {
    return $this->divisions;
  }

  public function getScoredRaces(Division $division = null) {
    return $this->scoredRaces[(string)$division];
  }
}