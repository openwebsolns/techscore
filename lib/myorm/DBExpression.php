<?php
namespace MyORM;

/**
 * Parent class for expressions. This class is EMPTY, and is used only for
 * typehinting, etc.
 *
 * @author Dayan Paez
 * @version 2010-08-20
 */
abstract class DBExpression {
  /**
   * Formats this expression recursively
   *
   * @return String with question mark place holders
   */
  abstract public function toSQL(\MySQLi $con);
}
?>