<?php
/*
 * This class is part of TechScore
 *
 * @package tscore-dialog
 */

/**
 * Template for all display dialogs. Requires REGATTA.
 *
 */
abstract class AbstractDialog {
  
  // Variables
  private $name;
  private $announce;
  protected $REGATTA;
  protected $PAGE;

  /**
   * Creates a new display dialog with the provided name and regatta
   *
   * @param String $name the name of the dialog
   * @param Regatta $reg the regatta
   */
  public function __construct($name, Regatta $reg) {
    $this->name = (string)$name;
    $this->REGATTA = $reg;
  }

  /**
   * Sets up the HTML page
   *
   */
  protected function setupPage() {
    require_once('xml/TScoreDialog.php');

    $title = sprintf("%s | %s | TS",
		     $this->name,
		     $this->REGATTA->get(Regatta::NAME));
    $this->PAGE = new TScoreDialog($this->name);
    $this->PAGE->addContent(new PageTitle($this->name));

    // Menu
    $this->PAGE->addMenu($h = new XH4("Refresh"));
    $h->add(new XLi(new XA($_SERVER['REQUEST_URI'], "Refresh")));

    //   -Regatta info
    $this->PAGE->addNavigation($d3 = new Div(array(), array("id"=>"regatta")));
    $d3->add(new XText($this->REGATTA->get(Regatta::NAME)));
    $d3->add(new Itemize(array(new XLi(ucfirst($this->REGATTA->get(Regatta::TYPE))))));
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
   * Prints string reprensentation of the HTML page
   *
   * @param Array $args the arguments to this page
   */
  final public function getHTML(Array $args) {
    $this->setupPage();
    $this->fillHTML($args);
    if (isset($_SESSION['ANNOUNCE'])) {
      $this->processAnnouncements();
    }
    $this->PAGE->printXML();
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
   * Children of this class must implement this method to be used when
   * displaying the page. The method should fill the protected
   * variable $PAGE, which is an instance of TScorePage
   *
   * @param Array $args arguments to customize the display of the page
   */
  abstract protected function fillHTML(Array $args);
}
?>
