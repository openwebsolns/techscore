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
  $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
  header("Location: " . HOME);
  return;
}
$USER = null;
try {
  $USER = new User($_SESSION['user']);
}
catch (Exception $e) {
  $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
  header("Location: " . HOME);
  return;
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
// Content, whether dialog ("v" or editing pane "p")
//
$PAGE = null;
if (!isset($_REQUEST['p']) &&
    !isset($_REQUEST['v']) &&
    !isset($_REQUEST['d'])) {
  $mes = "No page requested.";
  $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
  header(sprintf("Location: %s/score/%s", HOME, $reg_id));
  exit;
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
    /*
    switch ($_REQUEST['p']) {

      // --------------- HOME    ---------------//
    case "home":
    case "details":
      $PAGE = new DetailsPane($USER, $REG);
      break;

      // --------------- SUMMARIES -------------//
    case "summary":
    case "summaries":
    case "comment":
    case "comments":
      $PAGE = new SummaryPane($USER, $REG);
      break;

      // --------------- RACES   ---------------//
    case "race":
    case "races":
      $PAGE = new RacesPane($USER, $REG);
      break;

      // --------------- TEAMS   ---------------//
    case "school":
    case "schools":
    case "team":
    case "teams":
      $PAGE = new TeamsPane($USER, $REG);
      break;

      // --------------- NOTES   ---------------//
    case "note":
    case "notes":
      $PAGE = new NotesPane($USER, $REG);
      break;
  
      // --------------- ROTATIONS   ---------------//
    case "sail":
    case "sails":
      $PAGE = new SailsPane($USER, $REG);
      break;

      // --------------- TWEAK SAILS ---------------//
    case "tweak":
      $PAGE = new TweakSailsPane($USER, $REG);
      break;

      // --------------- MANUAL TWEAK -------------//
    case "manual-tweak":
      $PAGE = new ManualTweakPane($USER, $REG);
      break;

      // --------------- RP FORMS -----------------//
    case "rp":
      $PAGE = new RpEnterPane($USER, $REG);
      break;

      // --------------- UNREG SAILORS-------------//
    case "temp":
      $PAGE = new UnregisteredSailorPane($USER, $REG);
      break;
    

      // --------------- SCORERS ---------------//
    case "scorer":
      $PAGE = new ScorersPane($USER, $REG);
      break;

      // --------------- ENTER FINISH--------------//
    case "finish":
    case "finishes":
      $PAGE = new EnterFinishPane($USER, $REG);
      break;

      // --------------- DROP FINISH --------------//
    case "current":
    case "drop-finish":
      $PAGE = new DropFinishPane($USER, $REG);
      break;


      // --------------- ENTER PENALTY ------------//
    case "penalty":
      $PAGE = new EnterPenaltyPane($USER, $REG);
      break;

      // --------------- DROP PENALTY ------------//
    case "drop":
      $PAGE = new DropPenaltyPane($USER, $REG);
      break;

      // --------------- TEAM PENALTY ------------//
    case "team-penalty":
      $PAGE = new TeamPenaltyPane($USER, $REG);
      break;

      // --------------- Redirect HOME ---------- //
    default:
    }
    */
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
      header(sprintf("Location: %s/view/%s/rotation", HOME, $REG->id()));
      exit;
    }
  }
}

//
// - Downloads
//
else {
  $st = $REG->get(Regatta::START_TIME);
  $nn = Utilities::createNick($REG->get(Regatta::NAME));
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
    $writer = new RpFormWriter($REG);
    $writer->makePDF("$name");

    header('Content-type: application/pdf');
    header(sprintf('Content-Disposition: attachment; filename="%s.pdf"', $name));
    readfile("$name.pdf");
    unlink("$name.pdf");
    break;

    
    // --------------- default ---------------//
  default:
    $mes = sprintf("Invalid download requested (%s)", $_REQUEST['d']);
    $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
    header(sprintf("Location: %s", $_SERVER['HTTP_REFERER']));
  }
  exit;
}

$args = $_REQUEST;
if (isset($_SESSION['POST']))
  $args = array_merge($args,$_SESSION['POST']);
print($PAGE->getHTML($args));
?>