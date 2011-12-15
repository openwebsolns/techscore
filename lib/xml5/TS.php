<?php
/**
 * This file is part of TechScore
 */

require_once(dirname(__FILE__).'/HtmlLib.php');

/**
 * A div with class Port and an H3 heading
 *
 * @author Dayan Paez
 * @version 2011-03-09
 */
class XPort extends XDiv {

  /**
   * Create a port with the given title
   *
   * @param String $title the title
   */
  public function __construct($title, Array $children = array(), Array $attrs = array()) {
    parent::__construct($attrs, array($h3 = new XH3("")));
    if (is_array($title)) {
      foreach ($title as $item)
	$h3->add($item);
    }
    else
      $h3->add($title);
    $this->set('class', 'port');
    foreach ($children as $child)
      $this->add($child);
  }
}
?>