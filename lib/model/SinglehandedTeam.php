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

  const CLASSNAME = 'singlehanded-sailor-span';
  const SEPARATOR = '/';

  /**
   * Returns either the skipper in A division, or the team name
   *
   * @return String name of the team or sailor
   */
  public function &getQualifiedName() {
    $sailors = $this->getSailors();
    if (count($sailors) == 0) {
      return parent::__get('name');
    }
    $name = implode(self::SEPARATOR, $sailors);
    return $name;
  }

  /**
   * Delegate to the sailor's toView representation.
   *
   * @param boolean $public true to include URL.
   * @return Xmlable or text.
   */
  public function toView($public = false) {
    $sailors = $this->getSailors();
    if (count($sailors) == 0) {
      return parent::getQualifiedName();
    }

    $span = new XSpan("", array('class' => self::CLASSNAME));
    foreach ($sailors as $i => $sailor) {
      if ($i > 0) {
        $span->add(self::SEPARATOR);
      }
      $span->add($sailor->toView($public));
    }
    return $span;
  }

  /**
   * Helper method to return the list of sailors.
   *
   * @return Array empty if no regatta, or no sailor.
   */
  private function getSailors() {
    if ($this->regatta == null) {
      return array();
    }

    try {
      $rps = $this->__get('regatta')->getRpManager()->getRP(
        $this,
        Division::A(),
        RP::SKIPPER
      );
      if (count($rps) == 0) {
        return array();
      }

      $sailors = array();
      foreach ($rps as $rp) {
        if ($rp->sailor !== null) {
          $sailors[] = $rp->sailor;
        }
      }
      return $sailors;
    }
    catch (Exception $e) {
      return array();
    }
  }
}
