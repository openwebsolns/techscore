<?php
namespace tscore\utils;

use \InvalidArgumentException;
use \SoterException;

use \DB;
use \FullRegatta;
use \RP;
use \Team;
use \Sailor;
use \STN;
use \School;

/**
 * Validates input submission for fleet regattas.
 *
 * @author Dayan Paez
 * @version 2015-04-10
 */
class FleetRpValidator {

  /**
   * Sailor "ID" used to indicate "no-show".
   */
  const NO_SHOW_ID = 'NULL';

  /**
   * Singleton instance of no show sailor.
   *
   * @see getNoShowSailor
   */
  private $noShowSailor;

  /**
   * @var FullRegatta the regatta in question.
   */  
  private $regatta;

  /**
   * @var Array cache of schools by ID. Lazy-loaded on-demand.
   */
  private $schoolsById;

  /**
   * @var Array list of sailors indexed by ID; reset by validation.
   */
  private $allSailors;

  /**
   * @var Array list of RpInputs; reset by validation. To handle the
   * case where the same sailor entry is spread out across multiple
   * entries, this list is indexed by the RpInput's hash.
   */
  private $rpData;

  public function __construct(FullRegatta $regatta) {
    $this->regatta = $regatta;
    if (!$regatta->isFleetRacing()) {
      throw new InvalidArgumentException("Only fleet racing regattas allowed.");
    }
  }

  /**
   * Call after 'validate' to fetch sailors, including reserves.
   *
   * @return Array:Sailor
   */
  public function getSailors() {
    if ($this->allSailors === null) {
      return null;
    }
    return array_values($this->allSailors);
  }

  /**
   * Return list of raw RpInputs.
   *
   * @return Array:RpInput.
   */
  public function getRpInputs() {
    if ($this->rpData === null) {
      return null;
    }
    return array_values($this->rpData);
  }

  /**
   * Processes (and validates) POST-like arguments in $args.
   *
   * @param Array $args the user input to validate.
   * @param Team $team the team whose RP we are validating.
   * @throws SoterException.
   */
  public function validate(Array $args, Team $team) {
    $this->allSailors = array();
    $this->rpData = array();

    $divisions = $this->regatta->getDivisions();

    // flags to validate sailor input
    $gender = ($this->regatta->participant == FullRegatta::PARTICIPANT_WOMEN) ?
      Sailor::FEMALE : null;
    $cross_rp = !$this->regatta->isSingleHanded() && DB::g(STN::ALLOW_CROSS_RP);

    // cache of races indexed by division and number, for efficiency.
    $racesPerDivision = array();

    // to validate "room in boat", maintain the number of positions
    // available indexed by division, race number and role.
    $spotsAvailable = array();
    foreach ($divisions as $division) {
      $key = (string)$division;
      $racesPerDivision[$key] = array();
      $spotsAvailable[$key] = array();

      foreach ($this->regatta->getRaces($division) as $race) {
        $racesPerDivision[$key][$race->number] = $race;
        $spotsPerDivision[$key][$race->number] = array(
          RP::SKIPPER => 1,
          RP::CREW => $race->boat->getNumCrews()
        );
      }
    }

    // to validate for multipresence: same sailor in same race cannot
    // be involved in two different roles
    $rolesBySailorAndRace = array();

    $rpForm = DB::$V->reqList($args, 'rp', count($divisions), "Missing RP data.");
    foreach ($divisions as $division) {

      $divisionForm = DB::$V->reqList(
        $rpForm, (string)$division, null,
        sprintf("Missing RP data for %s division.", $division));

      // allow missing skipper/crew in arguments
      foreach (array(RP::SKIPPER, RP::CREW) as $role) {

        $sailorsForm = DB::$V->incList($divisionForm, $role);
        foreach ($sailorsForm as $i => $entry) {
          $sailorID = DB::$V->incString($entry, 'sailor', 1);
          $racesString = DB::$V->incString($entry, 'races', 1);

          if ($sailorID == null || $racesString == null) {
            continue;
          }

          // special case: no show
          if ($sailorID == self::NO_SHOW_ID) {
            $sailor = $this->getNoShowSailor();
          }
          else {
            $sailor = $this->validateSailor($sailorID, $team->school, $gender, $cross_rp);
            if (!array_key_exists($sailor->id, $rolesBySailorAndRace)) {
              $rolesBySailorAndRace[$sailor->id] = array();
            }
          }

          $raceNums = DB::parseRange($racesString);
          if (count($raceNums) == 0) {
            throw new SoterException(
              sprintf("Invalid races for %s in %s division at position %d.", $role, $division, ($i + 1))
            );
          }

          $races = array();
          foreach ($raceNums as $number) {
            if (!array_key_exists($number, $racesPerDivision[(string)$division])) {
              throw new SoterException(
                sprintf("Invalid race number (%s) provided in %s division.", $number, $division)
              );
            }
            $race = $racesPerDivision[(string)$division][$number];

            // any room?
            if ($spotsPerDivision[(string)$division][$number][$role] <= 0) {
              throw new SoterException(sprintf("No room in race %s for %s.", $race, $sailor));
            }
            $spotsPerDivision[(string)$division][$number][$role]--;

            // multipresence?
            if (array_key_exists($sailor->id, $rolesBySailorAndRace)) {
              $key = (string)$race;
              if (array_key_exists($key, $rolesBySailorAndRace[$sailor->id])
                  && $rolesBySailorAndRace[$sailor->id][$key] != $role) {
                throw new SoterException(
                  sprintf(
                    "Sailor %s cannot sail in multiple roles in the same race (%s).",
                    $sailor,
                    $race
                  )
                );
              }
              $rolesBySailorAndRace[$sailor->id][$key] = $role;
            }

            $races[] = $race;
          }

          $rpInput = new RpInput();
          $rpInput->setSailor($sailor);
          $rpInput->setTeam($team);
          $rpInput->setBoatRole($role);
          $rpInput->setRaces($races);

          // do we need to merge this entry with existing one?
          $hash = $rpInput->hash();
          if (array_key_exists($hash, $this->rpData)) {
            $this->rpData[$hash]->addRaces($rpInput->races);
          }
          else {
            $this->rpData[$hash] = $rpInput;
            if ($sailor->id != self::NO_SHOW_ID) {
              $this->allSailors[$sailor->id] = $sailor;
            }
          }
        }
      }
    }

    // also include reserves
    foreach (DB::$V->incList($args, 'reserves') as $id) {
      $sailor = $this->validateSailor($id, $team->school, $gender, $cross_rp);
      $this->allSailors[$sailor->id] = $sailor;
    }
  }

  /**
   * Helper method to fetch a sailor with given ID, given restrictions.
   *
   * @param String $id the ID of the sailor to fetch.
   * @param School $school the school for the sailor.
   * @param String $gender null to ignore, or one to restrict.
   * @param boolean $cross_rp true to allow sailors from other teams.
   * @return Sailor
   * @throws SoterException
   */
  private function validateSailor($id, School $school, $gender = null, $cross_rp = false) {
    $sailor = DB::getSailor($id);
    if ($sailor === null) {
      throw new SoterException(
        sprintf("Invalid sailor provided: %s.", $id)
      );
    }
    if ($gender !== null && $gender != $sailor->gender) {
      throw new SoterException(sprintf("Sailor not allowed in this regatta (%s).", $sailor));
    }
    if ($cross_rp === false) {
      if ($sailor->school->id != $school->id) {
        throw new SoterException(sprintf("Sailor provided (%s) cannot sail for given school.", $sailor));
      }
    }
    return $sailor;
  }

  private function getNoShowSailor() {
    if ($this->noShowSailor == null) {
      $this->noShowSailor = new Sailor();
      $this->noShowSailor->id = self::NO_SHOW_ID;
    }
    return $this->noShowSailor;
  }
}