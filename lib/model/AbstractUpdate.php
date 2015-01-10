<?php


abstract class AbstractUpdate extends DBObject {
  public $activity;
  protected $request_time;
  protected $completion_time;

  public function db_type($field) {
    switch ($field) {
    case 'request_time':
    case 'completion_time':
      return DB::T(DB::NOW);
    }
    return parent::db_type($field);
  }
  protected function db_order() { return array('request_time'=>true); }

  /**
   * Unique identifier for the request, without taking ID into account
   *
   */
  abstract public function hash();
}
