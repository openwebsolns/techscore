<?php
/**
 * Directs traffic while scoring regattas
 *
 */
require_once('../lib/conf.php');
session_start();

//
// Is logged-in
//
if (!(isset($_SESSION['user']))) {
  $_SESSION['last_page'] = preg_replace(':^/edit/:', '/', $_SERVER['REQUEST_URI']);

  // provide the login page
  $PAGE = new WelcomePage();
  echo $PAGE->toHTML();
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
  if (empty($_REQUEST['p'])) {
    $PAGE = new DetailsPane($USER, $REG);
  }
  else {
    $panes = array(new DetailsPane($USER, $REG),
		   new SummaryPane($USER, $REG),
		   new RacesPane($USER, $REG),
		   new TeamsPane($USER, $REG),
		   new NotesPane($USER, $REG),
		   new SailsPane($USER, $REG),
		   new TweakSailsPane($USER, $REG),
		   new ManualTweakPane($USER, $REG),
		   new RpEnterPane($USER, $REG),
		   new UnregisteredSailorPane($USER, $REG),
		   new ScorersPane($USER, $REG),
		   new EnterFinishPane($USER, $REG),
		   new DropFinishPane($USER, $REG),
		   new EnterPenaltyPane($USER, $REG),
		   new DropPenaltyPane($USER, $REG),
		   new TeamPenaltyPane($USER, $REG));
    foreach ($panes as $pane) {
      if (in_array($_REQUEST['p'], $pane->getURLs())) {
	if ($pane->isActive()) {
	  $PAGE = $pane;
	  break;
	}
	else {
	  $title = $pane->getTitle();
	  $_SESSION['ANNOUNCE'][] = new Announcement("$title is not available.", Announcement::WARNING);
	  WebServer::go("/score/".$REG->id());
	}
      }
    }
    if ($PAGE === null) {
      $mes = sprintf("Invalid page requested (%s)", $_REQUEST['p']);
      $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
      WebServer::go("/score/".$REG->id());
    }
  }
  // process, if so requested
  if (isset($_GET['_action']) && $_GET['_action'] == 'edit') {
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
    $mes = "No dialog selected, defaulting to Rotation.";
    $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
    $PAGE = new RotationDialog($REG);
  }
  else {
    switch ($_REQUEST['v']) {

      // --------------- ROT DIALOG ---------------//
    case "rotation":
    case "rotations":
      $PAGE = new RotationDialog($REG);
      break;
  
      // --------------- RP DIALOG ----------------//
    case "sailors":
    case "sailor":
      $PAGE = new RegistrationsDialog($REG);
      break;
    
      // --------------- FULL SCORE --------------//
    case "result":
    case "results":
    case "score":
    case "scores":
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
	$PAGE = new ScoresDivisionDialog($REG, new Division($div));
      } catch (Exception $e) {
	$_SESSION['ANNOUNCE'][] = new Announcement($e->getMessage(), Announcement::WARNING);
	$PAGE = new ScoresFullDialog($REG);
      }
      break;

    case "div-score":
    case "div-scores":
      $PAGE = new ScoresDivisionalDialog($REG);
    break;

      // --------------- BOAT SCORE --------------//
    case "boat":
    case "boats":
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
    echo '<?xml version="1.0" encoding="utf-8"?>';
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
if (isset($_SESSION['POST']))
  $args = array_merge($args,$_SESSION['POST']);
echo $PAGE->getHTML($args);

if (LOG_MEMORY)
  error_log(sprintf("%s:\t%d\n", $_SERVER['REQUEST_URI'], memory_get_peak_usage()), 3, "../log/memory.log");
?>