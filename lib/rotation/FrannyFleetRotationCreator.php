<?php
namespace rotation;

use \Division;
use \FullRegatta;
use \InvalidArgumentException;
use \model\FleetRotation;

/**
 * Creates "Franny-style" rotations (only available for standard regattas).
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class FrannyFleetRotationCreator extends AbstractFleetRotationCreator {

  /**
   * The strategy is to use the chosen rotation type on the first
   * division, and then offset that to every other division.
   *
   */
  public function createRotation(FleetRotation $rotation) {
    $regatta = $rotation->regatta;
    $manager = $regatta->getRotationManager();
    $divisionNames = $rotation->division_order;
    $sails = $rotation->sails_list->getSailObjects();
    $offset = floor(count($sails) / count($divisionNames));

    $firstDivision = Division::get(array_shift($divisionNames));
    $sailsRotator = $this->getSailsRotator($rotation->rotation_type, $sails);
    $racesRotator = new SimilarStyleRacesRotator($this->getRacesInDivision($regatta, $firstDivision));

    $manager->reset();
    $manager->initQueue();
    $this->queueRotation($rotation, $racesRotator, $sailsRotator);

    foreach ($divisionNames as $divisionName) {
      $division = Division::get($divisionName);
      $sails = $this->cycleSails($sails, $offset);
      $sailsRotator = $this->getSailsRotator($rotation->rotation_type, $sails);
      $racesRotator = new SimilarStyleRacesRotator($this->getRacesInDivision($regatta, $division));
      $this->queueRotation($rotation, $racesRotator, $sailsRotator);
    }
    $manager->commit();
  }

  private function getRacesInDivision(FullRegatta $regatta, Division $div) {
    $races = array();
    foreach ($regatta->getRaces($div) as $race) {
      $races[] = $race;
    }
    return array($races);
  }

  private function cycleSails(Array $sails, $offset) {
    $firstChunk = array();
    for ($i = 0; $i < $offset; $i++) {
      $firstChunk[] = $sails[$i];
    }
    $i = 0;
    for (; $i < count($sails) - $offset; $i++) {
      $sails[$i] = $sails[$i + $offset];
    }
    for (; $i < count($sails); $i++) {
      $sails[$i] = array_shift($firstChunk);
    }
    return $sails;
  }
}