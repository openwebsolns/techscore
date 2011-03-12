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

    $this->PAGE = new TScorePage($title, $this->USER, $this->REGATTA);
    $this->PAGE->addContent(new PageTitle($this->name));

    // ------------------------------------------------------------
    // Menu
    $score_i = array("Regatta"   => array(new DetailsPane($this->USER, $this->REGATTA),
					  new SummaryPane($this->USER, $this->REGATTA),
					  new ScorersPane($this->USER, $this->REGATTA),
					  new RacesPane($this->USER, $this->REGATTA),
					  new NotesPane($this->USER, $this->REGATTA),
					  '/' => "Close"),
		     "Teams"     => array(new TeamsPane($this->USER, $this->REGATTA),
					  new ReplaceTeamPane($this->USER, $this->REGATTA)),
		     "Rotations" => array(new SailsPane($this->USER, $this->REGATTA),
					  new TweakSailsPane($this->USER, $this->REGATTA),
					  new ManualTweakPane($this->USER, $this->REGATTA)),
		     "RP Forms"  => array(new RpEnterPane($this->USER, $this->REGATTA),
					  new UnregisteredSailorPane($this->USER, $this->REGATTA)),
		     "Finishes"  => array(new EnterFinishPane($this->USER, $this->REGATTA),
					  new DropFinishPane($this->USER, $this->REGATTA),
					  new EnterPenaltyPane($this->USER, $this->REGATTA),
					  new DropPenaltyPane($this->USER, $this->REGATTA),
					  new TeamPenaltyPane($this->USER, $this->REGATTA)));


    $dial_i  = array("rotation" => "Rotation",
		     "scores"   => "Scores",
		     "sailors"  => "Sailors");

    // Fill panes menu
    $id = $this->REGATTA->id();
    foreach ($score_i as $title => $panes) {
      $menu = new Div();
      $menu->addAttr("class", "menu");
      $menu->addChild(new Heading($title));
      $menu->addChild($m_list = new GenericList());
      foreach ($panes as $url => $pane) {
	if ($pane instanceof AbstractPane) {
	  $url = $pane->getMainURL();
	  $pane = $pane->getTitle();
	  // if ($pane->isActive())
	}
	$m_list->addItems(new LItem(new Link("/score/$id/$url", $pane)));
	// else
	// $m_list->addItems(new LItem($pane->getTitle(), array("class"=>"inactive")));
      }
      $this->PAGE->addMenu($menu);
    }

    // Downloads
    $menu = new Div();
    $menu->addAttr("class", "menu");
    $menu->addChild(new Heading("Download"));
    $menu->addChild($m_list = new GenericList());
    $m_list->addItems(new LItem(new Link("/download/$id/regatta", "Regatta")));
    $m_list->addItems(new LItem(new Link("/download/$id/rp", "RP Forms")));
    $this->PAGE->addMenu($menu);

    // Dialogs
    $menu = new Div();
    $menu->addAttr("class", "menu");
    $menu->addChild(new Heading("Windows"));
    $menu->addChild($m_list = new GenericList());
    foreach ($dial_i as $url => $title) {
      $link = new Link("/view/$id/$url", $title);
      $link->addAttr("class", "frame-toggle");
      $link->addAttr("target", "_blank");
      $m_list->addItems(new LItem($link));
    }
    $this->PAGE->addMenu($menu);
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
    WebServer::go(sprintf('/score/%s/%s', $this->REGATTA->id(), $page));
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
   * variable $PAGE, which is an instance of TScorePage
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
    return new Form(sprintf("/edit/%d/%s", $this->REGATTA->id(), $this->getMainURL()), $method);
  }
}

?>