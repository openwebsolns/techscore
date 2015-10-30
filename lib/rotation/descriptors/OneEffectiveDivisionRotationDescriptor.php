<?php
namespace rotation\descriptors;

use \model\FleetRotation;

/**
 * Descriptor for combined-division or single-division regattas.
 *
 * @author Dayan Paez
 * @version 2015-10-30
 */
class OneEffectiveDivisionRotationDescriptor extends MultiDivisionStandardRotationDescriptor {

  public function describe(FleetRotation $rotation) {
    $description = ucfirst($this->describeType($rotation));

    $racesPhrase = $this->describeRacesPerSet($rotation);
    if ($racesPhrase !== null) {
      $description .= " " . $racesPhrase;
    }

    $description .= ".";
    return $description;
  }
}