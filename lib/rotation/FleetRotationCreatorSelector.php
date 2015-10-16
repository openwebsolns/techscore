<?php
namespace rotation;

use \Regatta;
use \model\FleetRotation;

/**
 * Helper to identify the right fleet rotation creator to use.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class FleetRotationCreatorSelector {

  /**
   * Select the best creator for the given rotation.
   *
   * @param FleetRotation $rotation a fully created rotation.
   * @return FleetRotationCreator the right creator.
   */
  public function selectRotationCreator(FleetRotation $rotation) {
    $regatta = $rotation->regatta;
    if ($regatta->scoring == Regatta::SCORING_COMBINED) {
      return new CombinedFleetRotationCreator();
    }
    if ($rotation->rotation_style == FleetRotation::STYLE_FRANNY) {
      return new FrannyFleetRotationCreator();
    }
    return new StandardFleetRotationCreator();
  }
}