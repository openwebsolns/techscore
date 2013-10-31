<?php
/*
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */

/**
 * The combined record of participation for a sailor in a specific
 * boat_role in a specific team. The races--which are distinct RPEntry
 * objects--sailed in the same division are collected in the list
 * races_nums.
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class RP {
  const SKIPPER = "skipper";
  const CREW    = "crew";

  private $races_nums;
  private $rps;

  /**
   * Creates a new such record using the given list of RPEntries,
   * where all RPEntries share the same sailor, race-division, team,
   * and boat_role.
   *
   * @param ArrayIterator $rps probably a DBDelegate
   */
  public function __construct($rps = array()) {
    $this->rps = $rps;
  }

  /**
   * Delegate these to one of its RPEntries
   */
  public function __get($field) {
    if (count($this->rps) == 0)
      return null;
    switch ($field) {
    case 'sailor':
    case 'team':
    case 'boat_role':
      return $this->rps[0]->$field;
    case 'division':
      return $this->rps[0]->race->division;
    case 'races_nums':
      if (!is_array($this->races_nums)) {
        $this->races_nums = array();
        foreach ($this->rps as $rp)
          $this->races_nums[] = $rp->race->number;
        sort($this->races_nums);
      }
      return $this->races_nums;
    default:
      throw new InvalidArgumentException("No such property $field in RP.");
    }
  }

  /**
   * Returns sailor representation, including year
   *
   * Returns "No show" if sailor is null
   *
   * @param boolean $xml true to wrap No shows in XSpan
   * @return String|null normally: (string)$sailor
   */
  public function getSailor($xml = false) {
    if (count($this->rps) == 0)
      return null;
    if ($this->rps[0]->sailor === null) {
      if ($xml !== false)
        return new XSpan("No show", array('class'=>'noshow'));
      return "No show";
    }
    return (string)$this->rps[0]->sailor;
  }

  /**
   * Returns suitable representation of sailor
   *
   * Includes "No show" for null sailor values
   *
   * @return String|null name of sailor
   */
  public function getSailorName() {
    if (count($this->rps) == 0)
      return null;
    if ($this->rps[0]->sailor === null)
      return "No show";
    return $this->rps[0]->sailor->getName();
  }

  /**
   * Returns suitable representation of sailor year
   *
   * Returns "----" for null sailor values
   *
   * @return String|null graduation year of sailor
   */
  public function getSailorYear() {
    if (count($this->rps) == 0)
      return null;
    if ($this->rps[0]->sailor === null)
      return "----";
    return $this->rps[0]->sailor->year;
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
  public static function getRoles() {
    return array(RP::SKIPPER => "Skipper", RP::CREW => "Crew");
  }
}
?>