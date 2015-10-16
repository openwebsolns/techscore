<?php
use \rotation\SwapSailsRotator;

require_once(dirname(dirname(__FILE__)) . '/AbstractUnitTester.php');

/**
 * Test generic regatta class functionality.
 *
 * @author Dayan Paez
 * @version 2015-03-26
 */
class SwapSailsRotatorTest extends AbstractUnitTester {

  /**
   * @expectedException InvalidArgumentException
   */
  public function testInvalidConstruction() {
    new SwapSailsRotator(array(1, 2, 3));
  }

  public function testRotate() {
    $sails = array(1, 2, 3, 4, 5, 6);
    $testObject = new SwapSailsRotator($sails);

    $next = $testObject->rotate();
    $this->assertEquals($sails, $next);
    $next = $testObject->rotate();
    $this->assertEquals(array(2, 1, 4, 3, 6, 5), $next);
    $next = $testObject->rotate();
    $this->assertEquals(array(3, 6, 5, 2, 1, 4), $next);
    $next = $testObject->rotate();
    $this->assertEquals(array(4, 5, 6, 1, 2, 3), $next);
    $next = $testObject->rotate();
    $this->assertEquals(array(5, 4, 1, 6, 3, 2), $next);
    $next = $testObject->rotate();
    $this->assertEquals(array(6, 3, 2, 5, 4, 1), $next);
    $next = $testObject->rotate();
    $this->assertEquals(array(1, 2, 3, 4, 5, 6), $next);
  }

}