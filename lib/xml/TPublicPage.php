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
 * The basic HTML template for the public pages
 *
 * @author Dayan Paez
 * @version 2010-08-24
 */
class TPublicPage extends WebPage {
  private $content;
  public function __construct($title) {
    parent::__construct($title);

    $this->addBody($div = new Div(array(), array("class"=>"nav")));
    $div->addChild(new PortTitle("Main menu"));
    $div->addChild(new Itemize(array(new LItem(new Link("#menu", "Menu")),
				     new LItem(new Link("#body", "Content")))));

    $this->addBody($this->content = new Div(array(), array("id" => "body")));
  }
  public function addSection(GenericElement $elem) {
    $this->content->addChild($elem);
  }
}
?>