<?php
namespace model;

use \AbstractUnitTester;
use \DB;

/**
 * Test the makeRange method.
 *
 * @author Dayan Paez
 * @version 2015-11-17
 */
class DBMakeRangeTest extends AbstractUnitTester {

  public function testMakeRange() {
    $expectations = array(
      '2-4' => array(3, 2, 4),
      '1,3,5-6' => array(1, 3, 5, 6),
    );
    foreach ($expectations as $expected => $input) {
      $result = DB::makeRange($input);
      $this->assertEquals($expected, $result);
    }
  }

}