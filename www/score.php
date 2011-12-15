<?php
/**
 * Directs traffic while scoring regattas
 *
 */

require_once('conf.php');
require_once('xml/Announcement.php');
require_once('tscore/WebServer.php');

session_start();

//
// Is logged-in
//
if (!(isset($_SESSION['user']))) {
  $_SESSION['last_page'] = preg_replace(':^/edit/:', '/', $_SERVER['REQUEST_URI']);

  // provide the login page
  $_SESSION['ANNOUNCE'][] = new Announcement("Please login to proceed.", Announcement::WARNING);
  require_once('xml/WelcomePage.php');
  $PAGE = new WelcomePage();
  $PAGE->printHTML();
  exit;
}
$USER = null;
try {
  $USER = new User($_SESSION['user']);
  AccountManager::requireActive($USER);
}
catch (Exception $e) {
  $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
  WebServer::go('/');
}

//
// Regatta
//
if (!isset($_REQUEST['reg']) || !is_numeric($_REQUEST['reg'])) {
  WebServer::go('/');
}
try {
  require_once('regatta/Regatta.php');
  $REG = new Regatta((int)$_REQUEST['reg']);
}
catch (Exception $e) {
  $_SESSION['ANNOUNCE'][] = new Announcement("No such regatta.", Announcement::WARNING);
  WebServer::go('/');
}
if (!$USER->hasJurisdiction($REG)) {
  // No jurisdiction
  WebServer::go('/');
}

//
// Content, whether dialog ("v" or editing pane "p")
//
$PAGE = null;
if (!isset($_REQUEST['p']) &&
    !isset($_REQUEST['v']) &&
    !isset($_REQUEST['d'])) {
  $mes = "No page requested.";
  $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
  WebServer::go("/score/".$REG->id());
}

//
// - Editing panes
//
elseif (isset($_REQUEST['p'])) {
  $POSTING = (isset($_GET['_action']) && $_GET['_action'] == 'edit');
  if (empty($_REQUEST['p'])) {
    require_once('tscore/DetailsPane.php');
    $PAGE = new DetailsPane($USER, $REG);
  }
  else {
    require_once('tscore/AbstractPane.php');
    $PAGE = AbstractPane::getPane($_REQUEST['p'], $USER, $REG);
    if ($PAGE === null) {
      $mes = sprintf("Invalid page requested (%s)", $_REQUEST['p']);
      $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
      WebServer::go("/score/".$REG->id());
    }
    if (!$PAGE->isActive()) {
      $title = $PAGE->getTitle();
      $_SESSION['ANNOUNCE'][] = new Announcement("$title is not available.", Announcement::WARNING);
      WebServer::go("/score/".$REG->id());
    }
  }
  // process, if so requested
  if ($POSTING) {
    require_once('public/UpdateManager.php');
    $_SESSION['POST'] = $PAGE->process($_POST);
    if (LOG_MEMORY)
      error_log(sprintf("%s:\t%d\n", $_SERVER['REQUEST_URI'], memory_get_peak_usage()), 3, "../log/memory.log");
    WebServer::goBack();
  }
}

//
// - View panes
//
elseif (isset($_REQUEST['v'])) {
  if (empty($_REQUEST['v'])) {
    require_once('tscore/RotationDialog.php');
    $mes = "No dialog selected, defaulting to Rotation.";
    $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
    $PAGE = new RotationDialog($REG);
  }
  else {
    switch ($_REQUEST['v']) {
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
      require_once('tscore/ScoresFullDialog.php');
      $PAGE = new ScoresFullDialog($REG);
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
      $div = substr($_REQUEST['v'], strlen($_REQUEST['v']) - 1);
      try {
	require_once('tscore/ScoresDivisionDialog.php');
	$PAGE = new ScoresDivisionDialog($REG, new Division($div));
      } catch (Exception $e) {
	$_SESSION['ANNOUNCE'][] = new Announcement($e->getMessage(), Announcement::WARNING);
	$PAGE = new ScoresFullDialog($REG);
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
      $mes = sprintf("Unknown dialog requested (%s).", $_REQUEST['v']);
      $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
      WebServer::go(sprintf("/view/%d/rotation", $REG->id()));
    }
  }
}

//
// - Downloads
//
else {
  $st = $REG->get(Regatta::START_TIME);
  $nn = $REG->get(Regatta::NICK_NAME);
  if (count($REG->getTeams()) == 0 || count($REG->getDivisions()) == 0) {
    $_SESSION['ANNOUNCE'][] = new Announcement("First create teams and divisions before downloading.", Announcement::WARNING);
    WebServer::go(sprintf('/score/%d', $REG->id()));
  }
  switch ($_REQUEST['d']) {

    // --------------- REGATTA ---------------//
  case "":
  case "regatta":
    $name = sprintf("%s-%s.tsr", $st->format("Y"), $nn);
    header("Content-type: text/xml");
    header(sprintf('Content-disposition: attachment; filename="%s"', $name));
    echo RegattaIO::toXML($REG);
    break;


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
    $mes = sprintf("Invalid download requested (%s)", $_REQUEST['d']);
    $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
    WebServer::goBack();
  }
  exit;
}
$args = $_REQUEST;
if (isset($_SESSION['POST']) && is_array($_SESSION['POST']))
  $args = array_merge($args,$_SESSION['POST']);
$PAGE->getHTML($args);

if (LOG_MEMORY)
  error_log(sprintf("%s:\t%d\n", $_SERVER['REQUEST_URI'], memory_get_peak_usage()), 3, "../log/memory.log");
?>
