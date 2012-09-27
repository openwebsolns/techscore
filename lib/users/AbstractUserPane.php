<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

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
   * @param Account $user the user to whom this applies
   */
  public function __construct($title, Account $user = null) {
    $this->title = (string)$title;
    $this->USER  = $user;
    if ($this->USER !== null)
      $this->SCHOOL = $this->USER->school;
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

    if ($this->USER === null) {
      // ------------------------------------------------------------
      // menu
      $this->PAGE->addMenu(new XDiv(array('class'=>'menu'),
                                    array(new XH4("Useful Links"),
                                          $m = new XUl(array(),
                                                       array(new XLi(new XA(".", "Sign-in")))))));
      if (Conf::$ALLOW_REGISTER !== false)
        $m->add(new XLi(new XA("register", "Register")));
      $m->add(new XLi(new XA("http://www.collegesailing.org", "ICSA Website")));
      $m->add(new XLi(new XA("http://techscore.sourceforge.net", "Offline TechScore")));

      $this->PAGE->addContent(new XPageTitle($this->title));
      $this->fillHTML($args);
      $this->PAGE->printXML();
      return;
    }

    // ------------------------------------------------------------
    // menu

    // User Preferences
    $this->PAGE->addMenu(new XDiv(array('class'=>'menu'),
                                  array(new XH4("TechScore"),
                                        new XUl(array(),
                                                array(new XLi(new XA("/archive", "All regattas")),
                                                      new XLi(new XA("/create", "New regatta", array("accesskey"=>"n"))),
                                                      new XLi(new XA("/account","My account")))))));
    // School setup
    $S = $this->SCHOOL->id;
    $this->PAGE->addMenu(new XDiv(array('class'=>'menu'),
                                  array(new XH4("My School"),
                                        new XUl(array(),
                                                array(new XLi(new XA("/prefs/$S",        "Instructions")),
                                                      new XLi(new XA("/prefs/$S/logo",   "School logo")),
                                                      new XLi(new XA("/prefs/$S/team",   "Team names")),
                                                      new XLi(new XA("/prefs/$S/sailor", "Sailors")))))));
    // Reports
    $this->PAGE->addMenu(new XDiv(array('class'=>'menu'),
                                  array(new XH4("Reports"),
                                        new XUl(array(),
                                                array(new XLi(new XA("/aa", "All-American")),
                                                      new XLi(new XA("/compare-sailors", "Head to head")),
                                                      new XLi(new XA("/compare-by-race", "Comp. by race")))))));
    // Messages
    $this->PAGE->addMenu(new XDiv(array('class'=>'menu'),
                                  array(new XH4("Messages"),
                                        $list = new XUl())));
    $list->add(new XLi(new XA("/inbox", "Inbox")));
    if ($this->USER->isAdmin())
      $list->add(new XLi(new XA("/send-message", "Send message")));

    // Admin
    if ($this->USER->isAdmin()) {
      $this->PAGE->addMenu(new XDiv(array('class'=>'menu'),
                                    array(new XH4("Admin"),
                                          new XUl(array(),
                                                  array(new XLi(new XA("/pending",   "Pending users")),
                                                        new XLi(new XA("/venue",     "Venues")),
                                                        new XLi(new XA("/edit-venue", "Add Venues")),
                                                        new XLi(new XA("/boats",     "Boats")))))));
    }
    $this->PAGE->addContent(new XPageTitle($this->title));
    $this->fillHTML($args);
    $this->PAGE->printXML();
  }

  /**
   * Redirects to the given URL, or back to the referer
   *
   * @param String $url the url to go
   */
  protected function redirect($url = null) {
    if ($url !== null)
      WS::go('/'.$url);
    WS::goBack('/');
  }

  /**
   * Wrapper around process method to be used by web clients. Wraps
   * the SoterExceptions as announcements.
   *
   * @param Array $args the parameters to process
   * @return Array parameters to pass to the next page
   */
  final public function processPOST(Array $args) {
    try {
      return $this->process($args);
    } catch (SoterException $e) {
      Session::pa(new PA($e->getMessage(), PA::E));
      return array();
    }
  }

  /**
   * Fill this page's content
   *
   * @param Array $args the arguments to process
   */
  abstract protected function fillHTML(Array $args);

  /**
   * Processes the requests made to this page (usually from this page)
   *
   * @param Array $args the arguments to process
   * @return Array the modified arguments
   */
  abstract public function process(Array $args);
}
?>