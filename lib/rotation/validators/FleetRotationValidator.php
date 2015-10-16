<?php
namespace rotation\validators;

use \model\FleetRotation;

/**
 * Validates a fleet rotation's parameters.
 *
 * @author Dayan Paez
 * @version 2015-10-20
 */
interface FleetRotationValidator  {

  /**
   * Validates that a given rotation object is well composed.
   *
   * @param FleetRotation $rotation the rotation to validate.
   * @throws SoterException if an error is encountered.
   */
  public function validateFleetRotation(FleetRotation $rotation);
}