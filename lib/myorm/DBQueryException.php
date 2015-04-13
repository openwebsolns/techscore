<?php
namespace MyORM;

/**
 * Query exceptions unique to the creation of queries
 *
 * @author Dayan Paez
 * @version 2010-06-11
 */
class DBQueryException extends \Exception {
  public function __construct($mes) {
    parent::__construct($mes);
  }
}
?>