<?php
namespace rotation;

use \ByeTeam;
use \Division;
use \InvalidArgumentException;
use \model\FleetRotation;

/**
 * Creates most fleet rotations for "standard" regattas.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class StandardFleetRotationCreator extends AbstractFleetRotationCreator {

  public function createRotation(FleetRotation $rotation) {
    $regatta = $rotation->regatta;
    $manager = $regatta->getRotationManager();
    $racesRotator = $this->getRacesRotator($rotation);
    $sailsRotator = $this->getSailsRotator($rotation->rotation_type, $rotation->sails_list->getSailObjects());

    // Work the magic!
    $manager->reset();
    $manager->initQueue();
    $this->queueRotation($rotation, $racesRotator, $sailsRotator, $rotation->races_per_set);
    $manager->commit();
  }

  public function getRacesRotator(FleetRotation $rotation) {

    $regatta = $rotation->regatta;
    $racesByDivision = array();
    foreach ($rotation->division_order as $divName) {
      $division = Division::get($divName);
      $races = array();
      foreach ($regatta->getRaces($division) as $race) {
        $races[] = $race;
      }
      $racesByDivision[] = $races;
    }

    switch ($rotation->rotation_style) {
    case FleetRotation::STYLE_SIMILAR:
      return new SimilarStyleRacesRotator($racesByDivision);
    case FleetRotation::STYLE_NAVY:
      return new NavyStyleRacesRotator($racesByDivision);
    default:
      throw new InvalidArgumentException(
        sprintf(
          "Unsuited for creating rotations of style \"%s\".",
          $rotation->rotation_style
        )
      );
    }
  }
}