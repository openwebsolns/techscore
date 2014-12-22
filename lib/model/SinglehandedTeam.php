<?php
/*
 * This file is part of Techscore
 */



/**
 * Team for the purpose of a singlehanded event. For those events, the
 * string representation of a team is the sailor's name, if such exists.
 *
 * @author Dayan Paez
 * @version 2012-01-16
 */
class SinglehandedTeam extends Team {

  /**
   * Returns either the skipper in A division, or the team name
   *
   * @return String name of the team or sailor
   */
  public function &getQualifiedName() {
    if ($this->regatta == null) return parent::__get("name");

    try {
      $rps = $this->__get('regatta')->getRpManager()->getRP($this, Division::A(), RP::SKIPPER);
      if (count($rps) == 0)
        return parent::__get("name");

      // Should be one, but just in case
      $sailors = array();
      foreach ($rps as $rp)
        $sailors[] = $rp->sailor;
      $sailors = implode("/", $sailors);
      return $sailors;
    } catch (Exception $e) {
      return parent::__get("name");
    }
  }
}
