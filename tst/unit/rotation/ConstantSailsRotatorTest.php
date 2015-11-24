<?php
namespace rotation;

use \AbstractUnitTester;

/**
 * Test generic regatta class functionality.
 *
 * @author Dayan Paez
 * @version 2015-03-26
 */
class ConstantSailsRotatorTest extends AbstractUnitTester {

  public function testRotate() {
    $sails = array(1, 2, 3, 4, 5);
    $testObject = new ConstantSailsRotator($sails);

    $next = $testObject->rotate();
    $this->assertEquals($sails, $next);
    $next = $testObject->rotate();
    $this->assertEquals($sails, $next);
  }

}