<?php
/*
 * This file is part of Techscore
 */



/**
 * Group of races (team racing only)
 *
 * @author Dayan Paez
 * @version 2013-05-04
 */
class Round_Group extends DBObject {
  protected function db_cache() { return true; }
  public function __toString() { return $this->id; }

  public function getRounds() {
    return DB::getAll(DB::T(DB::ROUND), new DBCond('round_group', $this));
  }

  /**
   * Returns string concatenation of round's titles
   *
   */
  public function getTitle() {
    $label = "";
    foreach ($this->getRounds() as $i => $round) {
      if ($i > 0)
        $label .= ", ";
      $label .= $round;
    }
    return $label;
  }
}
