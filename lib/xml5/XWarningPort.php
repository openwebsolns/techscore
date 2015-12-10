<?php
namespace xml5;

use \XPort;

/**
 * A special port with extra class.
 *
 * @author Dayan Paez
 * @version 2015-12-10
 */
class XWarningPort extends XPort {

  const WARNING_CLASSNAME = 'warning-port';

  public function __construct($title, Array $children = array(), Array $attrs = array()) {
    parent::__construct($title, $children, $attrs);
    $this->set('class', array(self::CLASSNAME, self::WARNING_CLASSNAME));
  }
}