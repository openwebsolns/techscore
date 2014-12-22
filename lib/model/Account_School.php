<?php
/*
 * This file is part of Techscore
 */



/**
 * Many-to-many relationship between accounts and schools
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
class Account_School extends DBObject {
  protected $account;
  protected $school;
  public function db_type($field) {
    switch ($field) {
    case 'account': return DB::T(DB::ACCOUNT);
    case 'school':  return DB::T(DB::SCHOOL);
    default:
      return parent::db_type($field);
    }
  }
}
