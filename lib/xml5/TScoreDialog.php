<?php
/*
 * This file is part of TechScore
 *
 * @package xml
 */

require_once('xml5/TS.php');

/**
 * The basic HTML page for TechScore dialogs. This pmage is an XPage
 * and it extends the XPage class found in the HtmlLib. It includes
 * facilities for adding items to the menu, and content.
 *
 * @author Dayan Paez
 * @version 2.0
 * @version 2010-01-13
 */
class TScoreDialog extends XPage {

  // Containers for Xmlable
  private $header;
  private $menu;
  private $content;

  private $filled;

  /**
   * Creates a new page with the given title
   *
   * @param String $title the title of the page
   */
  public function __construct($title) {
    parent::__construct((string)$title . " | " . Conf::$NAME);
    $this->filled = false;
    $this->menu = array();
    $this->header = array();
    $this->content = array();
  }

  private function fill() {
    if ($this->filled) return;
    $this->filled = true;

    // HTML HEAD element
    // Favicon the W3C way
    $this->head->add(new XLink(array('rel'=>'icon', 'type'=>'image/x-icon', 'href'=>WS::link('/inc/img/favicon.ico'))));
    $this->head->set('profile', 'http://www.w3.org/2005/10/profile');

    // CSS Stylesheets
    $this->head->add(new LinkCSS('/inc/css/modern-dialog.css'));
    $this->head->add(new LinkCSS('/inc/css/print.css','print'));

    // Javascript
    foreach (array("jquery-1.3.min.js",
                   "jquery.tablehover.min.js") as $scr) {
      $this->head->add(new XScript('text/javascript', "/inc/js/$scr"));
    }

    // Header
    $this->body->add($div = new XDiv(array('id'=>'headdiv')));
    $div->add(new XH1(new XImg("/inc/img/techscore.png", Conf::$NAME, array("id"=>"headimg"))));
    $div->add(new XH4(date("D M j, Y"), array("id"=>"date")));
    foreach ($this->header as $sub)
      $div->add($sub);

    // Menu
    $this->body->add(new XHr(array("class"=>"hidden")));
    $this->body->add($div = new XDiv(array('id'=>'menudiv')));
    foreach ($this->menu as $sub)
      $div->add($sub);

    // Content
    $this->body->add($c = new XDiv(array('id'=>'bodydiv')));

    // Announcement
    if (class_exists('Session', false))
      $c->add(Session::getAnnouncements('/inc/img'));
    foreach ($this->content as $cont)
      $c->add($cont);

    $this->body->add(new XDiv(array('id'=>'footdiv'),
                              array(new XP(array(), sprintf("%s v%s %s", Conf::$NAME, Conf::$VERSION, Conf::$COPYRIGHT)))));
  }

  /**
   * Adds the Xmlable to the content of this page
   *
   * @param Xmlable $elem an element to append to the body of this
   * page
   */
  public function addContent(Xmlable $elem) {
    $this->content[] = $elem;
  }

  /**
   * Adds the given element to the menu division of this page
   *
   * @param Xmlable $elem to add to the menu of this page
   */
  public function addMenu(Xmlable $elem) {
    $this->menu[] = $elem;
  }

  /**
   * Adds the given element to the page header
   *
   * @param Xmlable $elem to add to the page header
   */
  public function addHeader(Xmlable $elem) {
    $this->header[] = $elem;
  }

  public function toXML() {
    $this->fill();
    return parent::toXML();
  }
  public function printXML() {
    $this->fill();
    parent::printXML();
  }
}
?>