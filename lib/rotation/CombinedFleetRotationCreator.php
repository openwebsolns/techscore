<?php
namespace rotation;

use \Division;
use \FullRegatta;
use \InvalidArgumentException;
use \Sail;
use \model\FleetRotation;

/**
 * Creates rotations for combined regattas.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class CombinedFleetRotationCreator extends AbstractFleetRotationCreator {

  public function createRotation(FleetRotation $rotation) {
    $regatta = $rotation->regatta;
    $manager = $regatta->getRotationManager();
    $divisions = $regatta->getDivisions();
    $teams = $regatta->getTeams();

    $raceNumbers = array();
    foreach ($regatta->getRaces(Division::A()) as $race) {
      $raceNumbers[] = $race->number;
    }
    $racesRotator = new SimilarStyleRacesRotator(array($raceNumbers));
    $sailsRotator = $this->getSailsRotator($rotation->rotation_type, $rotation->sails_list->getSailObjects());

    $manager->reset();
    $manager->initQueue();
    $raceNumbers = $racesRotator->nextRaces($rotation->races_per_set);
    $sails = $sailsRotator->rotate();
    while (count($raceNumbers) > 0) {
      foreach ($raceNumbers as $raceNumber) {
        $i = 0;
        foreach ($divisions as $division) {
          $race = $regatta->getRace($division, $raceNumber);
          foreach ($teams as $team) {
            $sail = new Sail();
            $sail->sail = $sails[$i]->sail;
            $sail->color = $sails[$i]->color;
            $sail->race = $race;
            $sail->team = $team;
            $manager->queue($sail);
            $i++;
          }
        }
      }

      $raceNumbers = $racesRotator->nextRaces($rotation->races_per_set);
      $sails = $sailsRotator->rotate();
    }
    $manager->commit();
  }
}