<?php
namespace rotation\descriptors;

use \AbstractUnitTester;
use \model\FleetRotation;

/**
 * Tests all interesting combinations of the descriptor.
 *
 * @author Dayan Paez
 * @version 2015-10-30
 */
class OneEffectiveDivisionRotationDescriptorTest extends AbstractUnitTester {

  private $testObject;
  private $rotation;

  protected function setUp() {
    $this->testObject = new OneEffectiveDivisionRotationDescriptor();
    $this->rotation = new FleetRotation();
  }

  public function testAll() {
    $types = FleetRotation::types();
    $races_per_sets = array(1, 2);

    foreach ($types as $type) {
      foreach ($races_per_sets as $races_per_set) {
        $this->rotation->rotation_type = $type;
        $this->rotation->races_per_set = $races_per_set;

        $result = $this->testObject->describe($this->rotation);

        /*
        printf(
          "%s, %s:\n\n%s\n\n",
          $type,
          $races_per_set,
          $result
        );
        */

        $this->assertNotNull($result);
      }
    }
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testInvalidType() {
    $this->rotation->rotation_type = "foo";
    $this->testObject->describe($this->rotation);
  }
}