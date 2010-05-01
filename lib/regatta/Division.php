<?php
/**
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */
require_once('conf.php');

/**
 * A division, one of possibly four: A, B, C, and D. Used primarily
 * for type hinting.
 *
 * @author Dayan Paez
 * @created 2009-10-05
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

  // Static variables
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
      throw new IllegalArgumentError("Invalid division value");
    }
  }
}
?>