<?php
namespace ui;

use \XFieldSet;

/**
 * A fieldset object with class = 'filter'.
 *
 * @author Dayan Paez
 * @version 2015-05-06
 */
class FilterFieldset extends XFieldSet {

  public function __construct($title = "Filter options", Array $args = array()) {
    parent::__construct($title, $args);
    $this->set('class', 'filter');
  }
}