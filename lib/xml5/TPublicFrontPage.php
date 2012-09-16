<?php
/*
 * This file is part of TechScore
 *
 * @package xml5
 */

require_once('TPublicPage.php');

/**
 * Specific page for front page, written in the new and improved HtmlLib.
 *
 * @author Dayan Paez
 * @version 2011-03-06
 */
class TPublicFrontPage extends TPublicPage {

  /**
   * Creates a new public page with the given title
   *
   * @param String $title the title of the page
   */
  public function __construct() {
    parent::__construct("Scores");
    $this->setDescription("Official site for live regatta results of the Intercollegiate Sailing Association.");
  }

  protected function getCSS() {
    return array('/inc/css/icsa-front.css');
  }
}
?>