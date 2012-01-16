<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

require_once('conf.php');

/**
 * Encapsulates a team for single-handed regattas. In singlehanded
 * events, the team name is the standard team name from the database
 * unless a sailor has been identified (for division A as a
 * skipper). In that case, then the sailor's name and year are used
 * instead. This is useful when printing the "name"
 *
 * @author  Dayan Paez
 * @version 2010-02-24
 */
class SinglehandedTeam extends Team {

  private $rp = null;

  /**
   * Sets the RP manager to use to pull the sailor information. If
   * none specified, then the Team's class behavior is simulated in
   * __toString()
   *
   * @param RpManager $rp the manager
   */
  public function setRpManager(RpManager $rp) {
    $this->rp = $rp;
  }

  /**
   * Overrides the parent's method for retrieving name
   *
   * @param String $name the name of the property, only "name" is overriden
   */
  public function __get($name) {
    if ($name == "name")
      return $this->getQualifiedName();
    return parent::__get($name);
  }

  /**
   * Returns either the skipper in A division, or the team name
   *
   * @return String name of the team or sailor
   */
  private function getQualifiedName() {
    if ($this->rp == null) return parent::__get("name");

    try {
      $rps = $this->rp->getRP($this, Division::A(), RP2::SKIPPER);
      if (count($rps) == 0) return parent::__get("name");

      $sailors = array();
      foreach ($rps as $rp)
	$sailors[] = $rp->sailor;
      return implode("/", $sailors);
    } catch (Exception $e) {
      return parent::__get("name");
    }
  }

  /**
   * Overrides the parent __toString() method to print the skipper(s)
   * in A Division, or the team name
   *
   * @return String the string representation of the team
   */
  public function __toString() {
    return sprintf("%s %s", $this->__get('school')->name, $this->getQualifiedName());
  }
}
?>