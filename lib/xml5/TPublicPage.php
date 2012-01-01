<?php
/*
 * This file is part of TechScore
 *
 * @package xml5
 */

require_once('xml5/TS.php');

/**
 * Public page for scores, written in the new and improved HtmlLib
 *
 * @author Dayan Paez
 * @version 2011-03-06
 */
class TPublicPage extends XPage {

  private $filled;
  private $menu;
  private $navigation;
  private $content;
  private $announce;  

  /**
   * Creates a new public page with the given title
   *
   * @param String $title the title of the page
   */
  public function __construct($title) {
    parent::__construct($title . " | TechScore: Real-Time Regatta Results");

    $this->filled = false;
    $this->menu = new XDiv(array('id'=>'menudiv'));
    $this->navigation = new XDiv(array('id'=>'topnav'));
    $this->content = new XDiv(array('id'=>'body'));
    $this->announce = new XDiv(array('id'=>'announcediv'));
  }

  /**
   * Fills the content of this page only once, according to the status
   * of the variable 'filled'
   *
   */
  private function fill() {
    if ($this->filled) return;

    // Stylesheets
    $this->head->add(new LinkCSS('/inc/css/mp.css'));

    // Navigation
    $this->body->add(new XDiv(array('class'=>'hidden'),
			      array(new XH3("Navigate"),
				    new XUl(array(),
					    array(new XLi(new XA('#menu', "Menu")),
						  new XLi(new XA('#body', "Content")))))));

    // Menu
    $this->body->add($this->menu);
    $this->body->add(new XHR(array('class'=>'hidden')));

    // Header
    $this->body->add($div = new XDiv(array('id'=>'headdiv')));
    $div->add($sub = new XDiv(array('id'=>'header')));
    $sub->add(new XH1(new XA(PUB_HOME, new XImg('/inc/img/techscore.png', "TechScore", array('id'=>'headimg')))));
    $sub->add(new XH4(date('M j, Y @ H:i:s'), array('id'=>'date')));
    $div->add($this->navigation);

    $this->body->add($this->content);
    $this->content->add($this->announce);

    // Footer
    $this->body->add(new XDiv(array('id'=>'footdiv'),
			      array(new XP(array(),
					   sprintf("TechScore v%s © Dayán Páez 2008-%s", VERSION, date('y'))))));

    $this->filled = true;
  }

  /**
   * Appends the given element to the content of the page
   *
   * @param Xmlable the element to add
   */
  public function addSection(Xmlable $elem) {
    $this->content->add($elem);
  }

  /**
   * Appends the givene element to the navigation
   *
   * @param Xmlable $elem the element to add
   */
  public function addNavigation(Xmlable $elem) {
    $this->navigation->add($elem);
  }

  /**
   * Appends the givene element to the menu
   *
   * @param Xmlable $elem the element to add
   */
  public function addMenu(Xmlable $elem) {
    $this->menu->add($elem);
  }

  /**
   * Delays the creation of the page and returns it as a string
   *
   * @return String the page
   */
  public function toXML() {
    $this->fill();
    return parent::toXML();
  }

  /**
   * Delays the creation of the page and echoes it to standard outpout
   *
   */
  public function printXML() {
    $this->fill();
    parent::printXML();
  }
}
?>