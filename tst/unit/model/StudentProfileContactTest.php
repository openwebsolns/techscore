<?php
namespace model;

use \AbstractUnitTester;
use \DB;
use \InvalidArgumentException;

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

  public function testValidation() {
    $obj = new StudentProfileContact();
    $obj->contact_type = null;
    $obj->contact_type = StudentProfileContact::CONTACT_TYPE_HOME;
    $obj->contact_type = StudentProfileContact::CONTACT_TYPE_SCHOOL;
    try {
      $obj->contact_type = "Foo";
      $this->assertTrue(false, "Expected exception here.");
    }
    catch (InvalidArgumentException $e) {
      // as expected
    }
  }

}