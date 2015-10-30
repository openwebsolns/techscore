<?php
use \model\FleetRotation;
use \rotation\descriptors\MultiDivisionStandardRotationDescriptor;

require_once(dirname(dirname(dirname(__FILE__))) . '/AbstractUnitTester.php');

/**
 * Tests all interesting combinations of the descriptor.
 *
 * @author Dayan Paez
 * @version 2015-10-30
 */
class MultiDivisionStandardRotationDescriptorTest extends AbstractUnitTester {

  private $testObject;
  private $rotation;

  protected function setUp() {
    $this->testObject = new MultiDivisionStandardRotationDescriptor();
    $this->rotation = new FleetRotation();
  }

  public function testAll() {
    $types = FleetRotation::types();
    $styles = FleetRotation::styles();
    $division_orders = array(
      array('A', 'B'),
      array('C', 'D', 'B', 'A'),
    );
    $races_per_sets = array(1, 2);

    foreach ($types as $type) {
      foreach ($styles as $style) {
        foreach ($division_orders as $division_order) {
          foreach ($races_per_sets as $races_per_set) {
            $this->rotation->rotation_type = $type;
            $this->rotation->rotation_style = $style;
            $this->rotation->division_order = $division_order;
            $this->rotation->races_per_set = $races_per_set;

            $result = $this->testObject->describe($this->rotation);
            /*
            printf(
              "%s, %s, %s, %s:\n\n%s\n\n",
              $type,
              $style,
              implode("", $division_order),
              $races_per_set,
              $result
            );
            */
            $this->assertNotNull($result);
          }
        }
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

  /**
   * @expectedException InvalidArgumentException
   */
  public function testInvalidStyle() {
    $this->rotation->rotation_type = FleetRotation::TYPE_STANDARD;
    $this->rotation->races_per_set = 2;
    $this->rotation->division_order = array('B', 'A');
    $this->rotation->rotation_style = "foo";
    $this->testObject->describe($this->rotation);
  }
}