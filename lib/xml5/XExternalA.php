<?php
namespace xml5;

use \XA;

/**
 * A link that opens "in a different tab".
 *
 * @author Dayan Paez
 * @version 2015-11-05
 */
class XExternalA extends XA {

  const CLASSNAME = 'external-link';

  public function __construct($href, $link) {
    parent::__construct(
      $href,
      $link,
      array(
        'onclick' => 'this.target="new"',
        'class' => self::CLASSNAME,
        'title' => "Opens in a new window"
      )
    );
  }
}
