<?php
namespace xml5;

use \XDiv;
use \Xmlable;

/**
 * A DIV wrapped around some HTML, with a class of its own.
 *
 * @author Dayan Paez
 * @version 2016-03-30
 */
class XHtmlPreview extends XDiv {

  const CLASSNAME = 'html-preview-div';

  public function __construct(Xmlable $html = null) {
    parent::__construct(array('class' => self::CLASSNAME));
    if ($html !== null) {
      $this->add($html);
    }
  }
}