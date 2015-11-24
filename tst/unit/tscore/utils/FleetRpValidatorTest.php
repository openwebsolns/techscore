<?php
namespace tscore\utils;

use \AbstractUnitTester;
use \Division;
use \FullRegatta;
use \Team;

/**
 * Tests the FleetRpValidator functionality.
 *
 * @author Dayan Paez
 * @version 2015-04-11
 */
class FleetRpValidatorTest extends AbstractUnitTester {

  private $validator;

  protected function setUp() {
    $this->validator = new FleetRpValidator(new TestRegatta());
  }

  /**
   * @expectedException SoterException
   * @expectedExceptionMessage Missing RP data.
   */
  public function testMungedInput() {
    $input = array(
      'rp' => 'foo'
    );
    $this->validator->validate($input, new Team());
  }

  /**
   * @expectedException SoterException
   * @expectedExceptionMessage Missing RP data for A division.
   */
  public function testMungedInputDivision() {
    $input = array(
      'rp' => array(
        'DivisionA' => array(), // invalid
        'B' => array(),
      ),
    );
    $this->validator->validate($input, new Team());
  }
}

/**
 * Regatta used in testing.
 *
 * @author Dayan Paez
 * @version thedate
 */
class TestRegatta extends FullRegatta {
    public function isFleetRacing() { return true; }
    public function getDivisions() {
      return array(Division::A(), Division::B());
    }
}
