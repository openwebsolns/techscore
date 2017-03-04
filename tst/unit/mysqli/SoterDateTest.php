<?php

class SoterTest extends AbstractUnitTester {

  private $testObject;

  protected function setUp() {
    $this->testObject = new Soter();
  }

  /**
   * @expectedException SoterException
   */
  public function testReqDateInvalid() {
    $this->testObject->reqDate(array('07-29-1996'), 0);
  }

  /**
   * @expectedException SoterException
   */
  public function testReqDateMissing() {
    $this->testObject->reqDate(array('2007-11-12'), 1);
  }

  /**
   * @expectedException SoterException
   */
  public function testReqDateMinimum() {
    $this->testObject->reqDate(
      array('2007-11-12'),
      0,
      new DateTime('2007-11-13')
    );
  }

  /**
   * @expectedException SoterException
   */
  public function testReqDateMaximum() {
    $this->testObject->reqDate(
      array('2007-11-12'),
      0,
      null,
      new DateTime('2007-11-11')
    );
  }

  public function testReqDateValid() {
    $expected = '2007-11-12';
    $date = $this->testObject->reqDate(
      array($expected),
      0
    );
    $this->assertEquals($expected, $date->format('Y-m-d'));
  }

  public function testIncDate() {
    $expected = new DateTime();
    $date = $this->testObject->incDate(
      array(),
      0,
      null,
      null,
      $expected
    );

    $this->assertSame($expected, $date);
  }
}