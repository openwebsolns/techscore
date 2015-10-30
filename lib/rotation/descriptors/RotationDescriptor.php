<?php
namespace rotation\descriptors;

use \model\FleetRotation;

/**
 * Interface for describing a rotation in words.
 *
 * @author Dayan Paez
 * @version 2015-10-30
 */
interface RotationDescriptor {

  /**
   * Returns a human-readable representation of the given rotation.
   *
   * @param FleetRotation $rotation the object to describe.
   * @return String the description.
   */
  public function describe(FleetRotation $rotation);

}