<?php
namespace model;

use \MyORM\DBI;
use \MyORM\DBObject;
use \Conf;
use \DateTime;

/**
 * A database entity with four auto-maintained fields.
 *
 * The fields provide for auditability of the objects, by identifying
 * when they were created and last updated (and by whom).
 *
 * @author Dayan Paez
 * @version 2015-10-16
 */
abstract class Object extends DBObject {

  protected $created_on;
  protected $last_updated_on;
  public $created_by;
  public $last_updated_by;

  /**
   * Strips namespace, and converts PascalCase to snake_case
   *
   * @return String
   */
  public function db_name() {
    $parts = explode('\\', get_class($this));
    $conv = preg_replace('/([A-Z])/', '_\1', lcfirst(array_pop($parts)));
    return strtolower($conv);
  }

  public function db_type($field) {
    switch ($field) {
    case 'created_on':
    case 'last_updated_on':
      return new DateTime();
    default:
      return parent::db_type($field);
    }
  }

  public function db_commit(DBI $dbi = null, $update = 'guess') {
    if ($dbi === null) {
      $dbi = Conf::$DB;
    }
    $this->db_prep_set();
    parent::db_commit($dbi, $update);
  }

  /**
   * Convenience method to set the auditing fields.
   *
   */
  public function db_prep_set() {
    if ($this->created_on === null) {
      $this->created_on = new DateTime();
      if (Conf::$USER !== null) {
        $this->created_by = Conf::$USER->id;
      }
    }
    else {
      $this->last_updated_on = new DateTime();
      if (Conf::$USER !== null) {
        $this->last_updated_by = Conf::$USER->id;
      }
    }
  }
}