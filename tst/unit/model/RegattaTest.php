<?php
namespace model;

use \AbstractUnitTester;
use \DateTime;
use \Regatta;

/**
 * Test generic regatta class functionality.
 *
 * @author Dayan Paez
 * @version 2015-03-26
 */
class RegattaTest extends AbstractUnitTester {

  /**
   * Test some summary functionality.
   *
   */
  public function testGetDayBasedOnTime() {
    $today = new DateTime();
    $tomorrow = new DateTime('tomorrow');
    $yesterday = new DateTime('yesterday');
    $dayAfter = new DateTime('2 days');
    $twoDaysLater = new DateTime('3 days');

    $reg = new Regatta();
    $reg->start_time = $today;
    $reg->end_date = $dayAfter;

    // Test day of and day before
    $this->assertEquals(1, $reg->getDayBasedOnTime($today));
    $this->assertEquals(1, $reg->getDayBasedOnTime($yesterday));

    // Test the other das and after the event
    $this->assertEquals(2, $reg->getDayBasedOnTime($tomorrow));
    $this->assertEquals(3, $reg->getDayBasedOnTime($dayAfter));
    $this->assertEquals(3, $reg->getDayBasedOnTime($twoDaysLater));
  }
}