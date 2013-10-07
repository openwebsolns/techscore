<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

require_once('regatta/DB.php');

/**
 * Encompasses a request for an outgoing message
 *
 * @author Dayan Paez
 * @version 2011-11-18
 */
class Outbox extends DBObject {
  /**
   * @var String constant to use for sending to all users
   */
  const R_ALL = 'all';
  /**
   * @var String send based on account role
   */
  const R_ROLE = 'roles';
  /**
   * @var String send based on conference membership
   */
  const R_CONF = 'conferences';
  /**
   * @var String send based on school access
   */
  const R_SCHOOL = 'schools';
  /**
   * @var String send based on IDs
   */
  const R_USER = 'users';

  public static function getRecipientTypes() {
    return array(self::R_ALL => self::R_ALL,
                 self::R_ROLE => self::R_ROLE,
                 self::R_CONF => self::R_CONF,
                 self::R_SCHOOL => self::R_SCHOOL,
                 self::R_USER => self::R_USER);
  }

  protected $sender;
  protected $queue_time;
  protected $completion_time;
  protected $arguments;
  public $recipients;
  public $copy_sender;
  public $subject;
  public $content;

  public function db_type($field) {
    switch ($field) {
    case 'queue_time':
    case 'completion_time':
      return DB::$NOW;
    case 'arguments':
      return array();
    case 'sender':
      return DB::$ACCOUNT;
    default:
      return parent::db_type($field);      
    }
  }
}
// Create template item
DB::$OUTBOX = new Outbox();
?>