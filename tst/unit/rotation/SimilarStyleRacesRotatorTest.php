<?php
namespace rotation;

use \AbstractUnitTester;

/**
 * Tests the rotator.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class SimilarStyleRacesRotatorTest extends AbstractUnitTester {

  public function testNextRaces() {
    $racesPerDivision = array(
      array('1A', '2A', '3A'),
      array('1B', '2B', '3B'),
    );

    $testObject = new SimilarStyleRacesRotator($racesPerDivision);
    $set = $testObject->nextRaces(2);
    $this->assertEquals(array('1A', '2A', '1B', '2B'), $set);
    $set = $testObject->nextRaces(2);
    $this->assertEquals(array('3A', '3B'), $set);
    $set = $testObject->nextRaces(2);
    $this->assertEmpty($set);
  }

}