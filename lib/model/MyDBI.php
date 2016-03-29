<?php
namespace model;

use \MyORM\DBI;
use \InvalidArgumentException;

/**
 * Non-static database connection layer.
 *
 * @author Dayan Paez
 * @version 2016-03-29
 */
class MyDBI extends DBI {

  const WEBSESSION_LOG = 'WebsessionLog';

  private $objectCache = array();

  /**
   * Return template object (cached).
   *
   * @param String $classname use a class constant.
   * @return AbstractObject, probably.
   */
  public function T($className) {
    if (!array_key_exists($className, $this->objectCache)) {
      if (!class_exists($classname)) {
        throw new InvalidArgumentException("No such class: $classname.");
      }
      $obj = new $classname();
      $this->objectCache[$className] = $obj;
    }
    return $this->objectCache[$className];
  }

}