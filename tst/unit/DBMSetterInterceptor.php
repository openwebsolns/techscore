<?php

/**
 * A sadly necessary safeguard against mucking with the database.
 *
 * This class can be used, instead of raw DBM, in order to prevent any
 * changes from actually happening to the database. Instead, all calls
 * to 'set' and 'insertAll' are tracked in-memory.
 *
 * @author Dayan Paez
 * @version 2016-03-07
 * @see DB::setDbm
 */
class DBMSetterInterceptor extends DBM {

  private static $objectsSet = array();

  public static function init() {
    self::$objectsSet = array();
  }

  public static function set(DBObject $obj, $update = "guess") {
    $class = get_class($obj);
    if (!array_key_exists($class, self::$objectsSet)) {
      self::$objectsSet[$class] = array();
    }
    self::$objectsSet[$class][] = $obj;
  }

  public static function getObjectsSet(DBObject $obj) {
    $class = get_class($obj);
    return array_key_exists($class, self::$objectsSet)
      ? self::$objectsSet[$class]
      : array();
  }

}