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
  const R_ALL = 'all';
  const R_CONFERENCES = 'conferences';
  const R_ROLES = 'roles';

  public $sender;
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
    default:
      return parent::db_type($field);      
    }
  }
}
// Create template item
DB::$OUTBOX = new Outbox();
?>