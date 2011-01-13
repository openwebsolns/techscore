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
 * Encapsulates a team
 *
 */
class Team {

  // Variables
  private $id;
  private $name;
  /**
   * School object for this team. The initial value of false means
   * that it is yet to be serialized from database. After that it will
   * contain either null or a School object, as returned by
   * Preferences class.
   */
  protected $school = false;

  /**
   * Getter method: delays creation of school object until deemed
   * absolutely necessary by code (i.e. the first time it is requested).
   *
   * @param String $name the name of the variable to fetch
   */
  public function __get($name) {
    // intercept call for school
    if ($name == "school") {
      if ($this->school === false)
	return null;
      if (!($this->school instanceof School) && $this->school !== null)
	$this->school = Preferences::getSchool($this->school);
      return $this->school;
    }
    // return what once was there
    if (isset($this->$name) && substr($name, 0, 1) != "_")
      return $this->$name;
    throw new InvalidArgumentException(sprintf("Invalid Team property (%s).", $name));
  }

  /**
   * Setter methods
   */
  public function __set($name, $value) {
    switch ($name) {
    case "name":
      $this->name = (string)$value;
      break;

    case "school":
      if (!($value instanceof School))
	throw new BadFunctionCallException("School property must be a School object.");
      $this->school = $value;
      break;

    case "id":
      $this->id = (int)$value;
      break;

    default:
      throw new BadFunctionCallException(sprintf("Property (%s) not editable for Team.", $name));
    }
    $this->fireTeamChange();
  }

  public function __toString() {
    return sprintf("%s %s", $this->__get('school')->nick_name, $this->name);
  }

  // ------------------------------------------------------------
  // Listeners

  private $_notify = array();

  /**
   * Registers the given listener with this object
   *
   * @param TeamListener $obj the race listener
   */
  public function addListener(TeamListener $obj) {
    $this->_notify[] = $obj;
  }

  /**
   * Removes the given listener from this object
   *
   * @param TeamListener $obj the race listener
   */
  public function removeListener(TeamListener $obj) {
    if (($key = array_search($obj, $this->_notify)) !== false) {
      unset($this->_notify[$key]);
    }
  }
  

  /**
   * Broadcast message to listeners
   *
   */
  private function fireTeamChange() {
    foreach ($this->_notify as $listener)
      $listener->changedTeam($this);
  }
}

?>
