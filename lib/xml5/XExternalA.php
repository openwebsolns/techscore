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
  public function __construct($href, $link) {
    parent::__construct(
      $href,
      $link,
      array(
        'onclick' => 'this.target="new"',
        'class' => 'external-link',
        'title' => "Opens in a new window"
      )
    );
  }
}
