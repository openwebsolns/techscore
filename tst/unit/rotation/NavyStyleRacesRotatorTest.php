<?php
use \rotation\NavyStyleRacesRotator;

require_once(dirname(dirname(__FILE__)) . '/AbstractUnitTester.php');

/**
 * Tests the rotator.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class NavyStyleRacesRotatorTest extends AbstractUnitTester {

  public function testNextRaces() {
    $racesPerDivision = array(
      array('1A', '2A', '3A'),
      array('1B', '2B', '3B'),
    );

    $testObject = new NavyStyleRacesRotator($racesPerDivision);
    $set = $testObject->nextRaces(2);
    $this->assertEquals(array('1A', '2A'), $set);

    $set = $testObject->nextRaces(2);
    $this->assertEquals(array('1B', '2B'), $set);

    $set = $testObject->nextRaces(2);
    $this->assertEquals(array('3A'), $set);

    $set = $testObject->nextRaces(2);
    $this->assertEquals(array('3B'), $set);

    $set = $testObject->nextRaces(2);
    $this->assertEmpty($set);
  }

}