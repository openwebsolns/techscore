<?php
namespace MyORM;

/**
 * A field-based argument. Returns the result of fetching the given field
 * from the passed parameter
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
class DBField_Arg implements DBFunction_Arg {

  private $field;

  /**
   * Creates a new field-based argument
   *
   * @param String $field the field to use in the passed parameter to
   * create the appropriate argument
   */
  public function __construct($field) {
    $this->field = (string)$field;
  }

  /**
   * Returns the 'field' for the given parameter
   *
   * @param mixed $param the object whose field to fetch
   * @return mixed the result
   */
  public function format($param) {
    $field = $this->field;
    return $param->$field;
  }
}
?>