<?php
namespace xml5;

use \XNumberInput;

/**
 * Number input for years.
 *
 * @author Dayan Paez
 * @version 2015-11-23
 */
class GraduationYearInput extends XNumberInput {

  const MINIMUM = 1986;
  const MAXIMUM_PADDING = 10;

  public function __construct($name, $value, Array $attrs = array()) {
    parent::__construct(
      $name,
      $value,
      self::MINIMUM,
      date('Y') + self::MAXIMUM_PADDING,
      1,
      $attrs
    );
  }
}