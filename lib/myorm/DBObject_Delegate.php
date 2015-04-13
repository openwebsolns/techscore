<?php
namespace MyORM;

/**
 * Fetches results as objects of the given type
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
class DBObject_Delegate implements DBDelegatable {

  private $object_type;
  private $object_args;

  /**
   * Return the objects using the specified type and parameters
   *
   * @param String $class the class name (defaults to stdClass)
   * @param Array $args the optional arguments
   */
  public function __construct($class = null, Array $args = array()) {
    $this->object_type = ($class === null) ? "stdClass" : $class;
    $this->object_args = $args;
  }

  /**
   * Returns the formatted object at the given pointer
   *
   */
  public function delegate_current(\MySQLi_Result $pointer) {
    return $pointer->fetch_object($this->object_type, $this->object_args);
  }
}
?>