<?php
/*
 * This file is part of Techscore
 */



/**
 * Cache of (local) URLs which have been serialized for a regatta
 *
 * @author Dayan Paez
 * @version 2013-10-02
 */
class Pub_Regatta_Url extends DBObject {
  protected $regatta;
  public $url;

  public function db_type($field) {
    if ($field == 'regatta')
      return DB::T(DB::FULL_REGATTA);
    return parent::db_type($field);
  }
}
