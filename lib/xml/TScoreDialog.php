<?php
/*
 * This file is part of TechScore
 *
 * @package xml
 */

require_once('xml/XmlLibrary.php');
require_once('xml5/TS.php');

/**
 * The basic HTML page for TechScore dialogs. This page is a
 * GenericElement and it extends the WebPage class found in the
 * XmlLibrary. It includes facilities for adding items to the menu,
 * and content.
 *
 * @author Dayan Paez
 * @version 2.0
 * @version 2010-01-13
 */
class TScoreDialog extends WebPage {

  // Private variables
  private $header;
  private $navigation;
  private $menu;
  private $content;
  private $announce;

  /**
   * Creates a new page with the given title
   *
   * @param String $title the title of the page
   */
  public function __construct($title) {
    parent::__construct();
    $this->fillHead((string)$title);

    // Menu
    $this->menu = new Div();
    $this->menu->set("id", "menudiv");
    $this->addBody($this->menu);
    $this->addBody(new GenericElement("hr", array(), array("class"=>"hidden")));
    $this->addBody($this->header = new Div());

    // Header
    $this->header->set("id", "headdiv");
    $this->fillPageHeader();

    // Bottom grab/spacer
    $this->addBody($div = new Div());
    $div->set("id", "bottom-grab");
    $div->add(new XText());

    // Announcement
    $this->addBody($this->announce = new Div());
    $this->announce->set("id", "announcediv");
    $this->announce->add(new XText());

    // Content
    $this->addBody($this->content = new Div());
    $this->content->set("id", "bodydiv");
  }

  /**
   * Fills the head element of this page
   *
   */
  private function fillHead($title) {
    $this->head->add(new GenericElement("title",
					     array(new XText($title))));

    // CSS Stylesheets
    $this->head->add(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "title"=>"Modern Tech",
						   "media"=>"screen",
						   "href"=>"/inc/css/modern-dialog.css")));
    $this->head->add(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "media"=>"print",
						   "href"=>"/inc/css/print.css")));
    // Javascript
    foreach (array("jquery-1.3.min.js",
		   "jquery.tablehover.min.js",
		   "jquery.columnmanager.min.js",
		   "refresher.js") as $scr) {
      $this->head->add(new GenericElement("script",
					       array(new XText("")),
					       array("type"=>"text/javascript",
						     "src"=>"/inc/js/" . $scr)));
    }
  }

  /**
   * Creates the header of this page
   *
   */
  private function fillPageHeader() {
    $this->header->add($div = new Div());
    $div->set("id", "header");
    $div->add($g = new GenericElement("h1"));
    $g->add(new XImg("/img/techscore-small.png", "TechScore", array("id"=>"headimg")));
    $div->add(new XH4(date("D M j, Y"), array("id"=>"date")));
    
    $this->header->add($this->navigation = new Div());
    $this->navigation->set("id", "topnav");
    $this->navigation->add(new XA("../help", "Help?",
					 array("id"=>"help","target"=>"_blank")));
  }

  /**
   * Adds the HTMLElement to the content of this page
   *
   * @param HTMLElement $elem an element to append to the body of this
   * page
   */
  public function addContent(HTMLElement $elem) {
    $this->content->add($elem);
  }

  /**
   * Adds the given element to the menu division of this page
   *
   * @param HTMLElement $elem to add to the menu of this page
   */
  public function addMenu(HTMLElement $elem) {
    $this->menu->add($elem);
  }

  /**
   * Adds the given element to the page header
   *
   * @param HTMLElement $elem to add to the page header
   */
  public function addHeader(HTMLElement $elem) {
    $this->header->add($elem);
  }

  /**
   * Adds the given element to the navigation part
   *
   * @param HTMLElement $elem to add to navigation
   */
  public function addNavigation(HTMLElement $elem) {
    $this->navigation->add($elem);
  }

  /**
   * Adds the given announcement to the page
   *
   * @param Announce $elem the announcement to add
   */
  public function addAnnouncement(Announcement $elem) {
    $this->announce->add($elem);
  }

  /**
   * Overrides parent method to include footer
   *
   */
  public function toXML($ind = 0) {
    // Footer
    $this->content->add($footer = new Div());
    $footer->set("id", "footdiv");
    $footer->add(new XP(array(), sprintf("TechScore v%s © Dayán Páez 2008-%s", VERSION, date('y'))));

    return parent::toXML();
  }
}
?>