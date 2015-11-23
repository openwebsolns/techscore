<?php
namespace xml5;

use \XSpan;

/**
 * Span used as prefix for form items.
 *
 * @author Dayan Paez
 * @version 2015-11-23
 */
class FormGroupHeader extends XSpan {

  const CLASSNAME = 'span_h';

  public function __construct($value) {
    parent::__construct("", array('class' => self::CLASSNAME));
    if (!is_array($value)) {
      $value = array($value);
    }
    foreach ($value as $v) {
      $this->add($v);
    }
  }
}