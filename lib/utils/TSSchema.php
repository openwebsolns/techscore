<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package scripts
 */

/**
 * Special schema table
 *
 * @author Dayan Paez
 * @version 2014-11-12
 */
class TSSchema extends DBObject {

  public $downgrade;
  protected $performed_at;
  public function db_name() { return '_schema_'; }
  public function db_type($field) {
    if ($field == 'performed_at')
      return DB::$NOW;
    return parent::db_type($field);
  }
  protected function db_order() { return array('performed_at' => false); }

  /**
   * Factory method, for convenience. Also stores in DB
   *
   */
  public static function create($file, $downgrade = null) {
    $obj = new TSSchema();
    $obj->id = (string)$file;
    $obj->downgrade = $downgrade;
    $obj->performed_at = DB::$NOW;
    DB::set($obj);
    return $obj;
  }
}

/**
 * Temporary table
 *
 * @author Dayan Paez
 * @version 2014-11-18
 */
class TSNewSchema extends DBObject {
  public function db_name() { return '_schema_new_'; }
  protected function db_order() { return array('id' => true); }
}
?>