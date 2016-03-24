<?php
namespace xml5;

use \XA;
use \XDiv;
use \XH4;
use \XLi;
use \XUl;

/**
 * A header and a list of links.
 *
 * @author Dayan Paez
 * @version 2016-03-24
 */
class MainMenuList extends XDiv {

  const CLASSNAME = 'menu';

  public function __construct($title, Array $links) {
    parent::__construct(
      array('class' => self::CLASSNAME),
      array(
        new XH4($title),
        $ul = new XUl()
      )
    );
    foreach ($links as $url => $href) {
      $ul->add(new XLi(new XA($url, $href)));
    }
  }

}