<?php

/**
 * A division, one of possibly four: A, B, C, and D. Used primarily
 * for type hinting.
 *
 * @author Dayan Paez
 * @version 2009-10-05
 */
class Division {

  private $value;
  public function __construct($val) {
    $this->value = $val;
  }
  public function value() {
    return $this->value;
  }
  public function __toString() {
    return $this->value;
  }

  /**
   * Returns the value for this division as a relative level, one of
   * "High", "Mid", or "Low".
   *
   * @param int $num the number of divisions participating
   * @return String the level
   */
  public function getLevel($num = 3) {
    if ($this->value == Division::A())
      return "High";
    if ($this->value == Division::D())
      return "Lowest";
    if ($this->value == Division::C())
      return "Low";
    // B division
    if ($num == 2)
      return "Low";
    return "Mid";
  }

  // Singletons
  private static $A;
  private static $B;
  private static $C;
  private static $D;

  // Static functions

  /**
   * Gets A division object
   *
   * @return A division
   */
  public static function A() {
    if (self::$A == null) {
      self::$A = new Division("A");
    }
    return self::$A;
  }
  /**
   * Gets B division object
   *
   * @return B division
   */
  public static function B() {
    if (self::$B == null) {
      self::$B = new Division("B");
    }
    return self::$B;
  }
  /**
   * Gets C division object
   *
   * @return C division
   */
  public static function C() {
    if (self::$C == null) {
      self::$C = new Division("C");
    }
    return self::$C;
  }
  /**
   * Gets D division object
   *
   * @return D division
   */
  public static function D() {
    if (self::$D == null) {
      self::$D = new Division("D");
    }
    return self::$D;
  }

  /**
   * Returns a list of divisions of the given size.
   *
   * @param int $num_divisions between 1 and 4.
   * @return Array a list of divisions, in order.
   * @throws InvalidArgumentException if invalid number requested.
   */
  public static function listOfSize($num_divisions) {
    if ($num_divisions < 1 || $num_divisions > 4) {
      throw new InvalidArgumentException("Invalid number of divisions requested: " . $num_divisions);
    }
    $list = array();
    $offset = ord('A');
    for ($i = 0; $i < $num_divisions; $i++) {
      $list[] = self::get(chr($offset + $i));
    }
    return $list;
  }

  /**
   * Gets the division object with the given value
   *
   * @param the division value to retrieve
   * @return the division object
   */
  public static function get($val) {
    switch ($val) {
    case "A":
      return self::A();
    case "B":
      return self::B();
    case "C":
      return self::C();
    case "D":
      return self::D();
    default:
      throw new InvalidArgumentException("Invalid division value: $val");
    }
  }

  /**
   * Fetches an associative array indexed by the value of the division
   * mapping to the division object
   *
   * @return Array
   */
  public static function getAssoc() {
    return array("A"=>Division::A(),
                 "B"=>Division::B(),
                 "C"=>Division::C(),
                 "D"=>Division::D());
  }
}
