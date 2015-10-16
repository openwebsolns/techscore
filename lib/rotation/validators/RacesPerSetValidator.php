<?php
namespace rotation\validators;

use \SoterException;
use \model\FleetRotation;

/**
 * Requires races per set unless "no rotation" is the type.
 *
 * @author Dayan Paez
 * @version 2015-10-20
 */
class RacesPerSetValidator implements FleetRotationValidator  {

  public function validateFleetRotation(FleetRotation $rotation) {
    if ($rotation->rotation_type != FleetRotation::TYPE_NONE) {
      if ($rotation->races_per_set == null) {
        throw new SoterException("Missing races per set.");
      }
    }
  }
}
