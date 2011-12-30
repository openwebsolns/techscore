<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('xml/XmlLibrary.php');
require_once('xml/TScorePage.php');

/**
 * Welcome page, subclasses WebPage
 *
 */
class WelcomePage extends TScorePage {

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
    parent::__construct("Welcome to TechScore");
    $this->fillMenu();
    $this->fillContent();
  }

  /**
   * Fills the menu
   *
   */
  protected function fillMenu() {
    // Access to registration, ICSA, offline TS
    $this->addMenu($menu = new Div());
    $menu->addAttr("class", "menu");
    $menu->addChild(new Heading("Useful Links"));
    $menu->addChild($l = new Itemize());
    $l->addChild(new LItem(new XA(".", "Sign-in")));
    $l->addChild(new LItem(new XA("register", "Register")));
    $l->addChild(new LItem(new XA("http://www.collegesailing.org", "ICSA Website")));
    $l->addChild(new LItem(new XA("http://techscore.sourceforge.net", "Offline TechScore")));
  }

  /**
   * Sets up the body of this page
   *
   */
  protected function fillContent() {
    // LOGIN MENU
    $this->addContent($p = new Port("Sign-in"));
    $p->addChild($form = new Form("/login", "post"));
    $form->addChild(new FItem(new Label("uname", "Username: "),
			      new FText("userid", "",   array("id"=>"uname", "maxlength"=>"40"))));
    $form->addChild($fi = new FItem(new Label("passw", "Password: "),
				    new FPassword("pass", "", array("id"=>"passw", "maxlength"=>"48"))));
    $fi->addChild(new XMessage(new XA('/password-recover', "Forgot your password?")));

    $form->addChild(new FSubmit("login", "Login"));

    // Announcements
    $this->addContent($p = new Port("Announcements"));
    $file = sprintf("%s/announcements.html", dirname(__FILE__));
    if (file_exists($file))
      $p->addChild(new XRawText(file_get_contents($file)));
    else
      $p->addChild(new Para("No announcements at this time."));

    $this->addContent($p = new Port("Register for TechScore"));

    $str = '
     If you are affiliated with <a
     href="http://www.collegesailing.org">ICSA</a> and would like an
     account for TechScore, you can <a href="./register">register
     here</a>.';
    $p->addChild(new Para($str));
  }
}

?>
