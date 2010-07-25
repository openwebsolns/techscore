<?php
/**
 * Directs traffic while editing school preferences
 *
 * @author Dayan Paez
 * @created 2010-03-05
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

$HOME = sprintf("prefs/%s", $USER->get(User::SCHOOL)->id);

//
// School
//
if (!isset($_REQUEST['school'])) {
  WebServer::go($HOME);
}

$SCHOOL = Preferences::getSchool(strtoupper($_REQUEST['school']));

if ($SCHOOL == null) {
  $mes = sprintf("No such school (%s).", $_REQUEST['school']);
  $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::ERROR);
  WebServer::go($HOME);
}
elseif ($SCHOOL != $USER->get(User::SCHOOL) && !$USER->get(User::ADMIN)) {
  $mes = sprintf("No permission to edit school (%s).", $SCHOOL);
  $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::ERROR);
  WebServer::go($HOME);
}

switch ($_REQUEST['p']) {
case "home":
  $PAGE = new PrefsHomePane($USER, $SCHOOL);
  break;

  // --------------- LOGO --------------- //
case "logo":
case "burgee":
  $PAGE = new EditLogoPane($USER, $SCHOOL);
  break;

  // --------------- SAILOR ------------- //
case "sailor":
case "sailors":
  $PAGE = new SailorMergePane($USER, $SCHOOL);
  break;

  // --------------- TEAMS ------------- //
case "team":
case "teams":
case "name":
case "names":
  $PAGE = new TeamNamePrefsPane($USER, $SCHOOL);
  break;

default:
  $mes = sprintf("No such page (%s).", $_REQUEST['p']);
  $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::ERROR);
  WebServer::go($HOME);
}

$PAGE->process($_POST);
WebServer::goBack();
?>