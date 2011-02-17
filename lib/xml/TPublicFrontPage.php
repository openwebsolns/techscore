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
 * The basic HTML template for the front page
 *
 * @author Dayan Paez
 * @version 2010-08-24
 */
class TPublicFrontPage extends WebPage {

  // Private variables
  private $header;
  private $navigation;
  private $menu;
  private $content;
  private $announce;

  private $mobile;

  public function __construct() {
    parent::__construct();

    // Setup head
    $this->addHead(new GenericElement("title", array(new Text('Welcome | TechScore: Real-Time Regatta Results'))));
    $this->addHead(new GenericElement("link",
				      array(),
				      array("rel"=>"stylesheet",
					    "type"=>"text/css",
					    "title"=>"Modern Tech",
					    "media"=>"screen",
					    "href"=>"/inc/css/mp-front.css")));

    // Setup body
    $this->addBody($div = new Div(array(), array("class"=>"hidden")));
    $div->addChild(new PortTitle("Navigate"));
    $div->addChild(new Itemize(array(new LItem(new Link("#menu", "Menu")),
				     new LItem(new Link("#body", "Content")))));

    // Menu
    $this->menu = new Div();
    $this->menu->addAttr("id", "menudiv");
    $this->menu->addChild(new Text(""));
    $this->addBody($this->menu);
    $this->addBody(new GenericElement("hr", array(), array("class"=>"hidden")));
    $this->addBody($this->header = new Div());

    // Header
    $this->header->addAttr("id", "headdiv");
    $this->fillPageHeader();

    $this->addBody($this->content = new Div(array(), array("id" => "body")));

    // Announcement
    $this->content->addChild($this->announce = new Div());
    $this->announce->addAttr("id", "announcediv");
    $this->announce->addChild(new Text());

    // Footer
    $this->addBody($footer = new Div());
    $footer->addAttr("id", "footdiv");
    $footer->addChild(new Para(sprintf("TechScore v%s &copy; Day&aacute;n P&aacute;ez 2008-11",
				       VERSION)));

    $this->addMenu(new Link("/schools", "Schools"));
  }
  public function addSection(GenericElement $elem) {
    $this->content->addChild($elem);
  }

  /**
   * Creates the header of this page
   *
   */
  private function fillPageHeader() {
    $this->header->addChild($div = new Div());
    $div->addAttr("id", "header");
    $div->addChild($g = new GenericElement("h1"));
    $g->addChild(new Link(PUB_HOME,
			  new Image("/inc/img/ts-front.png", array("id"=>"headimg",
								    "alt"=>"TechScore"))));
    $div->addChild(new Heading(date("M j, Y"), array("id"=>"date")));
    
    $this->header->addChild($this->navigation = new Div());
    $this->navigation->addAttr("id", "topnav");
    $this->navigation->addChild(new Text());
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
   * Adds the given element to the menu division of this page
   *
   * @param HTMLElement $elem to add to the menu of this page
   */
  public function addMenu(HTMLElement $elem) {
    $this->menu->addChild($elem);
  }
}
?>