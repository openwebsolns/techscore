<?php
namespace rotation\validators;

use \SoterException;
use \model\FleetRotation;

/**
 * Aggregates the errors across multiple validators.
 *
 * @author Dayan Paez
 * @version 2015-10-20
 */
class AggregatedFleetRotationValidator implements FleetRotationValidator  {

  /**
   * @var Array:FleetRotationValidator list of validators.
   */
  private $validators;

  public function validateFleetRotation(FleetRotation $rotation) {
    foreach ($this->getFleetRotationValidators() as $validator) {
      $validator->validateFleetRotation($rotation);
    }
  }

  /**
   * Auto-inject validators.
   */
  private function getFleetRotationValidators() {
    if ($this->validators == null) {
      $this->validators = array(
        new RequireRotationTypeValidator(),
        new MultiDivisionalValidator(),
        new RacesPerSetValidator(),
        new SailsListValidator(),
        new SwapSailsListCountValidator(),
      );
    }
    return $this->validators;
  }

  public function setFleetRotationValidators(Array $list) {
    $this->validators = $list;
  }
}
