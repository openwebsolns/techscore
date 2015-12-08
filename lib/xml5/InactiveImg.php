<?php
namespace xml5;

use \XImg;

/**
 * "Inctive" image: a closed eye.
 *
 * @author Dayan Paez
 * @version 2015-12-08
 */
class InactiveImg extends XImg {

  const URL = '/inc/img/c-eye.png';
  const ALT = "Inactive";

  public function __construct() {
    parent::__construct(self::URL, self::ALT, array('title' => self::ALT));
  }
}