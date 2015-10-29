<?php
namespace ui;

use \DB;
use \FCheckbox;

/**
 * A checkbox specifically for an STN-backed setting.
 *
 * @author Dayan Paez
 * @version 2015-10-29
 */
class StnCheckbox extends FCheckbox {

  /**
   * Creates a new checkbox based on setting value.
   *
   * @param Const $setting one of the STN constants.
   * @param String $label the label to use.
   */
  public function __construct($setting, $label) {
    parent::__construct($setting, 1, $label, DB::g($setting) !== null);
  }

}