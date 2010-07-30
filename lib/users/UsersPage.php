<?php
/**
 * This file is part of TechScore
 *
 * @package users
 */

require_once("conf.php");

/**
 * User's home page, subclasses WebPage
 *
 */
class UsersPage extends TScorePage {

  // Private variables
  private $user;

  /**
   * Create a new User home page for the specified user
   *
   * @param User $user the user whose page to load
   */
  public function __construct($title, User $user) {
    parent::__construct($title);
    $this->user = $user;
    $this->fillMenu();
  }

  /**
   * Fills up the head element of this page
   *
   */
  private function fillHead() {
    $this->head->addChild(new GenericElement("title",
					     array(new Text($this->title))));
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
    $this->head->addChild(new GenericElement("link",
					     array(),
					     array("rel"=>"stylesheet",
						   "type"=>"text/css",
						   "title"=>"Modern",
						   "media"=>"screen",
						   "href"=>"inc/css/modern.css")));
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
  }

  /**
   * Creates the header of this page
   *
   */
  /*
  private function fillPageHeader() {
    $this->header->addChild($div = new Div());
    $div->addAttr("id", "topnav");
    $div->addChild(new Link("../help", "Help?", array("id"=>"help",
						      "target"=>"_blank",
						      "accesskey"=>"h")));
    // User information
    $div->addChild($d3 = new Div(array(), array("id"=>"user")));
    $d3->addChild(new Text($this->user->getName()));
    $d3->addChild(new Link("logout", "[logout]"));

    $role = sprintf('%s at %s',
		    ucfirst($this->user->get(User::ROLE)),
		    $this->user->get(User::SCHOOL)->nick_name);
    $d3->addChild(new Itemize(array(new LItem($this->user->username()),
				    new LItem($role))));
    
    $div->addChild($div = new Div());
    $div->addAttr("id", "bottom-grab");
    $div->addChild(new Text());
  }
  */

  /**
   * Fills the menu
   *
   */
  private function fillMenu() {
    // Determine school based on session data or default value
    $SCHOOL = $this->user->get(User::SCHOOL)->id;
    if (isset($_SESSION['SCHOOL'])) $SCHOOL = $_SESSION['SCHOOL'];
    
    // Preferences
    $this->addMenu($div = new Div());
    $div->addAttr("class", "menu");
    $div->addChild(new Heading("TechScore"));
    $div->addChild($list = new GenericList());
    $list->addItems(new LItem(new Link(".",      "My regattas")),
		    new LItem(new Link("create", "New regatta")),
		    new LItem($l = new Link("logout", "Logout", array("accesskey"=>"l"))));

    // School setup
    $this->addMenu($div = new Div());
    $div->addAttr("class", "menu");
    $div->addChild(new Heading("School setup"));
    $div->addChild($list = new GenericList());
    $list->addItems(new LItem(new Link("prefs/$SCHOOL",        "Pref. Home")),
		    new LItem(new Link("prefs/$SCHOOL/logo",   "School logo")),
		    new LItem(new Link("prefs/$SCHOOL/team",   "Team names")),
		    new LItem(new Link("prefs/$SCHOOL/sailor", "Sailors")));
    
    // Messages
    $this->addMenu($div = new Div());
    $div->addAttr("class", "menu");
    $div->addChild(new Heading("Messages"));
    $div->addChild($list = new GenericList());
    $list->addItems(new LItem(new Link("inbox", "Inbox")));

    // Admin
    if ($this->user->get(User::ADMIN)) {
      $this->addMenu($div = new Div());
      $div->addAttr("class", "menu");
      $div->addChild(new Heading("Admin"));
      $div->addChild($list = new GenericList());
      $list->addItems(new LItem(new Link("pending",   "Pending users")));
      $list->addItems(new LItem(new Link("venue",     "Venues")));
      $list->addItems(new LItem(new Link("venue-add", "Add Venues")));
    }
  }
}
?>