<?php
/**
 * Describes the parent class for all editing panes in the
 * preferences section
 *
 * @author Dayan Paez
 * @version 1.0
 * @created 2009-10-14
 * @package prefs
 */

require_once("conf.php");
__autoload("XmlLibrary");

/**
 * Parent class for all preference editing panes, similar to
 * AbstractPane defined for editing regatta pages
 *
 */
abstract class AbstractPrefsPane {

  // Private variables
  private $name;
  protected $USER;
  protected $SCHOOL;
  protected $PAGE;

  /**
   * Create a new preferences editing pane with the given name
   *
   * @param String $name the name of the pane
   * @param User   $user the user which is using the page
   * @param School $school the school for this page
   */
  public function __construct($name, User $user, School $school) {
    $this->name   = $name;
    $this->USER   = $user;
    $this->SCHOOL = $school;
  }

  /**
   * Sets up the HTML page
   *
   */
  protected function setupPage() {
    $title = sprintf("%s | %s | TS",
		     $this->name,
		     $this->SCHOOL->name);
    $this->PAGE = new UsersPage($title, $this->USER);
  }

  /**
   * Returns the string representation of the HTML page
   *
   * @return String HTML page
   */
  public function getHTML() {
    $this->setupPage();
    $this->fillHTML();
    
    // fill announcements
    while (count($_SESSION['ANNOUNCE']) > 0) {
      $this->ANNOUNCE->addChild(array_shift($_SESSION['ANNOUNCE']));
    }
    return $this->PAGE->toHTML();
  }

  /**
   * Queues the given annnouncement
   *
   * @param Announcement $a the announcement
   */
  protected function announce(Announcement $a) {
    $_SESSION['ANNOUNCE'][] = $a;
  }

  /**
   * Children of this class must implement this method to be used when
   * displaying the page. The method should fill the protected
   * variable $PAGE, using possibly the page's content variable, $CON.
   *
   */
  abstract protected function fillHTML();

  /**
   * Process the edits described in the EventArgs array.
   *
   * @param Array EventArgs the event arguments to process
   */
  abstract public function process(Array $args);
}
?>