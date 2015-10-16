<?php
namespace rotation;

use \model\FleetRotation;

/**
 * Contract for objects that create (fleet) rotations.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
interface FleetRotationCreator {

  /**
   * Creates a regatta's rotation based on FleetRotation parameters.
   *
   * @param FleetRotation $rotation fully prepared rotation
   *   parameter. The regatta property is used as a launching platform
   *   for other properties (like RotationManager, for instance).
   * @throws InvalidArgumentException if unable to create rotation due
   *   to parameters.
   */
  public function createRotation(FleetRotation $rotation);

}