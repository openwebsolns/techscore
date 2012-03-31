<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package xml
 */

require_once('xml5/TS.php');

/**
 * The basic HTML page for TechScore files. This page extends the
 * XPage class and sets up the necessary structure common to all the
 * pages: headers, footers, contents.
 *
 * @author Dayan Paez
 * @version 2.0
 * @version 2009-10-19
 */
class TScorePage extends XPage {

  // Private variables
  private $user;
  private $reg;

  private $header;
  private $navigation;
  private $menu;
  private $content;

  private $mobile;
  private $filled;

  /**
   * Creates a new page with the given title
   *
   * @param String $title the title of the page
   * @param Account $user the possible logged-in user
   * @param Regatta $reg the possible regatta in use. This affects the
   * menu that is displayed.
   */
  public function __construct($title, Account $user = null, Regatta $reg = null) {
    parent::__construct($title . " | " . Conf::$NAME);
    $this->user = $user;
    $this->reg = $reg;

    $this->mobile = $this->isMobile();
    $this->fillHead();

    $this->content = array();
    $this->filled = false;
    $this->menu = new XDiv(array('id'=>'menudiv'));
    $this->header = new XDiv(array('id'=>'headdiv'));
    $this->navigation = new XDiv(array('id'=>'topnav'));
  }

  private function fill() {
    if ($this->filled) return;
    $this->filled = true;
    
    // Menu
    if ($this->mobile) {
      $this->body->add(new XButton(array("onclick"=>"toggleMenu()", 'type'=>'button'), array("Menu")));
    }
    $this->body->add($this->menu);
    $this->body->add(new XHr(array('class'=>'hidden')));
    $this->body->add($this->header);

    // Header
    $this->fillPageHeader($this->user, $this->reg);

    // Content
    $this->body->add($c = new XDiv(array('id'=>'bodydiv')));

    // Announcement
    if (class_exists('Session', false))
      $c->add(Session::getAnnouncements('/inc/img'));
    foreach ($this->content as $cont)
      $c->add($cont);

    // Footer
    $this->body->add(new XDiv(array('id'=>'footdiv'),
			      array(new XP(array(), sprintf("%s v%s © Dayán Páez 2008-%s", Conf::$NAME, Conf::$VERSION, date('y'))))));
  }

  /**
   * Determines whether the page is being accessed through a mobile
   * device
   *
   */
  private function isMobile() {
    return (isset($_SERVER['HTTP_USER_AGENT']) &&
	    (strpos($_SERVER['HTTP_USER_AGENT'], "Android") !== false ||
	     strpos($_SERVER['HTTP_USER_AGENT'], "iPhone")  !== false));
  }

  /**
   * Fills up the head element of this page
   *
   */
  private function fillHead() {
    $this->head->add(new XMeta('robots', 'noindex, nofollow'));
    $this->head->add(new XMetaHTTP('http-equiv', 'text/html; charset=UTF-8'));

    // CSS Stylesheets
    if ($this->mobile) {
      $this->head->add(new LinkCSS('/inc/css/mobile.css'));
    }
    else {
      $this->head->add(new LinkCSS('/inc/css/modern.css'));
    }
    $this->head->add(new LinkCSS('/inc/css/print.css','print'));
    $this->head->add(new LinkCSS('/inc/css/cal.css'));

    // Javascript
    foreach (array("jquery-1.3.min.js",
		   "jquery.tablehover.min.js",
		   "jquery.columnmanager.min.js",
		   "ui.datepicker.js") as $scr) {
      $this->head->add(new XScript('text/javascript', "/inc/js/$scr"));
    }
    if ($this->mobile) {
      $this->head->add(new XScript('text/javascript', '/inc/js/mobile.js'));
    }
    else {
      $this->head->add(new XScript('text/javascript', '/inc/js/form.js'));
    }
  }

  /**
   * Creates the header of this page
   *
   */
  private function fillPageHeader(Account $user = null, Regatta $reg = null) {
    $this->header->add($div = new XDiv(array('id'=>'header'),
				       array(new XH1(new XImg("/inc/img/techscore.png", Conf::$NAME, array("id"=>"headimg"))))));
    $div->add(new XH4(date("M j, Y"), array("id"=>"date")));
    if (class_exists('Session', false) && Session::has('user'))
      $div->add(new XH4(Session::g('user'), array("id"=>"user")));
    
    $this->header->add($this->navigation);
    $this->navigation->add($a = new XA(Conf::$HELP_HOME, new XSpan("H", array('style'=>"text-decoration:underline")),
				       array("id"=>"help",
					     'onclick'=>'this.target="help"',
					     "accesskey"=>"h")));
    $a->add("elp?");
    if ($user !== null) {
      $this->navigation->add(new XDiv(array("id"=>"logout"),
				      array(new XA("/logout", "Logout", array('accesskey'=>'l')))));
    }
    if ($reg !== null) {
      $div->add(new XH4($reg->name, array("id"=>"regatta")));
    }
  }

  /**
   * Adds the Xmlable to the content of this page
   *
   * @param Xmlable $elem an element to append to the body of this
   * page
   */
  public function addContent($elem) {
    $this->content[] = $elem;
  }

  /**
   * Adds the given element to the menu division of this page
   *
   * @param Xmlable $elem to add to the menu of this page
   */
  public function addMenu($elem) {
    $this->menu->add($elem);
  }

  /**
   * Adds the given element to the page header
   *
   * @param Xmlable $elem to add to the page header
   */
  public function addHeader(Xmlable $elem) {
    $this->header->add($elem);
  }

  /**
   * Adds the given element to the navigation part
   *
   * @param Xmlable $elem to add to navigation
   */
  public function addNavigation(Xmlable $elem) {
    $this->navigation->add($elem);
  }

  public function toXML() {
    $this->fill();
    return parent::toXML();
  }
  public function printXML() {
    $this->fill();
    return parent::printXML();
  }
}

?>
