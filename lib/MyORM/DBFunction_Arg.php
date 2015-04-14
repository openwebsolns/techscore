<?php
namespace MyORM;
/**
 * Interface for creating arguments to a function using the
 * information from a given object. See <code>DBStatic_Arg</code>
 * and <code>DBField_Arg</code> for concrete examples
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
interface DBFunction_Arg {
  
  /**
   * Format and return the argument based on the information from the
   * parameter
   *
   * @param mixed $param the parameter to use
   * @return mixed the argument
   */
  public function format($param);
}
?>