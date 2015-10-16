<?php
namespace rotation\validators;

use \SoterException;
use \model\FleetRotation;

/**
 * Requires even number of sails for swap rotations.
 *
 * @author Dayan Paez
 * @version 2015-10-20
 */
class SwapSailsListCountValidator implements FleetRotationValidator  {

  public function validateFleetRotation(FleetRotation $rotation) {
    if ($rotation->rotation_type == FleetRotation::TYPE_SWAP) {
      if ($rotation->sails_list->count() % 2 != 0) {
        throw new SoterException("Swap rotations require an even number of sails.");
      }
    }
  }
}
