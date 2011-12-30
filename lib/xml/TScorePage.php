<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package xml
 */

require_once('xml/XmlLibrary.php');
require_once('xml5/TS.php');

/**
 * The basic HTML page for TechScore files. This page extends the
 * WebPage class and sets up the necessary structure common to all the
 * pages: headers, footers, contents, and announcements.
 *
 * @author Dayan Paez
 * @version 2.0
 * @version 2009-10-19
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
   * @param User $user the possible logged-in user
   * @param Regatta $reg the possible regatta in use. This affects the
   * menu that is displayed.
   */
  public function __construct($title, User $user = null, Regatta $reg = null) {
    parent::__construct();
    $this->mobile = $this->isMobile();

    $this->addHead(new GenericElement('meta', array(), array('name'=>'robots', 'content'=>'noindex, nofollow')));
    $this->addHead(new GenericElement('meta', array(), array('http-equiv'=>'Content-Type', 'content'=>'text/html; charset=UTF-8')));
    $this->fillHead($title);

    // Menu
    if ($this->mobile) {
      $this->addBody(new GenericElement("button", array(new XText("Menu")),
					array("onclick"=>"toggleMenu()")));
    }
    $this->menu = new Div();
    $this->menu->set("id", "menudiv");
    $this->addBody($this->menu);
    $this->addBody(new GenericElement("hr", array(), array("class"=>"hidden")));
    $this->addBody($this->header = new Div());

    // Header
    $this->header->set("id", "headdiv");
    $this->fillPageHeader($user, $reg);

    // Content
    $this->addBody($this->content = new Div());
    $this->content->set("id", "bodydiv");

    // Announcement
    // Fill announcement
    $this->content->add($this->announce = new Div());
    $this->announce->set("id", "announcediv");
    $this->announce->add(new XText(""));
    if (isset($_SESSION['ANNOUNCE']) && is_array($_SESSION['ANNOUNCE']) &&
	count($_SESSION['ANNOUNCE']) > 0) {
      while (count($_SESSION['ANNOUNCE']) > 0)
	$this->addAnnouncement(array_shift($_SESSION['ANNOUNCE']));
    }

    // Footer
    $this->addBody($footer = new Div());
    $footer->set("id", "footdiv");
    $footer->add(new Para(sprintf("TechScore v%s © Dayán Páez 2008-%s", VERSION, date('y'))));
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
    $this->head->add(new GenericElement("title",
					     array(new XText($title))));

    // CSS Stylesheets
    if ($this->mobile) {
      $this->head->add(new GenericElement("link",
					       array(),
					       array("rel"=>"stylesheet",
						     "type"=>"text/css",
						     "media"=>"screen",
						     "href"=>"/inc/css/mobile.css")));
    }
    else {
      $this->head->add(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "title"=>"Modern Tech",
						   "media"=>"screen",
						   "href"=>"/inc/css/modern.css")));
    }
    $this->head->add(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "media"=>"print",
						   "href"=>"/inc/css/print.css")));
    $this->head->add(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "media"=>"screen",
						   "href"=>"/inc/css/cal.css")));

    // Javascript
    foreach (array("jquery-1.3.min.js",
		   "jquery.tablehover.min.js",
		   "jquery.columnmanager.min.js",
		   "ui.datepicker.js") as $scr) {
      $this->head->add(new GenericElement("script",
					       array(new XText("")),
					       array("type"=>"text/javascript",
						     "src"=>"/inc/js/" . $scr)));
    }
    if ($this->mobile) {
      $this->head->add(new GenericElement("script", array(new XText("")),
					       array("type"=>"text/javascript",
						     "src"=>"/inc/js/mobile.js")));
    }
    else {
      foreach (array("form.js") as $scr) {
	$this->head->add(new GenericElement("script", array(new XText("")),
						 array("type"=>"text/javascript",
						       "src"=>"/inc/js/" . $scr)));
      }
    }
  }

  /**
   * Creates the header of this page
   *
   */
  private function fillPageHeader(User $user = null, Regatta $reg = null) {
    $this->header->add($div = new Div());
    $div->set("id", "header");
    $div->add($g = new GenericElement("h1"));
    $g->add(new XImg("/img/techscore.png", "TechScore", array("id"=>"headimg")));
    $div->add(new XH4(date("M j, Y"), array("id"=>"date")));
    if (isset($_SESSION['user'])) {
      $div->add(new XH4($_SESSION['user'], array("id"=>"user")));
    }
    
    $this->header->add($this->navigation = new Div());
    $this->navigation->set("id", "topnav");
    $this->navigation->add($a = new XA(HELP_HOME, new XSpan("H", array('style'=>"text-decoration:underline")),
					    array("id"=>"help",
						  "target"=>"help",
						  "accesskey"=>"h")));
    $a->add("elp?");
    if ($user !== null) {
      $this->navigation->add($d3 = new Div(array(), array("id"=>"user")));
      $d3->add(new XA("/logout", "Logout", array('accesskey'=>'l')));
      // $d3->add(new Itemize(array(new XLi($user->username()))));
    }
    if ($reg !== null) {
      $div->add(new XH4($reg->get(Regatta::NAME), array("id"=>"regatta")));
    }
  }

  /**
   * Adds the HTMLElement to the content of this page
   *
   * @param HTMLElement $elem an element to append to the body of this
   * page
   */
  public function addContent($elem) {
    $this->content->add($elem);
  }

  /**
   * Adds the given element to the menu division of this page
   *
   * @param HTMLElement $elem to add to the menu of this page
   */
  public function addMenu($elem) {
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
}

?>
