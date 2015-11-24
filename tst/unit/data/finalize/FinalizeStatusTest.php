<?php
namespace data\finalize;

use \AbstractUnitTester;
use \DateTime;

/**
 * Test setters in FinalizeStatus.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class FinalizeStatusTest extends AbstractUnitTester {

  /**
   * @expectedException InvalidArgumentException
   */
  public function testInvalidType() {
    new FinalizeStatus("foo");
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testInvalidMessage() {
    new FinalizeStatus(FinalizeStatus::VALID, new DateTime());
  }
}