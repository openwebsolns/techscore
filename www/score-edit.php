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
  WebServer::go('/');
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
$reg_id = (int)$_REQUEST['reg'];
if (!Preferences::getObjectWithProperty($USER->getRegattas(), "id", $reg_id)) {
  // No jurisdiction
  WebServer::go('/');
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
	WebServer::go('score/'.$REG->id());
      }
    }
  }
  if ($PAGE === null) {
    $mes = sprintf("Invalid page requested (%s)", $_REQUEST['p']);
    $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::WARNING);
    WebServer::go('score/'.$reg_id);
  }
}

$_SESSION['POST'] = $PAGE->process($_POST);
if (LOG_MEMORY)
  error_log(sprintf("%s:\t%d\n", $_SERVER['REQUEST_URI'], memory_get_peak_usage()), 3, "../log/memory.log");
WebServer::goBack();
?>