<?php
namespace ui;

use \XP;
use \XSpan;
use \XA;

require_once('xml5/HtmlLib.php');

/**
 * A collection of links delineating stages in a multi-page application.
 *
 * @author Dayan Paez
 * @version 2015-03-2
 */
class ProgressDiv extends XP {

  /**
   * Create a new progress section.
   *
   */
  public function __construct() {
    parent::__construct(array('id'=>'progressdiv'));
  }

  /**
   * Append a new stage.
   *
   * @param String $title the title for the stage.
   * @param String $link the optional link, if any.
   * @param boolean $current true if the stage is current.
   * @param boolean $completed true to add 'completed' flag. Ignored if current.
   */
  public function addStage($title, $link = null, $current = false, $completed = false) {
    $elem = null;
    if ($link !== null) {
      $elem = new XA($link, $title);
    }
    else {
      $elem = new XSpan($title);
    }

    if ($current !== false) {
      $elem->set('class', 'current');
    }
    elseif ($completed !== false) {
      $elem->set('class', 'completed');
    }

    $this->add($elem);
  }

  /**
   * Convenience method to addStage.
   *
   * @param String $title the title for the stage.
   * @param String $link the optional link, if any.
   */
  public function addCurrent($title, $link = null) {
    $this->addStage($title, $link, true);
  }

  /**
   * Convenience method to addStage.
   *
   * @param String $title the title for the stage.
   * @param String $link the optional link, if any.
   */
  public function addCompleted($title, $link = null) {
    $this->addStage($title, $link, false, true);
  }
}