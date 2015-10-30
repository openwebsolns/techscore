<?php
namespace rotation\descriptors;

use \model\FleetRotation;

/**
 * Delegates to a known strategy based on input.
 *
 * @author Dayan Paez
 * @version 2015-10-30
 */
class AggregatedRotationDescriptor implements RotationDescriptor {

  private $multiDivisionDescriptor;
  private $singleDivisionDescriptor;

  public function describe(FleetRotation $rotation) {
    $delegate = null;
    if ($rotation->regatta->getEffectiveDivisionCount() == 1) {
      $delegate = $this->getSingleDivisionDescriptor();
    }
    else {
      $delegate = $this->getMultiDivisionDescriptor();
    }
    return $delegate->describe($rotation);
  }

  public function setMultiDivisionDescriptor(RotationDescriptor $descriptor) {
    $this->multiDivisionDescriptor = $descriptor;
  }

  public function setSingleDivisionDescriptor(RotationDescriptor $descriptor) {
    $this->singleDivisionDescriptor = $descriptor;
  }

  private function getMultiDivisionDescriptor() {
    if ($this->multiDivisionDescriptor == null) {
      $this->multiDivisionDescriptor = new MultiDivisionStandardRotationDescriptor();
    }
    return $this->multiDivisionDescriptor;
  }

  private function getSingleDivisionDescriptor() {
    if ($this->singleDivisionDescriptor == null) {
      $this->singleDivisionDescriptor = new OneEffectiveDivisionRotationDescriptor();
    }
    return $this->singleDivisionDescriptor;
  }
}