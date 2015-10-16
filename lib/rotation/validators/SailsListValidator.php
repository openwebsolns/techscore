<?php
namespace rotation\validators;

use \Regatta;
use \SoterException;
use \model\FleetRotation;

/**
 * Validates that a sail list is provided and of the right size.
 *
 * @author Dayan Paez
 * @version 2015-10-20
 */
class SailsListValidator implements FleetRotationValidator  {

  public function validateFleetRotation(FleetRotation $rotation) {
    if ($rotation->sails_list == null) {
      throw new SoterException("Missing list of sails.");
    }

    $regatta = $rotation->regatta;
    $teams = $regatta->getTeams();
    if ($regatta->scoring == Regatta::SCORING_COMBINED) {
      $divisions = $regatta->getDivisions();
      if ($rotation->sails_list->count() < count($teams) * count($divisions)) {
        throw new SoterException("All the teams must be accounted for in combined division rotations.");
      }
    }
    elseif ($rotation->sails_list->count() < count($teams)) {
      throw new SoterException("All the teams must be accounted for.");
    }
  }
}
