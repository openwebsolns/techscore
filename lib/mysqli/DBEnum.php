<?php

/**
 * A constraint for database fields.
 *
 * @author Dayan Paez
 * @version 2016-03-31
 */
class DBEnum {

  private $allowedValues;
  private $isNullable;

  public function __construct(Array $allowedValues, $isNullable = true) {
    $this->allowedValues = $allowedValues;
    $this->isNullable = $isNullable !== false;
  }

  public function validate($value) {
    if ($value === null) {
      if (!$this->isNullable) {
        throw new InvalidArgumentException("Null not allowed.");
      }
      return;
    }
    if (!in_array($value, $this->allowedValues)) {
      throw new InvalidArgumentException(sprintf("\"%s\" not allowed (%s)", $value, implode(',', $this->allowedValues)));
    }
  }

}