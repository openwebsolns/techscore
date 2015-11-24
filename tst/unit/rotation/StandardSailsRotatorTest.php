<?php
namespace rotation;

use \AbstractUnitTester;

/**
 * Test generic regatta class functionality.
 *
 * @author Dayan Paez
 * @version 2015-03-26
 */
class StandardSailsRotatorTest extends AbstractUnitTester {

  public function testRotate() {
    $sails = array(1, 2, 3, 4, 5);
    $testObject = new StandardSailsRotator($sails);

    $next = $testObject->rotate();
    $this->assertEquals($sails, $next);
    $next = $testObject->rotate();
    $this->assertEquals(array(2, 3, 4, 5, 1), $next);
    $next = $testObject->rotate();
    $this->assertEquals(array(3, 4, 5, 1, 2), $next);
    $next = $testObject->rotate();
    $this->assertEquals(array(4, 5, 1, 2, 3), $next);
    $next = $testObject->rotate();
    $this->assertEquals(array(5, 1, 2, 3, 4), $next);
    $next = $testObject->rotate();
    $this->assertEquals(array(1, 2, 3, 4, 5), $next);
  }

}