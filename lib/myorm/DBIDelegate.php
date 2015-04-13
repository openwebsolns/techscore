<?php
namespace MyORM;

/**
 * DBObject delegate makes use of DBI cache mechanism where possible.
 *
 * @author Dayan Paez
 * @version 2013-01-27
 */
class DBIDelegate implements DBDelegatable {
  protected $object_type;
  protected $dbi;

  public function __construct($class, DBI $dbi) {
    $this->setDBI($dbi);
    $this->setClass($class);
  }

  public function setClass($class) {
    $this->object_type = $class;
  }

  public function setDBI(DBI $dbi) {
    $this->dbi = $dbi;
  }

  /**
   * Returns the object at the given pointer, possibly from DBI cache
   *
   */
  public function delegate_current(\MySQLi_Result $pointer) {
    $obj = $pointer->fetch_object($this->object_type);
    if ($obj === null)
      return null;
    if ($obj->db_get_cache()) {
      if (($cache = $this->dbi->getCached($obj, $obj->id)) !== null)
        return $cache;
      $this->dbi->setCached($obj);
    }
    $obj->db_set_dbi($this->dbi);
    return $obj;
  }
}
?>