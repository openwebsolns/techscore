<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");
__autoload("XmlLibrary");

/**
 * Welcome page, subclasses WebPage
 *
 */
class WelcomePage extends WebPage {

  // Private variables
  private $header;
  private $navigation;
  private $menu;
  private $content;
  private $announce;

  /**
   * Create a new Welcome webpage, titled "Welcome"
   *
   */
  public function __construct() {
    parent::__construct();
    
    // Menu
    $this->menu = new Div();
    $this->menu->addAttr("id", "menudiv");
    $this->addBody($this->menu);
    $this->addBody(new GenericElement("hr", array(), array("class"=>"hidden")));
    $this->addBody($this->header = new Div());

    // Header
    $this->header->addAttr("id", "headdiv");

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

    $this->fillHead();
    $this->fillPageHeader();
    $this->fillMenu();
    $this->fillContent();
  }

  /**
   * Adds the good stuff to the head of the page
   *
   */
  private function fillHead() {
    $this->head->addChild(new GenericElement("title", array(new Text($title))));
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
						   "title"=>"Modern Tech",
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
					 array("id"=>"help","target"=>"_blank")));
  }

  /**
   * Fills the menu
   *
   */
  private function fillMenu() {
    
    // LOGIN MENU
    $this->menu->addChild($form = new Form("login", "post", array(), false));
    $form->addChild(new Label("uname", "Username: "));
    $form->addChild(new FText("userid", "",
			      array("id"=>"uname",
				    "size"=>"12")));
    $form->addChild(new Label("passw", "Password: "));
    $form->addChild(new FPassword("pass", "",
				  array("id"=>"passw",
					"size"=>"12")));

    $form->addChild(new FSubmit("login", "Login"));
  }

  /**
   * Sets up the body of this page
   *
   */
  private function fillContent() {
    $this->content->addChild(new PageTitle("Welcome"));
    $this->content->addChild($p = new Port("Announcements"));
    $p->addChild(new Text(file_get_contents(dirname(__FILE__) . "/announcements.html")));

    $this->content->addChild($p = new Port("Register for TechScore"));

    $str = '
     If you are affiliated with <a
     href="http://www.collegesailing.org">ICSA</a> and would like an
     account for TechScore, you can <a href="../register">register
     here</a>.';
    $p->addChild(new Para($str));

    // Process announcements
    if (isset($_SESSION['ANNOUNCE'])) {
      foreach ($_SESSION['ANNOUNCE'] as $mes) {
	$this->addAnnouncement(new Announcement($mes, Announcement::WARNING));
      }
    }
  }
}

?>