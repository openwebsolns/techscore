<?php
/*
 * This file is part of Techscore
 */



/**
 * Host account for a regatta (as just an ID) [many-to-many]
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
class Scorer extends DBObject {
  public $regatta;
  protected $account;
  public $principal;

  public function db_type($field) {
    switch ($field) {
    case 'account': return DB::T(DB::ACCOUNT);
    default:
      return parent::db_type($field);
    }
  }
}
