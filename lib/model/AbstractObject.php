<?php
namespace model;

use \DB;
use \DBObject;
use \Conf;

/**
 * A database entity with four auto-maintained fields.
 *
 * The fields provide for auditability of the objects, by identifying
 * when they were created and last updated (and by whom).
 *
 * @author Dayan Paez
 * @version 2015-10-16
 */
abstract class AbstractObject extends DBObject {

  protected $created_on;
  protected $last_updated_on;
  public $created_by;
  public $last_updated_by;

  public function db_type($field) {
    switch ($field) {
    case 'created_on':
    case 'last_updated_on':
      return DB::T(DB::NOW);
    default:
      return parent::db_type($field);
    }
  }

  /**
   * Convenience method to set the auditing fields.
   *
   */
  public function db_prep_set() {
    if ($this->created_on === null) {
      $this->created_on = DB::T(DB::NOW);
      if (Conf::$USER !== null) {
        $this->created_by = Conf::$USER->id;
      }
    }
    else {
      $this->last_updated_on = DB::T(DB::NOW);
      if (Conf::$USER !== null) {
        $this->last_updated_by = Conf::$USER->id;
      }
    }
  }
}
