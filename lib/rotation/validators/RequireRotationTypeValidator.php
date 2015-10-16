<?php
namespace rotation\validators;

use \SoterException;
use \model\FleetRotation;

/**
 * Validates a fleet rotation's parameters.
 *
 * @author Dayan Paez
 * @version 2015-10-20
 */
class RequireRotationTypeValidator implements FleetRotationValidator  {

  /**
   * Validates that a given rotation object is well composed.
   *
   * @param FleetRotation $rotation the rotation to validate.
   * @throws SoterException if an error is encountered.
   */
  public function validateFleetRotation(FleetRotation $rotation) {
    if ($rotation->rotation_type == null) {
      throw new SoterException("Missing rotation type.");
    }
  }
}
