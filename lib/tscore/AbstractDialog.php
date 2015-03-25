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
  public function __construct($name, Account $user, FullRegatta $reg) {
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
  public static function getDialog(Array $uri, Account $u, FullRegatta $r) {
    if (count($uri) == 0)
      $uri = array('rotation');
    try {
      switch ($uri[0]) {
        // --------------- ROT DIALOG ---------------//
      case 'rotation':
      case 'rotations':
        if ($r->scoring == Regatta::SCORING_TEAM) {
          require_once('tscore/TeamRotationDialog.php');
          return new TeamRotationDialog($u, $r);
        }
        require_once('tscore/RotationDialog.php');
        return new RotationDialog($u, $r);

        // --------------- RP DIALOG ----------------//
      case 'sailors':
      case 'sailor':
        if ($r->scoring == Regatta::SCORING_TEAM) {
          require_once('tscore/TeamRegistrationsDialog.php');
          return new TeamRegistrationsDialog($u, $r);
        }
        require_once('tscore/RegistrationsDialog.php');
        return new RegistrationsDialog($u, $r);

        // --------------- SCORES --------------//
      case 'result':
      case 'results':
      case 'score':
      case 'scores':
        if ($r->scoring == Regatta::SCORING_TEAM) {
          require_once('tscore/ScoresGridDialog.php');
          return new ScoresGridDialog($u, $r);
        }
        // look for division argument
        if (count($uri) > 1) {
          require_once('tscore/ScoresDivisionDialog.php');
          return new ScoresDivisionDialog($u, $r, new Division($uri[1]));
        }
        require_once('tscore/ScoresFullDialog.php');
        return new ScoresFullDialog($u, $r);

        // --------------- ALL RACES ----------------//
      case 'all':
      case 'races':
        if ($r->scoring == Regatta::SCORING_TEAM) {
          require_once('tscore/TeamRacesDialog.php');
          return new TeamRacesDialog($u, $r);
        }
        return null;

        // --------------- RANKING ------------------//
      case 'ranking':
      case 'rank':
      case 'div-score':
      case 'div-scores':
        if ($r->scoring == Regatta::SCORING_TEAM) {
          require_once('tscore/TeamRankingDialog.php');
          return new TeamRankingDialog($u, $r);
        }
        require_once('tscore/ScoresDivisionalDialog.php');
        return new ScoresDivisionalDialog($u, $r);

        // --------------- COMINED. SCORE --------------//
      case 'combined':
        if ($r->scoring != Regatta::SCORING_COMBINED)
          return null;
        require_once('tscore/ScoresCombinedDialog.php');
        return new ScoresCombinedDialog($u, $r);

        // -------------- CHARTS --------------------//
      case 'chart':
      case 'history':
        if ($r->scoring != Regatta::SCORING_STANDARD)
          return null;
        require_once('tscore/ScoresChartDialog.php');
        return new ScoresChartDialog($u, $r);

        // -------------- BOATS --------------------//
      case 'boat':
      case 'boats':
        if ($r->scoring != Regatta::SCORING_STANDARD && $r->scoring != Regatta::SCORING_COMBINED)
          return null;
        $rot = $r->getRotation();
        if (!$rot->isAssigned())
          return null;
        require_once('tscore/BoatsDialog.php');
        return new BoatsDialog($u, $r);

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
