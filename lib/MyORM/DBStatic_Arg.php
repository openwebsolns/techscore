<?php
namespace MyORM;

/**
 * A static function parameter
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
class DBStatic_Arg implements DBFunction_Arg {

  private $value;

  /**
   * Creates a new argument with the given value
   *
   * @param mixed $value the value of this agument
   */
  public function __construct($value) {
    $this->value = $value;
  }

  /**
   * Returns this object's value
   *
   * @return mixed the value
   */
  public function format($param) {
    return $this->value;
  }
}
?>