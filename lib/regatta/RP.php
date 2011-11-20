<?php
/*
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */

require_once('conf.php');

/**
 * Encapsulates an RP entry
 *
 * @author Dayan Paez
 * @version 2009-10-05
 */
class RP {
  public $races_nums;
  public $sailor;
  public $team;
  public $boat_role;
  public $division;

  const SKIPPER = "skipper";
  const CREW    = "crew";

  public function __construct() {
    $this->races_nums = explode(",", $this->races_nums);
  }

  /**
   * Returns the constant which matches the role specified role, and
   * throws an error if it's invalid.
   *
   * @param mixed $role the role to parse
   * @return string constant
   * @throws IllegalArgumentError if no such role exists
   */
  public static function parseRole($role) {
    $role = (string)$role;
    switch ($role) {
    case RP::SKIPPER:
      return RP::SKIPPER;
    case RP::CREW:
      return RP::CREW;
    default:
      throw new IllegalArgumentError("Invalid sailor role: $role");
    }
  }
}
?>