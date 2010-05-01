<?php
/**
 * This file is part of TechScore
 *
 * @package users
 */
require_once('conf.php');

/**
 * This is the parent class of all user's editing panes. It insures a
 * function called getHTML() exists which only populates a page if so
 * necessary. This page is modeled after tscore/AbstractPane
 *
 * @author Dayan Paez
 * @date   2010-04-12
 */
abstract class AbstractUserPane {

  protected $USER;
  protected $PAGE;
  private $title;

  /**
   * Creates a new User editing pane with the given title
   *
   * @param String $title the title of the page
   * @param User $user the user to whom this applies
   */
  public function __construct($title, User $user) {
    $this->title = (string)$title;
    $this->USER  = $user;
  }

  /**
   * Retrieves the HTML code for this pane
   *
   * @param Array $args the arguments to consider
   * @return String the HTML code
   */
  public function getHTML(Array $args) {
    $this->PAGE = new UsersPage($this->title, $this->USER);
    $this->PAGE->addContent(new PageTitle($this->title));
    $this->fillHTML($args);
    return $this->PAGE->toHTML();
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
      header("Location: $url");
    elseif (isset($_SESSION['HTTP_REFERER']))
      header("Location: " . $_SESSION['HTTP_REFERER']);
    else
      header("Location: . ");
    exit;
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