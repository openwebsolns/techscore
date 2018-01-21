<?php
namespace eligibility;

use \InvalidArgumentException;

use \Conf;

/**
 * Creates delayed singleton EligibilityCalculator based on config.
 */
class EligibilityCalculatorFactory {
  private static $calculator = null;

  public static function build() {
    if (self::$calculator === null) {
      $classname = Conf::$ELIGIBILITY_CALCULATOR;
      if ($classname === null) {
        throw new InvalidArgumentException("No EligibilityCalculator configured");
      }
      self::$calculator = new $classname();
    }
    return self::$calculator;
  }
}