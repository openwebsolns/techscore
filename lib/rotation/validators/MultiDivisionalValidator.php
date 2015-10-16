<?php
namespace rotation\validators;

use \SoterException;
use \model\FleetRotation;

/**
 * Validates race order and style for multi-division regatta.
 *
 * @author Dayan Paez
 * @version 2015-10-20
 */
class MultiDivisionalValidator implements FleetRotationValidator  {

  public function validateFleetRotation(FleetRotation $rotation) {
    if ($rotation->regatta->getEffectiveDivisionCount() > 1) {
      if ($rotation->rotation_style == null) {
        throw new SoterException("Missing rotation style.");
      }
      if ($rotation->division_order == null) {
        throw new SoterException("Missing order of divisions.");
      }
    }
  }
}
