<?php
use \tscore\RpDownloadDialog;

/*
 * This class is part of TechScore
 *
 * @package tscore-dialog
 */

require('AbstractPane.php');

/**
 * Template for all display dialogs.
 *
 */
abstract class AbstractDialog extends AbstractPane {

  /**
   * Creates a new display dialog with the provided name and regatta
   *
   * @param String $name the name of the dialog
   * @param Regatta $reg the regatta
   */
  public function __construct($name, Account $user, FullRegatta $reg) {
    parent::__construct($name, $user, $reg);
    $this->REGATTA = $reg;
  }

  public function process(Array $args) {
    throw new SoterException("Dialogs only display data. Invalid request.");
  }

  protected function setupPage() {
    parent::setupPage();
    $this->PAGE->setContentAttribute('class', 'dialog');
  }

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

  /**
   * Returns a new instance of a dialog for the given download URL.
   *
   * Assumption: full URL is of form /download/<reg>/<args/to/this/method>.
   *
   * @see getDialog
   */
  public static function getDownloadDialog(Array $uri, Account $u, FullRegatta $r) {
    if (count($uri) == 0) {
      return null;
    }

    try {
      switch ($uri[0]) {
        // --------------- RP FORMS --------------//
      case 'rp':
      case 'rpform':
      case 'rps':
        return new RpDownloadDialog($u, $r);


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
