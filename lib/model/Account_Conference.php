<?php
/*
 * This file is part of Techscore
 */



/**
 * Many-tomany relationship between accounts and conferences
 *
 * @author Dayan Paez
 * @version 2014-05-25
 */
class Account_Conference extends DBObject {
  protected $account;
  protected $conference;
  public function db_type($field) {
    switch ($field) {
    case 'account': return DB::T(DB::ACCOUNT);
    case 'school':  return DB::T(DB::SCHOOL);
    default:
      return parent::db_type($field);
    }
  }
}
