<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

/**
 * Encompasses a request for an outgoing message
 *
 * @author Dayan Paez
 * @version 2011-11-18
 */
class Outbox {
  public $id;
  public $sender;
  protected $queue_time;
  protected $completion_time;
  public $recipients;
  public $arguments;
  public $copy_sender;
  public $subject;
  public $content;

  const TABLES = 'outbox';

  public function __get($field) {
    switch ($field) {
    case 'queue_time':
    case 'completion_time':
      if ($this->$field === null) return null;
      if (!($this->$field instanceof DateTime))
	$this->$field = new DateTime($this->$field);
    }
    return $this->$field;
  }
  /**
   * Ascertains that the time fields are indeed DateTime objects
   *
   * @throws InvalidArgumentException if $value is of an invalid type
   */
  public function __set($field, $value) {
    switch ($field) {
    case 'queue_time':
    case 'completion_time':
      if ($value === null) {
	$this->$field = null;
	return;
      }
      if (!($value instanceof DateTime))
	throw new InvalidArgumentException("$field must be DateTime.");
    }
    $this->$field = $value;
  }
}
?>