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

    $this->PAGE = new TScoreDialog($this->name . " | " . $this->REGATTA->name);
    $this->PAGE->addContent(new XPageTitle($this->name));

    //   -Regatta info
    $this->PAGE->addHeader(new XH4($this->REGATTA->name, array('id'=>'regata')));
  }

  /**
   * Prints string reprensentation of the HTML page
   *
   * @param Array $args the arguments to this page
   */
  final public function getHTML(Array $args) {
    $this->setupPage();
    $this->fillHTML($args);
    $this->PAGE->printXML();
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
