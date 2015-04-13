<?php
namespace MyORM;
/**
 * Thin wrapper around a DBDelegate object in order to use it as an
 * array. This function makes heavy use of PHP5's SPL extension
 * <code>ArrayIterator</code>, available at
 * <url>http://www.php.net/manual/en/class.arrayiterator.php</url>
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
class DBDelegate extends \ArrayIterator {

  private $row_num;
  private $result;
  private $current;

  // Delegate action
  private $action;
  
  public function __construct(\MySQLi_Result $result, DBDelegatable $action) {
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
    throw new \InvalidArgumentException("DBDelegate does not support setting values");
  }
}
?>