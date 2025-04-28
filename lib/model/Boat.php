<?php
/*
 * This file is part of Techscore
 */



/**
 * A boat class, like Techs and FJs.
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
class Boat extends DBObject {
  public $name;
  public $min_crews;
  public $max_crews;

  protected function db_cache() { return true; }
  protected function db_order() { return array('name'=>true); }
  public function __toString() { return $this->name; }

  public function getRaces() {
    return DB::getAll(DB::T(DB::RACE), new DBCond('boat', $this));
  }

  public function getRounds() {
    return DB::getAll(DB::T(DB::ROUND), new DBCond('boat', $this));
  }

  /**
   * Returns count of crews allowed on the boat as a string.
   *
   * @return "3-4", or "1"
   */
  public function getNumCrews() {
    $num = (int)$this->min_crews;
    if ($this->max_crews != $this->min_crews)
      $num .= '-' . (int)$this->max_crews;
    return $num;
  }
}
