<?php
/**
 * Utilities for MySQLi convenience and efficiency. The main class in
 * this library, <code>MySQLi_delegate</code>, was born out of a need
 * to abstract away the MySQL dependency by giving client code the
 * ability to use the result of a query as if it were a native PHP
 * array, for the most part.
 *
 * The efficiency lies in the fact that the data itself is not pulled
 * from the database until it is directly accessed in the array.
 *
 * @author Dayan Paez
 * @version 2010-05-14
 * @package mysql
 */

/**
 * Simulates the explode function, but can use any ArrayIterator
 * object instead.
 *
 * @author Dayan Paez
 * @version 2010-12-14
 *
 * @param String $glue the in-between string
 * @param Array|ArrayIterator $array the values
 * @return String the $array elements cast as a string, with $glue in
 * between.
 */
function ai_implode($glue, $array) {
  $cnt = count($array);
  if ($cnt == 0) return '';
  $t = (string)$array[0];
  for ($i = 1; $i < $cnt; $i++) {
    $t .= $glue;
    $t .= (string)$array[$i];
  }
  return $t;
}

/**
 * Thin wrapper around a MySQLi_Result object in order to use it as an
 * array. This function makes heavy use of PHP5's SPL extension
 * <code>ArrayIterator</code>, available at
 * <url>http://www.php.net/manual/en/class.arrayiterator.php</url>
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
class MySQLi_Delegate extends ArrayIterator {

  private $row_num;
  private $result;
  private $current;

  // Delegate action
  private $action;
  
  public function __construct(MySQLi_Result $result, MySQLi_Delegatable $action) {
    $this->result = $result;
    $this->row_num = 0;
    $this->action = $action;
  }

  public function __destruct() {
    $this->result->free();
  }

  // Implementation of iterator

  public function rewind() {
    $this->seek(0);
  }

  public function current() {
    return $this->current;
  }

  public function key() {
    return $this->row_num;
  }

  public function next() {
    $this->seek($this->row_num + 1);
  }

  public function valid() {
    return ($this->current !== false);
  }

  // Implementation of Countable

  public function count() {
    return $this->result->num_rows;
  }

  // Implementation of SeekableIterator

  public function seek($position) {
    $this->row_num = $position;
    $b = $this->result->data_seek($position);
    if ($b === false)
      $this->current = false;
    else
      $this->current = $this->action->delegate_current($this->result);
  }
  
  public function offsetExists($index) {
    return true;
  }
  public function offsetGet($index) {
    $this->seek($index);
    return $this->current();
  }
  public function offsetSet($index, $val) {
    throw new InvalidArgumentException("MySQLi_delegate does not support setting values");
  }
}

/**
 * Interface for delegate actions
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
interface MySQLi_Delegatable {
  
  /**
   * This method is responsible for fetching the current value from
   * the MySQLi_Result, formatting it as required, and returning the
   * result. Instance methods could, for instance, return the result
   * as an object of a specific type, by calling the appropriate
   * <code>fetch_object</code> method in MySQLi_Result
   *
   * @param MySQLi_Result $pointer the pointer at which to fetch the
   * current value
   * @return mixed the resulting object or array
   */
  public function delegate_current(MySQLi_Result $pointer);
}

/**
 * Fetches results as objects of the given type
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
class MySQLi_Object_Delegate implements MySQLi_Delegatable {

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
  public function delegate_current(MySQLi_Result $pointer) {
    return $pointer->fetch_object($this->object_type, $this->object_args);
  }
}

/**
 * Returns the result of calling the method for the provided class
 * using the given field as arguments. For instance, suppose there is
 * a class 'M' (object '$M') with method 'fetch($type, $int)' where
 * '$type' is a constant and '$int' is one of the fields in the result
 * set called 'id' (known as the field). This delegate would return
 * the result of issuing:
 *
 * <pre>
 * $r = MySQLi_Result->fetch_object();
 * $M->fetch($type, $r->$field);
 * </pre>
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
class MySQLi_Function_Delegate implements MySQLi_Delegatable {

  private $callback;
  private $args;

  /**
   * Creates a new delegate which returns the results of issuing
   * callback with the given arguments
   *
   * @param callback $function the callback to use
   * @param Array<MySQLi_Function_Arg> the arguments to issue
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
   * @param MySQLi_Function_Arg $arg the argument to add
   */
  public function addArg(MySQLi_Function_Arg $arg) {
    $this->args[] = $arg;
  }

  /**
   * Returns the result of calling for query using the callback
   *
   * @return mixed the result of the callback function, or NULL
   */
  public function delegate_current(MySQLi_Result $pointer) {
    $current = $pointer->fetch_object();
    if ($current === null) return null;

    // Prepare the arguments
    $args = array();
    foreach ($this->args as $arg)
      $args[] = $arg->format($current);
    return call_user_func_array($this->callback, $args);
  }
}

/**
 * Interface for creating arguments to a function using the
 * information from a given object. See <code>MySQLi_Static_Arg</code>
 * and <code>MySQLi_Field_Arg</code> for concrete examples
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
interface MySQLi_Function_Arg {
  
  /**
   * Format and return the argument based on the information from the
   * parameter
   *
   * @param mixed $param the parameter to use
   * @return mixed the argument
   */
  public function format($param);
}

/**
 * A static function parameter
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
class MySQLi_Static_Arg implements MySQLi_Function_Arg {

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

/**
 * A field-based argument. Returns the result of fetching the given field
 * from the passed parameter
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
class MySQLi_Field_Arg implements MySQLi_Function_Arg {

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