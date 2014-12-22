<?php
/*
 * This file is part of Techscore
 */



/**
 * Tokens created to validate emails
 *
 * @author Dayan Paez
 * @version 2014-11-23
 */
class Email_Token extends DBObject {
  protected $account;
  protected $deadline;
  public $email;

  public function db_type($field) {
    switch ($field) {
    case 'account':
      require_once('Account.php');
      return DB::T(DB::ACCOUNT);
    case 'deadline':
      return DB::T(DB::NOW);
    default:
      return parent::db_type($field);
    }
  }

  /**
   * True if deadline hasn't passed
   *
   * @return boolean
   */
  public function isTokenActive() {
    return (
      $this->deadline !== null
      && $this->__get('deadline') > DB::T(DB::NOW)
    );
  }

  public function __toString() {
    return $this->id;
  }
}
