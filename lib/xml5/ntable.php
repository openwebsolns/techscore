<?php
/**
 * This API extends PHP's strong array infrastructure to support hash
 * tables or maps with any number of corresponding columns. This is
 * best done by extending the abstract map below, and overriding the
 * 'columns' field.
 *
 * @author Dayan Paez
 * @created 2011-08-31
 * @package php
 */
abstract class NTable extends ArrayIterator {
  /**
   * @var Array the name of the columns. Child methods must set this
   * variable, and it must not be an associative array.
   */
  protected $colnames = array();

  /**
   * @var Array the default value for the columns. If present, this
   * array should be of the same size as $colnames. If left null,
   * then 'null' will be used as the default value for columns not
   * provided in 'pop', 'push' and similar functions. Make sure to
   * change this value BEFORE the constructor is called.
   */
  protected $defaults = null;

  /**
   * @var Array the actual columns
   */
  protected $columns = array();

  /**
   * Crates a new map. The number of arguments provided must equal the
   * size of the map. If arrays are provided, they must all be the
   * same size. At least one argument is required.
   *
   * @throws RuntimeException
   */
  public function __construct() {
    if (count($this->colnames) < 1)
      throw new RuntimeException("Map must contain at least one column!");
    foreach ($this->colnames as $n)
      $this->columns[$n] = array();
    
    if ($this->defaults === null)
      $this->defaults = array();
    while (count($this->defaults) < count($this->colnames))
      $this->defaults[] = null;
  }

  private function retfunc($callback) {
    $ret = array();
    foreach ($this->colnames as $n)
      $ret[$n] = $callback($this->columns[$n]);
    return $ret;
  }

  private function dofunc($callback, Array $args) {
    $nargs = count($args);
    if ($nargs == 0)
      throw new RuntimeException("At least one item must be provided.");

    foreach ($this->colnames as $i => $n) {
      $arg = (array_key_exists($i, $args)) ? $args[$i] : $this->defaults[$i];
      $callback($this->columns[$n], $arg);
    }
  }

  /**
   * Pushes the arguments to each respective list. Their number must
   * match
   *
   * @param mixed $elem the first element to add
   * @param ...
   */
  public function push() {
    $this->dofunc('array_push', func_get_args());
  }

  /**
   * Pops the arguments to each respective list.
   *   
   * @return Array the popped items
   */
  public function pop() {
    return $this->retfunc('array_pop');
  }

  public function shift() {
    return $this->retfunc('array_shift');
  }

  public function unshift() {
    $this->dofunc('array_unshift', func_get_args());
  }

  // iterator stuff
  private $pos = 0;

  public function rewind() {
    $this->pos = 0;
  }
  public function current() {
    $ret = array();
    foreach ($this->columns as $n => $val)
      $ret[$n] = $val[$this->pos];
    return $ret;
  }
  public function key() {
    return $this->pos;
  }
  public function next() {
    $this->pos++;
  }
  public function valid() {
    return ($this->pos < $this->count());
  }
  public function count() {
    return count($this->columns[$this->colnames[0]]);
  }
  public function seek($pos) {
    if ($pos >= $this->count())
      throw new OutOfBoundsException("Invalid seek index provided $pos.");
    $this->pos = $pos;
  }
  public function offsetExists($index) {
    return ($index < $this->count() && $index >= 0);
  }
  public function offsetGet($index) {
    if (!$this->offsetExists($index))
      throw new OutOfBoundsException("No such offset exists $index.");
    $ret = array();
    foreach ($this->columns as $n => $c)
      $ret[$n] = $c[$index];
    return $ret;
  }
  public function offsetSet($index, $newvalue) {
    echo 'called with args count: ' . func_num_args();
  }

  /**
   * Allows fetching columns as properties. Note that this is an
   * array, and changes made to it are not reflected in the map
   *
   * @return Array the column
   * @throws InvalidArgumentException if no such column exists
   */
  public function __get($name) {
    if (array_key_exists($name, $this->columns))
      return $this->columns[$name];
    throw new InvalidArgumentException("Invalid column name $name.");
  }

  /**
   * Allows direct access to one of the colums to edit one of its
   * pre-existing values
   *
   */
  public function set($colname, $index, $value) {
    if (!array_key_exists($colname, $this->columns))
      throw new RuntimeException("Invalid column specified $colname.");
    if (!$this->offsetExists($index))
      throw new OutOfBoundsException("Invalid offset provided $index.");
    $this->columns[$colname][$index] = $value;
  }
}
?>