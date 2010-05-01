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
    /*
    
    $this->PAGE->addHead(new GenericElement("title",
					    array(new Text($title))));
    $this->PAGE->addHead(new GenericElement("base",
					    array(),
					    array("href"=>HOME . "/")));
    $this->PAGE->addHead(new GenericElement("link",
					    array(),
					    array("rel"=>"shortcut icon",
						  "href"=>"img/t.ico",
						  "type"=>"image/x-icon")));
    // Stylesheets
    $this->PAGE->addHead(new GenericElement("link",
					    array(),
					    array("rel"=>"stylesheet",
						  "type"=>"text/css",
						  "title"=>"Tech",
						  "media"=>"screen",
						  "href"=>"inc/css/prefs.css")));
    $this->PAGE->addHead(new GenericElement("link",
					    array(),
					    array("rel"=>"stylesheet",
						  "type"=>"text/css",
						  "media"=>"screen",
						  "href"=>"inc/css/" . 
						  "AutoComplete.css")));
    $this->PAGE->addHead(new GenericElement("link",
					    array(),
					    array("rel"=>"stylesheet",
						  "type"=>"text/css",
						  "media"=>"print",
						  "href"=>"inc/css/prefs-print.css")));
    $this->PAGE->addHead(new GenericElement("link",
					    array(),
					    array("rel"=>"alternate stylesheet",
						  "type"=>"text/css",
						  "title"=>"Plain Text",
						  "media"=>"screen",
						  "href"=>"inc/css/plain.css")));
    $this->PAGE->addHead(new GenericElement("link",
					    array(),
					    array("rel"=>"stylesheet",
						  "type"=>"text/css",
						  "media"=>"screen",
						  "href"=>"inc/css/cal.css")));

    // Javascript
    foreach (array("jquery-1.3.min.js") as $scr) {
      $this->PAGE->addHead(new GenericElement("script",
					      array(new Text("")),
					      array("type"=>"text/javascript",
						    "src"=>"inc/js/" . $scr)));
    }
    */
    
    //--------------------------------------------------
    // BODY
    /*
    $menu = new Div(array(), array("id"=>"menudiv"));
    $this->PAGE->addBody($menu);
    $this->PAGE->addBody(new GenericElement("hr", array(),
					    array("class"=>"hidden")));

    // Menus
    $this->PAGE->addMenu($m = new MenuDiv());

    //  -Home
    $m->addChild("TechScore",   $m_list = new GenericList());
    $m_list->addItems(new LItem(new Link(".", "Back")));
    
    //  -Preferences
    $m->addSubmenu("Preferences", $m_list = new GenericList());
    $id = $this->SCHOOL->id;
    $m_list->addItems(new LItem(new Link("prefs/$id",        "Pref. Home")),
		      new LItem(new Link("prefs/$id/logo",   "School logo")),
		      new LItem(new Link("prefs/$id/team",   "Team names")),
		      new LItem(new Link("prefs/$id/sailor", "Sailors")));

    // --------------------------------------------------
    // Header
    $this->PAGE->addBody($d = new Div(array(), array("id"=>"headdiv")));
    $d->addChild($d2 = new Div(array(), array("id"=>"header")));
    $d2->addChild(new GenericElement("h1",
				     array(new Image("img/techscore.png",
						     array("id"=>"headimg",
							   "alt"=>"TechScore")))));
    $d2->addChild(new Heading(date("D M. j, Y"), array("id"=>"date")));

    // Topnav
    $d->addChild($d2 = new Div(array(), array("id"=>"topnav")));
    //   -Help link
    $d2->addChild(new Link("../help", "Help?",
			   array("id"=>"help",
				 "target"=>"_blank")));
    
    //   -User info
    $usr = $this->USER;
    $d2->addChild($d3 = new Div(array(), array("id"=>"user")));
    $d3->addChild(new Text($usr->getName()));
    $d3->addChild(new Link("_logout.php", "[logout]"));
    $d3->addChild(new Itemize(array(new LItem($usr->username()),
				    new LItem(sprintf("%s at %s",
						      ucfirst($usr->get(User::ROLE)),
						      $usr->get(User::SCHOOL)->nick_name)))));

    $this->PAGE->addBody(new Div(array(), array("id" => "bottom-grab")));
    // --------------------------------------------------
    // Announcement
    $this->PAGE->addBody($this->ANNOUNCE = new Div());
    $this->ANNOUNCE->addChild(new Text(""));
    $this->ANNOUNCE->addAttr("id", "announcediv");

    // --------------------------------------------------
    // Content
    $this->PAGE->addBody($this->CON = new Div());
    $this->CON->addAttr("id", "bodydiv");
    $this->CON->addChild(new GenericElement("h2", array(new Text($this->name))));

    // --------------------------------------------------
    // Footer
    $this->PAGE->addBody($foot = new Div());
    $foot->addAttr("id", "footdiv");
    $foot->addChild(new Para(sprintf("TechScore v%s &copy; Day&aacute;n P&aacute;ez 2008-9",
				     VERSION)));
    */
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


// Main method
if (basename($argv[0]) == basename(__FILE__)) {

  class TestPane extends AbstractPrefsPane {
    public function __construct(User $user, School $sch) {
      parent::__construct("Test", $user, $sch);
    }
    public function fillHTML() {}
  }
  
  $u = Preferences::getUser("paez@mit.edu");
  $p = new TestPane($u, $u->school);
  print($p->getHTML());
}
?>