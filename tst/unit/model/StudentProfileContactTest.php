<?php
namespace model;

use \AbstractUnitTester;
use \DB;

/**
 * Basic goodness.
 *
 * @author Dayan Paez
 * @version 2016-03-31
 */
class StudentProfileContactTest extends AbstractUnitTester {

  public function testDeserialization() {
    $result = DB::getAll(new StudentProfileContact());
  }

}