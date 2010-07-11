<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package xml
 */
require_once('conf.php');
__autoload('XmlLibrary');

/**
 * The basic HTML page for TechScore files. This page extends the
 * WebPage class and sets up the necessary structure common to all the
 * pages: headers, footers, contents, and announcements.
 *
 * @author Dayan Paez
 * @version 2.0
 * @created 2009-10-19
 */
class TScorePage extends WebPage {

  // Private variables
  private $header;
  private $navigation;
  private $menu;
  private $content;
  private $announce;

  private $mobile;

  /**
   * Creates a new page with the given title
   *
   * @param String $title the title of the page
   * @param Regatta $reg the regatta in question
   */
  public function __construct($title) {
    parent::__construct();
    $this->mobile = $this->isMobile();

    $this->fillHead($title);

    // Menu
    if ($this->mobile) {
      $this->addBody(new GenericElement("button", array(new Text("Menu")),
					array("onclick"=>"toggleMenu()")));
    }
    $this->menu = new Div();
    $this->menu->addAttr("id", "menudiv");
    $this->addBody($this->menu);
    $this->addBody(new GenericElement("hr", array(), array("class"=>"hidden")));
    $this->addBody($this->header = new Div());

    // Header
    $this->header->addAttr("id", "headdiv");
    $this->fillPageHeader();

    // Content
    $this->addBody($this->content = new Div());
    $this->content->addAttr("id", "bodydiv");

    // Announcement
    $this->content->addChild($this->announce = new Div());
    $this->announce->addAttr("id", "announcediv");
    $this->announce->addChild(new Text());

    // Footer
    $this->addBody($footer = new Div());
    $footer->addAttr("id", "footdiv");
    $footer->addChild(new Para(sprintf("TechScore v%s &copy; Day&aacute;n P&aacute;ez 2008-9",
				       VERSION)));

    // Fill announcement
    if (is_array($_SESSION['ANNOUNCE'])) {
      while (count($_SESSION['ANNOUNCE']) > 0)
	$this->addAnnouncement(array_shift($_SESSION['ANNOUNCE']));
    }
  }

  /**
   * Determines whether the page is being accessed through a mobile
   * device
   *
   */
  private function isMobile() {
    return (strpos($_SERVER['HTTP_USER_AGENT'], "Android") !== false ||
	    strpos($_SERVER['HTTP_USER_AGENT'], "iPhone")  !== false);
  }

  /**
   * Fills up the head element of this page
   *
   */
  private function fillHead($title) {
    $this->head->addChild(new GenericElement("title",
					     array(new Text($title))));
    $base = new GenericElement("base",
			       array(),
			       array("href"=>(HOME . "/")));
    $this->head->addChild($base);

    // Shortcut icon
    $this->head->addChild(new GenericElement("link",
					     array(),
					     array("rel"=>"shortcut icon",
						   "href"=>"img/t.ico",
						   "type"=>"image/x-icon")));

    // CSS Stylesheets
    /*
    $this->head->addChild(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "title"=>"Tech",
						   "media"=>"screen",
						   "href"=>"inc/css/tech.css")));
    */
    if ($this->mobile) {
      $this->head->addChild(new GenericElement("link",
					       array(),
					       array("rel"=>"stylesheet",
						     "type"=>"text/css",
						     "media"=>"screen",
						     "href"=>"inc/css/mobile.css")));
    }
    else {
      $this->head->addChild(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "title"=>"Modern Tech",
						   "media"=>"screen",
						   "href"=>"inc/css/modern.css")));
    }
    $this->head->addChild(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "media"=>"screen",
						   "href"=>"inc/css/" . 
						   "AutoComplete.css")));
    $this->head->addChild(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "media"=>"print",
						   "href"=>"inc/css/print.css")));
    $this->head->addChild(new GenericElement("link",
					     array(),
					     array("rel"=>"alternate stylesheet",
						   "type"=>"text/css",
						   "title"=>"Plain Text",
						   "media"=>"screen",
						   "href"=>"inc/css/plain.css")));
    $this->head->addChild(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "media"=>"screen",
						   "href"=>"inc/css/cal.css")));

    // Javascript
    foreach (array("jquery-1.3.min.js",
		   "jquery.tablehover.min.js",
		   "jquery.columnmanager.min.js",
		   "ui.datepicker.js",
		   "AutoComplete.js") as $scr) {
      $this->head->addChild(new GenericElement("script",
					       array(new Text("")),
					       array("type"=>"text/javascript",
						     "src"=>"inc/js/" . $scr)));
    }
    if ($this->mobile) {
      $this->head->addChild(new GenericElement("script", array(new Text("")),
					       array("type"=>"text/javascript",
						     "src"=>"inc/js/mobile.js")));
    }
    else {
      foreach (array("form.js", "ui.frames.js") as $scr) {
	$this->head->addChild(new GenericElement("script", array(new Text("")),
						 array("type"=>"text/javascript",
						       "src"=>"inc/js/" . $scr)));
      }
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
    $g->addChild(new Image("img/techscore.png", array("id"=>"headimg",
						      "alt"=>"TechScore")));
    $div->addChild(new Heading(date("D M j, Y"), array("id"=>"date")));
    
    $this->header->addChild($this->navigation = new Div());
    $this->navigation->addAttr("id", "topnav");
    $this->navigation->addChild(new Link("../help", "Help?",
					 array("id"=>"help",
					       "target"=>"_blank",
					       "accesskey"=>"h")));
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
}

?>