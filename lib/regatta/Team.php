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
   * School object for this team
   */
  private $school;
  
  protected $school_id;
  protected $school_name;
  protected $school_nick_name;
  protected $school_conference;
  protected $school_city;
  protected $school_state;
  protected $school_burgee;

  public function __construct() {
    $this->school = new School();
    $this->school->id         = $this->school_id;
    $this->school->name       = $this->school_name;
    $this->school->nick_name  = $this->school_nick_name;
    $this->school->conference = $this->school_conference;
    $this->school->city       = $this->school_city;
    $this->school->state      = $this->school_state;
    $this->school->burgee     = $this->school_burgee;

    unset($this->school_id, $this->school_name, $this->school_nick_name,
	  $this->school_conference, $this->school_city,
	  $this->school_state, $this->school_burgee);
  }

  /**
   * Getter method
   *
   */
  public function __get($name) {
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
    return sprintf("%s %s", $this->school->nick_name, $this->name);
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