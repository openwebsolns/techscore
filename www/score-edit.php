<?php
  /**
   * Directs traffic while editing regattas
   *
   */
require_once('../lib/conf.php');
session_start();

//
// Is logged-in
//
if (!(isset($_SESSION['user']))) {
  $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
  WebServer::go(HOME);
}
$USER = null;
try {
  $USER = new User($_SESSION['user']);
  AccountManager::requireActive($USER);
}
catch (Exception $e) {
  $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
  WebServer::go(HOME);
}

//
// Regatta
//
if (!isset($_REQUEST['reg']) || !is_numeric($_REQUEST['reg'])) {
  header("Location: " . HOME);
  return;
}
$reg_id = (int)$_REQUEST['reg'];
if (!Preferences::getObjectWithProperty($USER->getRegattas(), "id", $reg_id)) {
  // No jurisdiction
  header("Location: " . HOME);
  return;
}
$REG = new Regatta($reg_id);

//
// Content
//
$PAGE = null;
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
	header(sprintf("Location: score/%s", $REG->id()));
	exit;
      }
    }
  }
  if ($PAGE === null) {
    $mes = sprintf("Invalid page requested (%s)", $_REQUEST['p']);
    $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
    header(sprintf("Location: %s/score/%s", HOME, $reg_id));
    exit;
  }
}
/*
if (empty($_REQUEST['p']))
  $PAGE = new DetailsPane($USER, new Regatta($reg_id));
else {
  switch ($_REQUEST['p']) {

    // --------------- SUMMARIES -------------//
  case "summary":
  case "summaries":
  case "comment":
  case "comments":
    $PAGE = new SummaryPane($USER, new Regatta($reg_id));
    break;

    // --------------- RACES   ---------------//
  case race:
  case races:
    $PAGE = new RacesPane($USER, new Regatta($reg_id));
    break;

    // --------------- TEAMS   ---------------//
  case school:
  case schools:
  case team:
  case teams:
    $PAGE = new TeamsPane($USER, new Regatta($reg_id));
    break;
  
    // --------------- NOTES   ---------------//
  case "note":
  case "notes":
    $PAGE = new NotesPane($USER, new Regatta($reg_id));
    break;
  
    // --------------- ROTATIONS   ---------------//
  case sail:
  case sails:
    $PAGE = new SailsPane($USER, new Regatta($reg_id));
    break;
  
    // --------------- TWEAK SAILS ---------------//
  case tweak:
    $PAGE = new TweakSailsPane($USER, new Regatta($reg_id));
    break;

    // --------------- MANUAL TWEAK -------------//
  case "manual-tweak":
    $PAGE = new ManualTweakPane($USER, new Regatta($reg_id));
    break;

    // --------------- RP FORMS -----------------//
  case "rp":
    $PAGE = new RpEnterPane($USER, new Regatta($reg_id));
    break;

    // --------------- UNREG SAILORS-------------//
  case "temp":
    $PAGE = new UnregisteredSailorPane($USER, new Regatta($reg_id));
    break;

    
    // --------------- ENTER FINISH--------------//
  case "finish":
  case "finishes":
    $PAGE = new EnterFinishPane($USER, new Regatta($reg_id));
    break;

    // --------------- DROP FINISH --------------//
  case "current":
  case "drop-finish":
    $PAGE = new DropFinishPane($USER, new Regatta($reg_id));
    break;


    // --------------- ENTER PENALTY ------------//
  case "penalty":
    $PAGE = new EnterPenaltyPane($USER, new Regatta($reg_id));
    break;

    // --------------- DROP PENALTY ------------//
  case "drop":
    $PAGE = new DropPenaltyPane($USER, new Regatta($reg_id));
    break;

    // --------------- TEAM PENALTY ------------//
  case "team-penalty":
    $PAGE = new TeamPenaltyPane($USER, new Regatta($reg_id));
    break;


    // --------------- SCORERS ---------------//
  case scorer:
    $PAGE = new ScorersPane($USER, new Regatta($reg_id));
    break;

    // --------------- Redirect HOME ---------- //
  default:
    $mes = sprintf("Invalid page requested (%s)", $_REQUEST['p']);
    $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
    header(sprintf("Location: %s/score/%s", HOME, $reg_id));
    exit;
  }
}
*/

$_SESSION['POST'] = $PAGE->process($_POST);
WebServer::goBack();
?>