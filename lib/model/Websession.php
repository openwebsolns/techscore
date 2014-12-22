<?php
/*
 * This file is part of Techscore
 */



/**
 * Web-based session object
 *
 * @author Dayan Paez
 * @version 2013-10-29
 */
class Websession extends DBObject {
  public $sessiondata;
  protected $created;
  protected $last_modified;
  protected $expires;

  protected function db_order() { return array('last_modified'=>false); }
  protected function db_cache() { return true; }
  public function db_type($field) {
    switch ($field) {
    case 'created':
    case 'last_modified':
    case 'expires':
      return DB::T(DB::NOW);
    }
  }
}
