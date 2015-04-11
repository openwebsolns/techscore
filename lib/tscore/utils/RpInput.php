<?php
namespace tscore\utils;

use \InvalidArgumentException;

use \RP;
use \Race;
use \Sailor;
use \Team;

/**
 * An adaptor to RP to facilitate RP input validation.
 *
 * These objects are very similar to 'RP' objects in their interface
 * in that they have the following properties:
 *
 *   - sailor
 *   - team
 *   - boat_role
 *   - list of Race objects (in the same division)
 *
 * @author Dayan Paez
 * @version 2015-04-10
 */
class RpInput {
  private $races;
  private $team;
  private $sailor;
  private $boat_role;
  private $division;

  public function __get($name) {
    if (!property_exists($this, $name)) {
      throw new InvalidArgumentException("No such property $name.");
    }
    return $this->$name;
  }

  public function setRaces(Array $races = array()) {
    $this->division = null;
    $this->addRaces($races);
  }

  public function addRaces(Array $races = array()) {
    if ($this->races == null) {
      $this->races = array();
    }
    foreach ($races as $race) {
      if (!($race instanceof Race)) {
        throw new InvalidArgumentException("Expected list of Race objects.");
      }
      if ($this->division !== null && $this->division != $race->division) {
        throw new InvalidArgumentException("All races must be from the same division.");
      }
      $this->division = $race->division;
      $this->races[$race->number] = $race;
    }
  }

  public function setTeam(Team $team = null) {
    $this->team = $team;
  }

  public function setSailor(Sailor $sailor = null) {
    $this->sailor = $sailor;
  }

  public function setBoatRole($role = null) {
    if ($role != RP::SKIPPER && $role != RP::CREW) {
      throw new InvalidArgumentException("Invalid role provided: $role.");
    }
    $this->boat_role = $role;
  }

  /**
   * Concatenation of sailor-division-role.
   *
   * @return "unique" key to use in rpData hash.
   */
  public function hash() {
    if ($this->sailor === null || $this->division === null || $this->boat_role === null) {
      throw new InvalidArgumentException("Missing either sailor, division, or role.");
    }
    return sprintf('%s-%s-%s', $this->sailor->id, $this->division, $this->boat_role);
  }
}