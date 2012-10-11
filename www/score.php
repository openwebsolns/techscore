<?php
/**
 * Directs traffic while scoring regattas
 *
 */

require_once('conf.php');

//
// Is logged-in
//
if (Conf::$USER === null) {
  Session::s('last_page', preg_replace(':^/edit/:', '/', $_SERVER['REQUEST_URI']));

  // provide the login page
  Session::pa(new PA("Please login to proceed.", PA::I));
  require_once('users/LoginPage.php');
  $PAGE = new LoginPage();
  $PAGE->getHTML($_GET);
  exit;
}

//
// Regatta
//
if (!isset($_GET['reg']) || !is_numeric($_GET['reg'])) {
  WS::go('/');
}
require_once('regatta/Regatta.php');
if (($REG = DB::getRegatta($_GET['reg'])) === null) {
  Session::pa(new PA("No such regatta.", PA::I));
  WS::go('/');
}
if (!Conf::$USER->hasJurisdiction($REG)) {
  Session::pa(new PA("You do not have permission to edit that regatta.", PA::I));
  WS::go('/');
}

//
// Content, whether dialog ("v" or editing pane "p")
//
$PAGE = null;
if (!isset($_GET['p']) &&
    !isset($_GET['v']) &&
    !isset($_GET['d'])) {
  $mes = "No page requested.";
  Session::pa(new PA($mes, PA::I));
  WS::go('/score/'.$REG->id);
}

//
// - Editing panes
//
elseif (isset($_GET['p'])) {
  $POSTING = (isset($_GET['_action']) && $_GET['_action'] == 'edit');
  if (empty($_GET['p'])) {
    require_once('tscore/DetailsPane.php');
    $PAGE = new DetailsPane(Conf::$USER, $REG);
  }
  else {
    require_once('tscore/AbstractPane.php');
    $PAGE = AbstractPane::getPane($_GET['p'], Conf::$USER, $REG);
    if ($PAGE === null) {
      $mes = sprintf("Invalid page requested (%s)", $_GET['p']);
      Session::pa(new PA($mes, PA::I));
      WS::go('/score/'.$REG->id);
    }
    if (!$PAGE->isActive()) {
      $title = $PAGE->getTitle();
      Session::pa(new PA("$title is not available.", PA::I));
      WS::go('/score/'.$REG->id);
    }
  }
  // process, if so requested
  if ($POSTING) {
    require_once('public/UpdateManager.php');
    Session::s('POST', $PAGE->processPOST($_POST));
    WS::goBack('/');
  }
}

//
// - View panes
//
elseif (isset($_GET['v'])) {
  if (empty($_GET['v'])) {
    require_once('tscore/RotationDialog.php');
    $mes = "No dialog selected, defaulting to Rotation.";
    Session::pa(new PA($mes, PA::I));
    $PAGE = new RotationDialog($REG);
  }
  else {
    switch ($_GET['v']) {
      // --------------- ROT DIALOG ---------------//
    case "rotation":
    case "rotations":
      require_once('tscore/RotationDialog.php');
      $PAGE = new RotationDialog($REG);
      break;

      // --------------- RP DIALOG ----------------//
    case "sailors":
    case "sailor":
      require_once('tscore/RegistrationsDialog.php');
      $PAGE = new RegistrationsDialog($REG);
      break;

      // --------------- FULL SCORE --------------//
    case "result":
    case "results":
    case "score":
    case "scores":
      if ($REG->scoring == Regatta::SCORING_TEAM) {
        require_once('tscore/ScoresGridDialog.php');
        $PAGE = new ScoresGridDialog($REG);
      }
      else {
        require_once('tscore/ScoresFullDialog.php');
        $PAGE = new ScoresFullDialog($REG);
      }
      break;

      // --------------- DIV. SCORE --------------//
    case "score/A":
    case "score/B":
    case "score/C":
    case "score/D":
    case "scores/A":
    case "scores/B":
    case "scores/C":
    case "scores/D":
      $div = substr($_GET['v'], strlen($_GET['v']) - 1);
      try {
        require_once('tscore/ScoresDivisionDialog.php');
        $PAGE = new ScoresDivisionDialog($REG, new Division($div));
      } catch (Exception $e) {
        Session::pa(new PA($e->getMessage(), PA::I));
        WS::go(sprintf('/view/%s/scores', $REG->id));
      }
      break;

    case "div-score":
    case "div-scores":
      require_once('tscore/ScoresDivisionalDialog.php');
      $PAGE = new ScoresDivisionalDialog($REG);
    break;

      // --------------- BOAT SCORE --------------//
    case "boat":
    case "boats":
      require_once('tscore/ScoresBoatDialog.php');
      $PAGE = new ScoresBoatDialog($REG);
      break;

      // --------------- LAST UPDATE ------------//
    case "last-update":
      $t = $REG->getLastScoreUpdate();
      if ($t == null)
        $t = new DateTime("yesterday");
      echo $t->format("Y-m-d H:i:s");
      exit;

      // --------------- default ----------------//
    default:
      $mes = sprintf("Unknown dialog requested (%s).", $_GET['v']);
      Session::pa(new PA($mes, PA::I));
      WS::go(sprintf('/view/%d/rotation', $REG->id));
    }
  }
}

//
// - Downloads
//
else {
  $st = $REG->start_time;
  $nn = $REG->nick;
  if (count($REG->getTeams()) == 0 || count($REG->getDivisions()) == 0) {
    Session::pa(new PA("First create teams and divisions before downloading.", PA::I));
    WS::go(sprintf('/score/%d', $REG->id));
  }
  switch ($_GET['d']) {

    // --------------- REGATTA ---------------//
    /*
  case "":
  case "regatta":
    $name = sprintf("%s-%s.tsr", $st->format("Y"), $nn);
    header("Content-type: text/xml");
    header(sprintf('Content-disposition: attachment; filename="%s"', $name));
    echo RegattaIO::toXML($REG);
    break;
    */

    // --------------- RP FORMS --------------//
  case "rp":
  case "rpform":
  case "rps":
    $name = sprintf("%s-%s-rp", $st->format("Y"), $nn);
    $rp = $REG->getRpManager();
    if ($rp->isFormRecent())
      $data = $rp->getForm();
    else {
      require_once('rpwriter/RpFormWriter.php');
      $writer = new RpFormWriter($REG);
      $path = $writer->makePDF();
      $data = file_get_contents($path);
      unlink($path);
      $rp->setForm($data);
    }

    header('Content-type: application/pdf');
    header(sprintf('Content-Disposition: attachment; filename="%s.pdf"', $name));
    echo $data;
    break;

    // --------------- default ---------------//
  default:
    $mes = sprintf("Invalid download requested (%s)", $_GET['d']);
    Session::pa(new PA($mes, PA::I));
    WS::goBack('/');
  }
  exit;
}
$args = $_GET;
$post = Session::g('POST');
if (is_array($post))
  $args = array_merge($post, $args);
$PAGE->getHTML($args);
?>
