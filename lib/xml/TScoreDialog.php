<?php
/*
 * This file is part of TechScore
 *
 * @package xml
 */

require_once('xml/XmlLibrary.php');

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
    $this->menu->addAttr("id", "menudiv");
    $this->addBody($this->menu);
    $this->addBody(new GenericElement("hr", array(), array("class"=>"hidden")));
    $this->addBody($this->header = new Div());

    // Header
    $this->header->addAttr("id", "headdiv");
    $this->fillPageHeader();

    // Bottom grab/spacer
    $this->addBody($div = new Div());
    $div->addAttr("id", "bottom-grab");
    $div->addChild(new Text());

    // Announcement
    $this->addBody($this->announce = new Div());
    $this->announce->addAttr("id", "announcediv");
    $this->announce->addChild(new Text());

    // Content
    $this->addBody($this->content = new Div());
    $this->content->addAttr("id", "bodydiv");
  }

  /**
   * Fills the head element of this page
   *
   */
  private function fillHead($title) {
    $this->head->addChild(new GenericElement("title",
					     array(new Text($title))));

    // CSS Stylesheets
    $this->head->addChild(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "title"=>"Modern Tech",
						   "media"=>"screen",
						   "href"=>"/inc/css/modern-dialog.css")));
    $this->head->addChild(new GenericElement("link",
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
      $this->head->addChild(new GenericElement("script",
					       array(new Text("")),
					       array("type"=>"text/javascript",
						     "src"=>"/inc/js/" . $scr)));
    }
  }

  /**
   * Creates the header of this page
   *
   */
  private function fillPageHeader() {
    $this->header->addChild($div = new Div());
    $div->addAttr("id", "header");
    $div->addChild($g = new GenericElement("h1"));
    $g->addChild(new Image("/img/techscore-small.png", array("id"=>"headimg",
							    "alt"=>"TechScore")));
    $div->addChild(new Heading(date("D M j, Y"), array("id"=>"date")));
    
    $this->header->addChild($this->navigation = new Div());
    $this->navigation->addAttr("id", "topnav");
    $this->navigation->addChild(new Link("../help", "Help?",
					 array("id"=>"help","target"=>"_blank")));
  }

  /**
   * Adds the HTMLElement to the content of this page
   *
   * @param HTMLElement $elem an element to append to the body of this
   * page
   */
  public function addContent(HTMLElement $elem) {
    $this->content->addChild($elem);
  }

  /**
   * Adds the given element to the menu division of this page
   *
   * @param HTMLElement $elem to add to the menu of this page
   */
  public function addMenu(HTMLElement $elem) {
    $this->menu->addChild($elem);
  }

  /**
   * Adds the given element to the page header
   *
   * @param HTMLElement $elem to add to the page header
   */
  public function addHeader(HTMLElement $elem) {
    $this->header->addChild($elem);
  }

  /**
   * Adds the given element to the navigation part
   *
   * @param HTMLElement $elem to add to navigation
   */
  public function addNavigation(HTMLElement $elem) {
    $this->navigation->addChild($elem);
  }

  /**
   * Adds the given announcement to the page
   *
   * @param Announce $elem the announcement to add
   */
  public function addAnnouncement(Announcement $elem) {
    $this->announce->addChild($elem);
  }

  /**
   * Overrides parent method to include footer
   *
   */
  public function toHTML($ind = 0) {
    // Footer
    $this->content->addChild($footer = new Div());
    $footer->addAttr("id", "footdiv");
    $footer->addChild(new Para(sprintf("TechScore v%s © Dayán Páez 2008-%s", VERSION, date('y'))));

    return parent::toHTML();
  }
}
?>