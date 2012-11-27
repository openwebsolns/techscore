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
  public function __construct($name, FullRegatta $reg) {
    $this->name = (string)$name;
    $this->REGATTA = $reg;
  }

  /**
   * Sets up the HTML page
   *
   */
  protected function setupPage() {
    require_once('xml5/TScoreDialog.php');

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

  // ------------------------------------------------------------
  // Static methods
  // ------------------------------------------------------------

  /**
   * Returns a new instance of a dialog for the given URL
   *
   * @see AbstractPane::getPane
   */
  public static function getDialog(Array $uri, Account $r, FullRegatta $u) {
    if (count($uri) == 0)
      $uri = array('rotation');
    try {
      switch ($uri[0]) {
        // --------------- ROT DIALOG ---------------//
      case 'rotation':
      case 'rotations':
        require_once('tscore/RotationDialog.php');
        return new RotationDialog($u);

        // --------------- RP DIALOG ----------------//
      case 'sailors':
      case 'sailor':
        require_once('tscore/RegistrationsDialog.php');
        return new RegistrationsDialog($u);

        // --------------- SCORES --------------//
      case 'result':
      case 'results':
      case 'score':
      case 'scores':
        if ($u->scoring == Regatta::SCORING_TEAM) {
          require_once('tscore/ScoresGridDialog.php');
          return new ScoresGridDialog($u);
        }
        // look for division argument
        if (count($uri) > 1) {
          require_once('tscore/ScoresDivisionDialog.php');
          return new ScoresDivisionDialog($u, new Division($uri[1]));
        }
        require_once('tscore/ScoresFullDialog.php');
        return new ScoresFullDialog($u);

        // --------------- DIV. SCORE --------------//
      case 'div-score':
      case 'div-scores':
        require_once('tscore/ScoresDivisionalDialog.php');
        return new ScoresDivisionalDialog($u);

        // --------------- COMINED. SCORE --------------//
      case 'combined':
        if ($u->scoring != Regatta::SCORING_COMBINED)
          return null;
        require_once('tscore/ScoresCombinedDialog.php');
        return new ScoresCombinedDialog($u);

	// -------------- CHARTS --------------------//
      case 'chart':
      case 'history':
	if ($u->scoring != Regatta::SCORING_STANDARD)
	  return null;
	require_once('tscore/ScoresChartDialog.php');
	return new ScoresChartDialog($u);

	// --------------- LAST UPDATE ------------//
      case 'last-update':
        // @TODO: deprecate
        $t = $u->getLastScoreUpdate();
        if ($t == null)
          $t = new DateTime('yesterday');
        echo $t->format('Y-m-d H:i:s');
        exit;

        // --------------- default ----------------//
      default:
        return null;
      }
    }
    catch (Exception $e) { // semaphore exception?
      return null;
    }
  }
}
?>
