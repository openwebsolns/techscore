<?php
namespace MyORM;

/**
 * Returns the result of calling the method for the provided class
 * using the given field as arguments. For instance, suppose there is
 * a class 'M' (object '$M') with method 'fetch($type, $int)' where
 * '$type' is a constant and '$int' is one of the fields in the result
 * set called 'id' (known as the field). This delegate would return
 * the result of issuing:
 *
 * <pre>
 * $r = DBDelegate->fetch_object();
 * $M->fetch($type, $r->$field);
 * </pre>
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
class DBFunction_Delegate implements DBDelegatable {

  private $callback;
  private $args;

  /**
   * Creates a new delegate which returns the results of issuing
   * callback with the given arguments
   *
   * @param callback $function the callback to use
   * @param Array<DBFunction_Arg> the arguments to issue
   */
  public function __construct($callback, Array $args = array()) {
    $this->callback = $callback;
    $this->args = array();
    foreach ($args as $arg)
      $this->addArg($arg);
  }

  /**
   * Adds the given argument to the function call
   *
   * @param DBFunction_Arg $arg the argument to add
   */
  public function addArg(DBFunction_Arg $arg) {
    $this->args[] = $arg;
  }

  /**
   * Returns the result of calling for query using the callback
   *
   * @return mixed the result of the callback function, or NULL
   */
  public function delegate_current(\MySQLi_Result $pointer) {
    $current = $pointer->fetch_object();
    if ($current === null) return null;

    // Prepare the arguments
    $args = array();
    foreach ($this->args as $arg)
      $args[] = $arg->format($current);
    return call_user_func_array($this->callback, $args);
  }
}
?>