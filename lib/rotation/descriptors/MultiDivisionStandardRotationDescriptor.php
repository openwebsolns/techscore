<?php
namespace rotation\descriptors;

use \InvalidArgumentException;
use \model\FleetRotation;

/**
 * Descriptor for standard regattas with more than one division.
 *
 * @author Dayan Paez
 * @version 2015-10-30
 */
class MultiDivisionStandardRotationDescriptor implements RotationDescriptor {

  private $rotationTypeDescriptors;

  /**
   * Generates string of the form:
   *
   * INPUT:
   *
   *   - Type: standard
   *   - Style: navy
   *   - Order: B, A
   *   - RacesPerSet: 2
   *
   * OUTPUT:
   *
   * "Rotate into the next boat in the sequence after 2 races, and
   * when switching divisions. Division B starts, followed by Division A."
   *
   * @param FleetRotation $rotation the object to describe.
   * @return String the description.
   */
  public function describe(FleetRotation $rotation) {
    $description = ucfirst($this->describeType($rotation));

    $racesPhrase = $this->describeRacesPerSet($rotation);
    if ($racesPhrase !== null) {
      $description .= " " . $racesPhrase;
    }

    $stylePhrase = $this->describeStyle($rotation);
    if ($stylePhrase !== null) {
      $description .= ", " . $stylePhrase;
    }
    $description .= ".";

    $orderPhrase = $this->describeDivisionOrder($rotation);
    if ($orderPhrase !== null) {
      $description .= " " . ucfirst($orderPhrase) . ".";
    }

    return $description;
  }

  protected function describeType(FleetRotation $rotation) {
    $descriptors = $this->getRotationTypeDescriptors();
    if (!array_key_exists($rotation->rotation_type, $descriptors)) {
      throw new InvalidArgumentException("Unknown rotation type: " . $rotation->rotation_type);
    }
    return $descriptors[$rotation->rotation_type];
  }

  protected function describeStyle(FleetRotation $rotation) {
    if ($rotation->rotation_style == FleetRotation::STYLE_FRANNY) {
      return "with starting sails offset for subsequent divisions";
    }
    if ($rotation->rotation_type == FleetRotation::TYPE_NONE) {
      return null;
    }
    if ($rotation->rotation_style == FleetRotation::STYLE_NAVY) {
      return "and when switching divisions";
    }
    if ($rotation->rotation_style == FleetRotation::STYLE_SIMILAR) {
      return "for all divisions";
    }
    throw new InvalidArgumentException("Unknown rotation style: " . $rotation->rotation_style);
  }

  protected function describeDivisionOrder(FleetRotation $rotation) {
    if ($rotation->rotation_style == FleetRotation::STYLE_SIMILAR) {
      return null;
    }
    if ($rotation->rotation_type == FleetRotation::TYPE_NONE
        && $rotation->rotation_style != FleetRotation::STYLE_FRANNY) {
      return null;
    }

    $inAlphabeticalOrder = true;
    $order = $rotation->division_order;
    for ($i = 0; $i < count($order) -1 ; $i++) {
      if (strcmp($order[$i], $order[$i + 1]) >= 0) {
        $inAlphabeticalOrder = false;
        break;
      }
    }

    if ($inAlphabeticalOrder) {
      return null;
    }

    $description = sprintf(
      "division %s starts, followed by division %s",
      $order[0],
      $order[1]
    );
    for ($i = 2; $i < count($order) -1; $i++) {
      $description .= sprintf(", then division %s", $order[$i]);
    }
    $count = count($order);
    if ($count > 2) {
      $description .= sprintf(", and finally division %s", $order[$count - 1]);
    }

    return $description;
  }

  protected function describeRacesPerSet(FleetRotation $rotation) {
    if ($rotation->rotation_type == FleetRotation::TYPE_NONE) {
      return null;
    }
    if ($rotation->races_per_set == 1) {
      return "after every race";
    }
    return sprintf("every %d races", $rotation->races_per_set);
  }

  private function getRotationTypeDescriptors() {
    if ($this->rotationTypeDescriptors === null) {
      $this->rotationTypeDescriptors = array(
        FleetRotation::TYPE_STANDARD => "rotate into the next boat in the sequence",
        FleetRotation::TYPE_SWAP => "odd-numbered teams rotate into next boat while even-numbered teams rotate into previous boat (swap)",
        FleetRotation::TYPE_NONE => "remain in initially assigned boat throughout",
      );
    }
    return $this->rotationTypeDescriptors;
  }
}