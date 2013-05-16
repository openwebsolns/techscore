<?php
/*
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */

/**
 * Grouping of sail numbers and colors for a team regatta
 *
 * This provides TS the ability to remember what rotation(s) have been
 * utilized in a given regatta.
 *
 * Objects of this class are meant to be serialized to the database,
 * and the class is intentionally kept lightweight.
 *
 * @author Dayan Paez
 * @created 2013-05-16
 */
class TeamRotation {
  private $_count;

  /**
   * @var Array the list of sails for the first team
   */
  protected $sails1 = array();
  /**
   * @var Array the list of sails for the second team
   */
  protected $sails2 = array();
  /**
   * @var Array the list of colors for the first team
   */
  protected $colors1 = array();
  /**
   * @var Array the list of colors for the second team
   */
  protected $colors2 = array();

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
    return array('sails1', 'sails2', 'colors1', 'colors2');
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
}
?>