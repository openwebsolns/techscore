<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('xml/XmlLibrary.php');
require_once('xml5/TS.php');

/**
 * This is the parent class of all user's editing panes. It insures a
 * function called getHTML() exists which only populates a page if so
 * necessary. This page is modeled after tscore/AbstractPane
 *
 * @author Dayan Paez
 * @version   2010-04-12
 */
abstract class AbstractUserPane {

  protected $USER;
  protected $PAGE;
  protected $SCHOOL;
  protected $title;

  /**
   * Creates a new User editing pane with the given title
   *
   * @param String $title the title of the page
   * @param User $user the user to whom this applies
   */
  public function __construct($title, User $user, School $school = null) {
    $this->title = (string)$title;
    $this->USER  = $user;
    $this->SCHOOL = $school;
    if ($this->SCHOOL === null)
      $this->SCHOOL = $this->USER->get(User::SCHOOL);
  }

  /**
   * Retrieves the HTML code for this pane
   *
   * @param Array $args the arguments to consider
   * @return String the HTML code
   */
  public function getHTML(Array $args) {
    require_once('xml/TScorePage.php');
    $this->PAGE = new TScorePage($this->title, $this->USER);

    // ------------------------------------------------------------
    // menu
    
    // User Preferences
    $this->PAGE->addMenu($div = new Div());
    $div->addAttr("class", "menu");
    $div->addChild(new Heading("TechScore"));
    $div->addChild($list = new GenericList());
    $list->addItems(new LItem(new XA("/",      "My regattas")),
		    new LItem(new XA("/create", "New regatta", array("accesskey"=>"n"))),
		    new LItem(new XA("/account","My account")));

    // School setup
    $S = $this->SCHOOL->id;
    $this->PAGE->addMenu($div = new Div());
    $div->addAttr("class", "menu");
    $div->addChild(new Heading("My School"));
    $div->addChild($list = new GenericList());
    $list->addItems(new LItem(new XA("/prefs/$S",        "Instructions")),
		    new LItem(new XA("/prefs/$S/logo",   "School logo")),
		    new LItem(new XA("/prefs/$S/team",   "Team names")),
		    new LItem(new XA("/prefs/$S/sailor", "Sailors")));

    // Reports
    $this->PAGE->addMenu($div = new Div());
    $div->addAttr("class", "menu");
    $div->addChild(new Heading("Reports"));
    $div->addChild($list = new GenericList());
    $list->addItems(new LItem(new XA("/aa", "All-American")),
		    new LItem(new XA("/compare-sailors", "Head to head")),
		    new LItem(new XA("/compare-by-race", "Comp. by race")));
    
    // Messages
    $this->PAGE->addMenu($div = new Div());
    $div->addAttr("class", "menu");
    $div->addChild(new Heading("Messages"));
    $div->addChild($list = new GenericList());
    $list->addItems(new LItem(new XA("/inbox", "Inbox")));
    if ($this->USER->get(User::ADMIN)) {
      $list->addItems(new LItem(new XA("/send-message", "Send message")));
    }

    // Admin
    if ($this->USER->get(User::ADMIN)) {
      $this->PAGE->addMenu($div = new Div());
      $div->addAttr("class", "menu");
      $div->addChild(new Heading("Admin"));
      $div->addChild($list = new GenericList());
      $list->addItems(new LItem(new XA("/pending",   "Pending users")));
      $list->addItems(new LItem(new XA("/venue",     "Venues")));
      $list->addItems(new LItem(new XA("/edit-venue", "Add Venues")));
      $list->addItems(new LItem(new XA("/boats",     "Boats")));
    }
    $this->PAGE->addContent(new PageTitle($this->title));
    $this->fillHTML($args);
    $this->PAGE->printXML();
  }

  /**
   * Queues the given announcement with the session
   *
   * @param Announcement $a the announcement
   */
  public function announce(Announcement $a) {
    if (isset($_SESSION)) {
      if (!isset($_SESSION['ANNOUNCE']))
	$_SESSION['ANNOUNCE'] = array();
      $_SESSION['ANNOUNCE'][] = $a;
    }
  }

  /**
   * Redirects to the given URL, or back to the referer
   *
   * @param String $url the url to go
   */
  public function redirect($url = null) {
    if ($url !== null)
      WebServer::go($url);
    WebServer::goBack();
  }

  /**
   * Fill this page's content
   *
   * @param Array $args the arguments to process
   */
  protected abstract function fillHTML(Array $args);

  /**
   * Processes the requests made to this page (usually from this page)
   *
   * @param Array $args the arguments to process
   * @return Array the modified arguments
   */
  public abstract function process(Array $args);
}
?>