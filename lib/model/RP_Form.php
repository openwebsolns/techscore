<?php
/*
 * This file is part of Techscore
 */



/**
 * Cached copy of RP physical, PDF form
 *
 * @author Dayan Paez
 * @version 2012-01-15
 */
class RP_Form extends DBObject {
  public $filedata;
  protected $created_at;

  public function db_type($field) {
    switch ($field) {
    case 'created_at': return DB::T(DB::NOW);
    default:
      return parent::db_type($field);
    }
  }
}
