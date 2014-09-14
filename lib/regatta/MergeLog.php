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
      return DB::$NOW;
    case 'error':
      return array();
    default:
      return parent::db_type($field);
    }
  }

  protected function db_order() {
    return array('started_at' => false);
  }
}

/**
 * Which unregistered sailor was merged, and with which sailor
 *
 * This class contains the necessary parameters to 'recreate' the
 * unregistered sailor entry that was deleted during the merge,
 * assuming that role = 'student'.
 *
 * @author Dayan Paez
 * @version 2014-09-14
 */
class Merge_Sailor_Log extends DBObject {
  protected $merge_log;
  protected $school;
  protected $sailor;
  public $last_name;
  public $first_name;
  public $year;
  public $gender;
  public $regatta_added;

  public function db_type($field) {
    switch ($field) {
    case 'merge_log': return DB::$MERGE_LOG;
    case 'school':    return DB::$SCHOOL;
    case 'sailor':    return DB::$SAILOR;
    default: return parent::db_type($field);
    }
  }

  protected function db_order() { return array('last_name'=>true, 'first_name'=>true); }

  /**
   * Creates an unpersisted Sailor based on parameters
   *
   * Useful when a deleted sailor needs to be revived
   *
   * @return Sailor a copy of original unregistered sailor
   */
  public function createUnregisteredSailor() {
    $sailor = new Sailor();
    $sailor->first_name = $this->first_name;
    $sailor->last_name = $this->last_name;
    $sailor->school = $this->__get('school');
    $sailor->year = $this->year;
    $sailor->gender = $this->gender;
    $sailor->role = Sailor::STUDENT;
    $sailor->regatta_added = $this->regatta_added;
    return $sailor;
  }

  /**
   * Fetch list of RP entries affected by this merge
   *
   * @return Array:RPEntry
   */
  public function getRpEntries() {
    return DB::getAll(
      DB::$RP_ENTRY,
      new DBCondIn(
        'id',
        DB::prepGetAll(
          DB::$MERGE_RP_LOG,
          new DBCond('merge_sailor_log', $this),
          array('rp')
        )
      )
    );
  }
}

/**
 * The individual RP entries that were changed in the merge
 *
 * @author Dayan Paez
 * @version 2014-09-14
 */
class Merge_Rp_Log extends DBObject {
  protected $merge_sailor_log;
  protected $rp;

  public function db_type($field) {
    switch ($field) {
    case 'merge_sailor_log': return DB::$MERGE_SAILOR_LOG;
    case 'rp': return DB::$RP_ENTRY;
    default: return parent::db_type($field);
    }
  }
}

DB::$MERGE_LOG = new Merge_Log();
DB::$MERGE_SAILOR_LOG = new Merge_Sailor_Log();
DB::$MERGE_RP_LOG = new Merge_Rp_Log();
?>