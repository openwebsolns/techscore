<?php
/**
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */
require_once('conf.php');

/**
 * Encapsulates a race. Changes to the attributes of this object are
 * broadcast to its notifiers, or listeners. If this race was created
 * by a Regatta object, then that regatta object is notified of
 * changes made to this race's properties. Note that race number is
 * not available for editing as it is automatically calculated.
 *
 */
class Race {

  // Variables
  private $id;
  private $division;
  private $number;
  private $boat;
  private $_notify = array();

  /**
   * Temporary property
   */
  private $boat_name;
  /**
   * Temporary property
   */
  private $boat_occupants;


  // Race fields
  const FIELDS = "race.id, race.division, race_num.number, 
                  boat.id as boat, boat.name as boat_name, 
                  boat.occupants as boat_occupants";
  const TABLES = "race inner join race_num using(id) inner join boat on race.boat = boat.id";

  public function __construct() {
    $boat = new Boat();
    $boat->id = $this->boat;
    $boat->name = $this->boat_name;
    $boat->occupants = (int)$this->boat_occupants;

    $this->boat = $boat;

    unset($this->boat_name, $this->boat_occupants);
  }

  public function __get($name) {
    if (isset($this->$name))
      return $this->$name;
    throw new InvalidArgumentException(sprintf("Invalid Race property (%s).", $name));
  }

  public function __set($name, $value) {
    switch ($name) {
    case "division":
      $this->setDivision($value);
      break;

    case "boat":
      $this->setBoat($value);
      break;

    default:
      throw new BadFunctionCallException(sprintf("Property (%s) not editable for Race.", $name));
    }
    $this->fireRaceChange();
  }

  private function setDivision(Division $div) {
    $this->division = $div;
  }

  private function setBoat(Boat $boat) {
    $this->boat = $boat;
  }

  public function __toString() {
    return sprintf("%s%s", $this->number, $this->division);
  }

  /**
   * Registers the given listener with this object
   *
   * @param RaceListener $obj the race listener
   */
  public function addListener(RaceListener $obj) {
    $this->_notify[] = $obj;
  }

  /**
   * Removes the given listener from this object
   *
   * @param RaceListener $obj the race listener
   */
  public function removeListener(RaceListener $obj) {
    if (($key = array_search($obj, $this->_notify)) !== false) {
      unset($this->_notify[$key]);
    }
  }

  /**
   * Broadcasts message to listeners
   *
   */
  private function fireRaceChange() {
    foreach ($this->_notify as $listener)
      $listener->changedRace($this);
  }

  /**
   * Parses the string and returns a Race object with the
   * corresponding division and number. Note that the race object
   * obtained is orphan.
   *
   * @param String $text the text representation of a race (3A, B12)
   * @return Race a race object
   * @throws InvalidArgumentException if unable to parse
   */
  public static function parse($text) {
    $race = (string)$text;
    try {
      $race = str_replace(" ", "", $race);
      $race = str_replace("-", "", $race);
      $race = strtoupper($race);

      if (in_array($race[0], array("A", "B", "C", "D"))) {
	// Move division letter to end of string
	$race = substr($race, 1) . substr($race, 0, 1);
      }
      if (in_array($race[strlen($race)-1],
		   array("A", "B", "C", "D"))) {
	$race_a = sscanf($race, "%d%s");
      }
      else
	throw new InvalidArgumentException("Race is missing division.");;

      if (empty($race_a[0]) || empty($race_a[1])) {
	throw new InvalidArgumentException("Race is missing division or number.");
      }

      $race = new Race();
      $race->division = new Division($race_a[1]);
      $race->number   = $race_a[0];
      return $race;
    }
    catch (Exception $e) {
      throw new InvalidArgumentException("Unable to parse race.");
    }
  }

  /**
   * Compares races by number, then division.
   *
   * @param Race $r1 the first race
   * @param Race $r2 the second race
   * @return negative should $r1 have a lower number, or failing that, a
   * lower division than $r2; positive if the opposite is true; 0 if they
   * are equal
   */
  public static function compareNumber(Race $r1, Race $r2) {
    $diff = $r1->number - $r2->number;
    if ($diff != 0) return $diff;
    return ord((string)$r1->division) - ord((string)$r2->division);
  }
}
?>