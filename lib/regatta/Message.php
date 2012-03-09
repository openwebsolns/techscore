<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

/**
 * Message to a user and all that entails
 *
 * @author Dayan Paez
 * @version 2010-03-25
 */
class Message extends DBObject {

  protected $account;
  protected $created;
  protected $read_time;
  public $content;
  public $subject;
  public $active;

  public function db_type($field) {
    switch ($field) {
    case 'created':
    case 'read_time':
      return DB::$NOW;
    case 'account':
      return DB::$ACCOUNT;
    default:
      return parent::db_type($field);
    }
  }

  protected function db_order() { return array('created'=>false); }
  public function db_where() { return new DBCond('active', 1); }

  /**
   * Returns just the content
   *
   * @return String the content
   */
  public function __toString() {
    return $this->content;
  }
}
DB::$MESSAGE = new Message();
?>