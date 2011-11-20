<?php
/*
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
 * @version 2009-11-30
 */
class Account {

  // Variables
  public $first_name;
  public $last_name;
  public $id;
  public $role;
  public $admin;
  public $status;
  public $password;
  private $school;

  const FIELDS = "account.first_name, account.last_name, account.school, account.password, 
                  account.id, account.role, account.status, is_admin as admin";
  const TABLES = "account";

  public function __toString() {
    return $this->getName();
  }

  /**
   * One-time de-serializes the "school", the only private property
   * for this object
   *
   * @param String $key must be "school"
   * @throws InvalidArgumentException if key is not "school"
   */
  public function __get($key) {
    if ($key != "school") throw new InvalidArgumentException("Invalid value requested from Account");
    if (!($this->school instanceof School))
      $this->school = Preferences::getSchool($this->school);
    return $this->school;
  }

  /**
   * To be used to set the school. Anything else generates an error
   *
   * @param String $key == "school"
   * @param School $value the school to set
   * @throws InvalidArgumentException if attempting to set any other
   * property or invalid value provided
   */
  public function __set($key, $value) {
    if ($key != "school")
      throw new InvalidArgumentException("Only the school property can be altered for Account.");
    if (!($value instanceof School))
      throw new InvalidArgumentException("Account school property must be School object.");
    $this->school = $value;
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