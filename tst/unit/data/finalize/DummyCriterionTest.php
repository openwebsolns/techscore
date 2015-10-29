<?php
use \data\finalize\DummyCriterion;
use \data\finalize\FinalizeStatus;

require_once(dirname(dirname(__DIR__)) . '/AbstractUnitTester.php');

/**
 * The simplest of tests for the simplest of classes.
 *
 * @author Dayan Paez
 * @version 2015-10-28
 */
class DummyCriterionTest extends AbstractUnitTester {

  private $testObject;
  private $regatta;

  protected function setUp() {
    $this->testObject = new DummyCriterion();
    $this->regatta = new Regatta();
  }

  public function testCanApplyTo() {
    $this->assertTrue($this->testObject->canApplyTo($this->regatta));
  }

  public function testGetFinalizeStatuses() {
    $this->assertEmpty($this->testObject->getFinalizeStatuses($this->regatta));
  }
}