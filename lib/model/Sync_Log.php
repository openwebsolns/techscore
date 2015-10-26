<?php

/**
 * Log of every database sync process run
 *
 * @author Dayan Paez
 * @version 2014-05-31
 */
class Sync_Log extends DBObject {
  protected $started_at;
  protected $ended_at;
  protected $updated;
  protected $error;

  const SCHOOLS = 'schools';
  const SAILORS = 'sailors';

  public function db_type($field) {
    switch ($field) {
    case 'started_at':
    case 'ended_at':
      return DB::T(DB::NOW);
    case 'updated':
    case 'error':
      return array();
    default:
      return parent::db_type($field);
    }
  }

  protected function db_order() {
    return array('started_at' => false);
  }

  /**
   * Gets the schools added in this sync process
   *
   * @return Array:School
   */
  public function getSchools() {
    return DB::getAll(DB::T(DB::SCHOOL), new DBCond('sync_log', $this));
  }

  /**
   * Gets the sailors added in this sync process
   *
   * @return Array:Sailor
   */
  public function getSailors() {
    return DB::getAll(DB::T(DB::SAILOR), new DBCond('sync_log', $this));
  }
}
