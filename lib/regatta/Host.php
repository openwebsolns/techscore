<?php
/*
 * This class is part of Techscore
 *
 * @author Dayan Paez
 * @created 2011-04-05
 * @package regatta
 */

/**
 * Encapsulates a host. Really, just a school. This is needed because
 * Regatta's getHosts method used to return an account, and so all
 * client codes expects to go through $obj->school to get the school.
 *
 * @author Dayan Paez
 * @version 2011-04-05
 */
class Host {
  public $id;
  public $regatta;
  private $school;

  const FIELDS = "host_school.id, host_school.regatta, host_school.school";
  const TABLES = "host_school";

  /**
   * One-time de-serializes the "school", the only private property
   * for this object
   *
   * @param String $key must be "school"
   * @throws InvalidArgumentException if key is not "school"
   */
  public function __get($key) {
    if ($key != "school") throw new InvalidArgumentException("Invalid value requested from Host");
    if (!($this->school instanceof School))
      $this->school = Preferences::getSchool($this->school);
    return $this->school;
  }
}
?>