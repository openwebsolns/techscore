<?php

/**
 * Parent class of database elements that may be inactivated from one
 * season to the next.
 *
 * @author Dayan Paez
 * @version 2014-09-28
 */
abstract class Element_Season extends DBObject {
  protected $season;
  protected $activated;

  public function db_type($field) {
    if ($field == 'season')
      return DB::T(DB::SEASON);
    if ($field == 'activated')
      return DB::T(DB::NOW);
    return parent::db_type($field);
  }
}
