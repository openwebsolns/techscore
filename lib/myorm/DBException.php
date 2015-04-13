<?php
namespace MyORM;

/**
 * Database serialization errors
 *
 * @author Dayan Paez
 * @version 2010-06-11
 */
class DBException extends Exception {
  public function __construct($mes = "") {
    parent::__construct($mes);
  }
}
?>