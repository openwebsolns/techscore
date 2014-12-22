<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

/*
 * Library of ORM classes for auto-merging unregistered sailors and
 * tracking merge operations.
 */

/**
 * History of merge attempts
 *
 * @author Dayan Paez
 * @created 2014-09-14
 */
class Merge_Log extends DBObject {
  protected $started_at;
  protected $ended_at;
  protected $error;

  public function db_type($field) {
    switch ($field) {
    case 'started_at':
    case 'ended_at':
      return DB::T(DB::NOW);
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
   * Fetch Merge_Sailor_Log associated with this log
   *
   * @return Array:Merge_Sailor_Log
   */
  public function getMergeSailorLogs() {
    return DB::getAll(DB::T(DB::MERGE_SAILOR_LOG), new DBCond('merge_log', $this));
  }

  public function getMergedRegattas() {
    require_once('regatta/Regatta.php');

    // Only count public regattas
    return DB::getAll(
      DB::T(DB::REGATTA),
      new DBCondIn(
        'id',
        DB::prepGetAll(
          DB::T(DB::MERGE_REGATTA_LOG),
          new DBCondIn(
            'merge_sailor_log',
            DB::prepGetAll(DB::T(DB::MERGE_SAILOR_LOG), new DBCond('merge_log', $this), array('id'))
          ),
          array('regatta')
        )
      )
    );
  }
}
