<?php
namespace xml5;

use \XImg;

/**
 * "Active" image: an open eye.
 *
 * @author Dayan Paez
 * @version 2015-12-08
 */
class ActiveImg extends XImg {

  const URL = '/inc/img/o-eye.png';
  const ALT = "Active";

  public function __construct() {
    parent::__construct(self::URL, self::ALT, array('title' => self::ALT));
  }
}