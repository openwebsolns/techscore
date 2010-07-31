<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('conf.php');
__autoload('XmlLibrary');

/**
 * Parent class of all editing panes. Requires USER and REGATTA.
 *
 * @author Dayan Paez
 * @created 2009-09-27
 */
abstract class AbstractPane {

  // Private variables
  private $name;
  private $announce;
  protected $REGATTA;
  protected $PAGE;
  protected $USER;

  /**
   * The title of the page as it will be used when generating URLs
   */
  protected $title;
  protected $urls;

  /**
   * Create a new editing pane with the given name
   *
   * @param String $name the name of the editing page
   * @param User $user the user that is editing
   * @param Regatta $reg the regatta for this page
   */
  public function __construct($name, User $user, Regatta $reg) {
    $this->name = (string)$name;
    $this->REGATTA = $reg;
    $this->USER = $user;

    // A quick fix to make sure that every pane has at least one URL,
    // while allowing subclasses some control
    $this->title = $this->name;
    $this->urls = array();
    $this->urls[] = str_replace(" ", "-", strtolower($name));
  }

  /**
   * Sets up the HTML page
   *
   */
  protected function setupPage() {
    $title = sprintf("%s | %s | TS",
		     $this->name,
		     $this->REGATTA->get(Regatta::NAME));

    $this->PAGE = new EditRegattaPage($title, $this->USER, $this->REGATTA);
    $this->PAGE->addContent(new PageTitle($this->name));

    // Header
    //   -User info
    $title = ucfirst(sprintf("%s at %s",
			     $this->USER->get(User::ROLE),
			     $this->USER->get(User::SCHOOL)->nick_name));
    $this->PAGE->addNavigation($d3 = new Div(array(), array("id"=>"user")));
    // $d3->addChild(new Text($this->USER->getName()));
    $d3->addChild(new Link("logout", "[logout]"));
    $d3->addChild(new Itemize(array(new LItem($this->USER->username()),
				    new LItem($title))));

    //   -Regatta info
    $this->PAGE->addNavigation($d3 = new Div(array(), array("id"=>"regatta")));
    $d3->addChild(new Text(stripslashes($this->REGATTA->get(Regatta::NAME))));
    $d3->addChild(new Link("./", "[close]", array("accesskey"=>"w")));
    $d3->addChild(new Itemize(array(new LItem(date_format($this->REGATTA->get(Regatta::START_TIME),
							  "M. j, Y")),
				    new LItem(ucfirst($this->REGATTA->get(Regatta::TYPE))))));

  }


  /**
   * Add the announcements saved in session
   */
  private function processAnnouncements() {
    while (count($_SESSION['ANNOUNCE']) > 0) {
      $anc = Announcement::parse(array_shift($_SESSION['ANNOUNCE']));
      $this->PAGE->addAnnouncement($anc);
    }
  }

  /**
   * Redirects the browser to the specified page, or regatta home if
   * none specified
   *
   *
   */
  protected function redirect($page = null) {
    header(sprintf("Location: %s/score/%s/%s",
		   HOME,
		   $this->REGATTA->id(),
		   $page));
    exit;
  }

  /**
   * Returns string reprensentation of the HTML page
   *
   * @param Array $args the arguments to this page
   * @return String the HTML code as a string
   */
  final public function getHTML(Array $args) {
    $this->setupPage();
    $this->fillHTML($args);
    if (isset($_SESSION['ANNOUNCE'])) {
      $this->processAnnouncements();
    }
    return $this->PAGE->toHTML();
  }

  /**
   * Queues the given announcement
   *
   * @param Announcement $a the announcement to add
   */
  final public function announce(Announcement $a) {
    $_SESSION['ANNOUNCE'][] = $a;
  }

  /**
   * Returns the title of the page
   *
   * @return String the title of the page
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * Returns the main URL by which this pane is arrived at
   *
   * @return String the relative URL
   */
  public function getMainURL() {
    return $this->urls[0];
  }

  /**
   * Returns all the URLs by which this pane can be arrived at
   *
   * @return Array<String> the relative URLs
   */
  public function getURLs() {
    return $this->urls;
  }

  /**
   * Children of this class must implement this method to be used when
   * displaying the page. The method should fill the protected
   * variable $PAGE, which is an instance of EditRegattaPage
   *
   * @param Array $args arguments to customize the display of the page
   */
  abstract protected function fillHTML(Array $args);

  /**
   * Process the edits described in the arguments array
   *
   * @param Array $args the parameters to process
   * @return Array parameters to pass to the next page
   */
  abstract public function process(Array $args);

  /**
   * Determines whether this pane is active given the current state of
   * the regatta
   *
   * @return boolean true if pane is active
   */
  abstract public function isActive();

  /**
   * Creates a new Form HTML element using the standard URL for this
   * pane. The optional $method is by default post
   *
   * @param $method "post" or "get"
   * @return Form element
   */
  protected function createForm($method = "post") {
    return new Form(sprintf("edit/%d/%s", $this->REGATTA->id(), $this->getMainURL()), $method);
  }
}

?>