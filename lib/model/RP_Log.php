<?php
/*
 * This file is part of Techscore
 */



/**
 * Log of RP changes, for updating to public site
 *
 * @author Dayan Paez
 * @version 2012-01-15
 */
class RP_Log extends DBObject {
  public $regatta;
  protected $updated_at;

  public function db_name() { return 'rp_log'; }
  public function db_type($field) {
    switch ($field) {
    case 'updated_at': return DB::T(DB::NOW);
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('updated_at'=>false); }
}
