<?php
/**
 * This class is part of TechScore
 *
 * @package regatta
 */
require_once('conf.php');

/**
 * Encapsulates an account: a user devoid of "extra" information and a
 * connection to the database
 *
 * @author Dayan Paez
 * @created 2009-11-30
 */
class Account {

  // Variables
  public $first_name;
  public $last_name;
  public $username;
  public $school;
  public $role;
  public $admin;

  const FIELDS = "account.first_name, account.last_name, 
                  account.username, account.role,
                  is_admin as admin,
                  school.id, school.nick_name, school.name, school.conference,
                  school.city, school.state, school.burgee";
  const TABLES = "account inner join school on (account.school = school.id)";

  public function __construct() {
    // Create school object, and delete all temporary fields
    $school = new School();
    $school->id         = $this->id;
    $school->nick_name  = $this->nick_name;
    $school->name       = $this->name;
    $school->conference = $this->conference;
    $school->city       = $this->city;
    $school->state      = $this->state;
    $school->burgee     = $this->burgee;

    $this->school = $school;
    unset($this->id,
	  $this->nick_name,
	  $this->name,
	  $this->conference,
	  $this->city,
	  $this->state,
	  $this->burgee);

  }
  public function __toString() {
    return $this->getName();
  }

  /**
   * Returns the user's name
   *
   * @return string "First Lastname"
   */
  public function getName() {
    return sprintf("%s %s", $this->first_name, $this->last_name);
  }
}
?>