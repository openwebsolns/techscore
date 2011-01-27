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
 * Encapsulates a sailor
 *
 * @author Dayan Paez
 * @created 2009-10-04
 */
class Sailor {
  public $id;
  public $school;
  public $last_name;
  public $first_name;
  public $year;
  public $role;
  public $icsa_id;

  const FIELDS = "id, icsa_id, last_name, first_name, year, role";
  const TABLES = "sailor";


  /**
   * Oversees variable acquisition. Provides for determining whether
   * sailor is registered
   *
   * @para String $name the name of the property
   */
  public function __get($name) {
    if ($name == "registered")
      return ($this->icsa_id > 0);
    if (isset($this->$name))
      return stripslashes($this->$name);
    throw new InvalidArgumentException("No such property $name for Sailors.");
  }

  public function __toString() {
    $year = ($this->year > 0) ? substr($this->year, -2) : "??";
    $name = sprintf("%s %s '%s",
		    $this->first_name,
		    $this->last_name,
		    $year);
    if (!$this->__get('registered'))
      $name .= " *";
    return stripslashes($name);
  }
}
?>