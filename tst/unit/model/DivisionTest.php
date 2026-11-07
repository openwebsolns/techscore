<?php
namespace model;

use \AbstractUnitTester;
use \Division;

/**
 * Tests the RotationTable creation.
 *
 * @author Dayan Paez
 * @version 2015-03-22
 */
class DivisionTest extends AbstractUnitTester {

  public function testListOfSize(): void {
    $divisions = Division::listOfSize(1);
    $this->assertEquals(1, count($divisions));
    $this->assertEquals(Division::A(), $divisions[0]);
  }

  public function testListOfSizeThree(): void {
    $divisions = Division::listOfSize(3);
    $this->assertEquals(3, count($divisions));
    $this->assertEquals(Division::A(), $divisions[0]);
    $this->assertEquals(Division::B(), $divisions[1]);
    $this->assertEquals(Division::C(), $divisions[2]);
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testInvalidListOfSize(): void {
    Division::listOfSize(5);
  }
}