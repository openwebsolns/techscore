<?php

/**
 * Grouping of sail numbers and colors.
 *
 * Objects of this class are meant to be serialized to the database,
 * and the class is intentionally kept lightweight.
 *
 * @author Dayan Paez
 * @created 2013-05-16
 */
class SailsList {

  private $_count;

  /**
   * @var Array the list of sails
   */
  protected $sails = array();
  /**
   * @var Array the corresponding list of colors
   */
  protected $colors = array();

  public function __get($name) {
    if (property_exists($this, $name))
      return $this->$name;
    throw new InvalidArgumentException("Invalid property requested: $name.");
  }

  public function __set($name, $value) {
    if (!property_exists($this, $name) || $name == '_count')
      throw new InvalidArgumentException("Invalid property requested: $name.");
    if (!is_array($value))
      throw new InvalidArgumentException("Invalid value for $name, expected Array.");
    if ($this->_count !== null && $this->_count != count($value))
      throw new InvalidArgumentException(sprintf("Count for %s must be %s.", $name, $this->_count));

    $this->$name = $value;
    $this->_count = count($value);
  }

  public function count() {
    return (int)$this->_count;
  }

  public function __sleep() {
    return array('sails', 'colors');
  }

  public function __wakeup() {
    foreach ($this->__sleep() as $prop) {
      $count = count($this->$prop);
      if ($count != 0) {
        if ($this->_count !== null && $this->_count != $count)
          throw new InvalidArgumentException("Property array sizes do not match.");
        $this->_count = $count;
      }
    }
  }

  /**
   * Shelter client code from discrepancies in internal representation
   *
   */
  public function sailAt($i) {
    if (isset($this->sails[$i]))
      return $this->sails[$i];
    return null;
  }
  public function colorAt($i) {
    if (isset($this->colors[$i]))
      return $this->colors[$i];
    return null;
  }

  public function getSailObjects() {
    $list = array();
    for ($i = 0; $i < $this->count(); $i++) {
      $sail = new Sail();
      $sail->sail = $this->sailAt($i);
      $sail->color = $this->colorAt($i);
      $list[] = $sail;
    }
    return $list;
  }
}
