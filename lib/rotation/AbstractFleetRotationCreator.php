<?php
namespace rotation;

use \InvalidArgumentException;
use \ByeTeam;
use \Sail;
use \model\FleetRotation;

/**
 * Parent class to encapsulate convenience methods.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
abstract class AbstractFleetRotationCreator implements FleetRotationCreator {

  /**
   * Returns the SailsRotator compatible with given rotation.
   *
   * @param Array the list of sails to create SailsRotator.
   * @param String $rotation_type type of rotation.
   * @return SailsRotator the appropriate rotator.
   * @throws InvalidArgumentException if unknown rotation type.
   */
  public function getSailsRotator($rotation_type, $sails) {
    switch ($rotation_type) {
    case FleetRotation::TYPE_NONE:
      return new ConstantSailsRotator($sails);
    case FleetRotation::TYPE_STANDARD:
      return new StandardSailsRotator($sails);
    case FleetRotation::TYPE_SWAP:
      return new SwapSailsRotator($sails);
    default:
      throw new InvalidArgumentException(
        sprintf(
          "Unsuited for creating rotations of type \"%s\".",
          $rotation_type
        )
      );
    }
  }

  protected function queueRotation(
    FleetRotation $rotation,
    RacesRotator $racesRotator,
    SailsRotator $sailsRotator
  ) {
    $regatta = $rotation->regatta;
    $manager = $regatta->getRotationManager();
    $teams = $regatta->getTeams();
    $races = $racesRotator->nextRaces($rotation->races_per_set);
    $sails = $sailsRotator->rotate();

    while (count($races) > 0) {
      foreach ($races as $race) {
        foreach ($sails as $i => $templateSail) {
          $team = ($i < count($teams)) ? $teams[$i] : new ByeTeam();
          $sail = new Sail();
          $sail->sail = $templateSail->sail;
          $sail->color = $templateSail->color;
          $sail->race = $race;
          $sail->team = $team;
          $manager->queue($sail);
        }
      }

      $races = $racesRotator->nextRaces($rotation->races_per_set);
      $sails = $sailsRotator->rotate();
    }
  }
}