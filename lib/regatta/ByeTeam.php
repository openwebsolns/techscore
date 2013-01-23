<?php
/*
 * This class is part of TechScore
 *
 * @author Dayan Paez
 * @version 2011-02-18
 * @package regatta
 */

/**
 * Describes a BYE team: ie a team that is no official team at all,
 * and not at all registered with the database and is useful for
 * creating rotations, for instance.
 *
 * @author Dayan Paez
 * @version 2011-02-18
 */
class ByeTeam extends Team {
  public function __construct() {
    $this->id = "BYE";
    $this->name = "BYE";
    $this->school = null;
  }
  public function &getQualifiedName() {
    $a = "BYE Team";
    return $a;
  }
  public function __toString() {
    return "BYE Team";
  }
}

?>