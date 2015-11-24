<?php
namespace rotation\descriptors;

use \AbstractUnitTester;
use \Regatta;
use \model\FleetRotation;

/**
 * Test the auto-injection and logic of the aggregator.
 *
 * @author Dayan Paez
 * @version 2015-10-30
 */
class AggregatedRotationDescriptorTest extends AbstractUnitTester {

  private $testObject;
  private $rotation;

  protected function setUp() {
    $this->testObject = new AggregatedRotationDescriptor();
    $this->rotation = new FleetRotation();
  }

  public function testSingleSelection() {
    $this->rotation->regatta = new AggregatedRotationDescriptorTestRegatta(1);

    $descriptor = new AggregatedRotationDescriptorTestRotationDescriptor();
    $this->testObject->setSingleDivisionDescriptor($descriptor);

    $this->testObject->describe($this->rotation);
    $this->assertEquals(1, $descriptor->getTimesCalled());
  }

  public function testMultiSelection() {
    $this->rotation->regatta = new AggregatedRotationDescriptorTestRegatta(2);

    $descriptor = new AggregatedRotationDescriptorTestRotationDescriptor();
    $this->testObject->setMultiDivisionDescriptor($descriptor);

    $this->testObject->describe($this->rotation);
    $this->assertEquals(1, $descriptor->getTimesCalled());
  }

  public function testAutoInjection() {
    $divisionCounts = array(1, 2);
    $types = FleetRotation::types();
    $styles = FleetRotation::styles();
    $division_orders = array(
      array('A', 'B'),
      array('C', 'B', 'A'),
    );
    $races_per_sets = array(1, 2);

    foreach ($divisionCounts as $divisionCount) {
      $this->rotation->regatta = new AggregatedRotationDescriptorTestRegatta($divisionCount);

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
  }
}

/**
 * Mock regatta for testing.
 */
class AggregatedRotationDescriptorTestRegatta extends Regatta {

  private $divisionCount;

  public function __construct($count) {
    $this->divisionCount = $count;
  }

  public function getEffectiveDivisionCount() {
    return $this->divisionCount;
  }
}

/**
 * Mock delegate to verify selection.
 */
class AggregatedRotationDescriptorTestRotationDescriptor implements RotationDescriptor {

  private $timesCalled = 0;

  public function describe(FleetRotation $rotation) {
    $this->timesCalled++;
    return "Hello, World";
  }

  public function getTimesCalled() {
    return $this->timesCalled;
  }
}