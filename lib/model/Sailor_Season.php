<?php
/*
 * This file is part of Techscore
 */



/**
 * Indicator of which seasons a sailor was active in
 *
 * @author Dayan Paez
 * @version 2014-09-28
 */
class Sailor_Season extends Element_Season {
  protected $sailor;

  public function db_type($field) {
    if ($field == 'sailor')
      return DB::T(DB::MEMBER);
    return parent::db_type($field);
  }
}
