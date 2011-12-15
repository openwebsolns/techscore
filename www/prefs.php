<?php
/**
 * Directs traffic while editing school preferences
 *
 * @author Dayan Paez
 * @created 2010-03-05
 */

require_once('conf.php');
require_once('xml/Announcement.php');
require_once('tscore/WebServer.php');

session_start();

//
// Is logged-in
//
if (!(isset($_SESSION['user']))) {
  $_SESSION['last_page'] = preg_replace(':^/pedit/:', '/', $_SERVER['REQUEST_URI']);

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

$HOME = sprintf("/prefs/%s", $USER->get(User::SCHOOL)->id);

//
// School
//
if (!isset($_REQUEST['school'])) {
  // Redirect to the user's school
  WebServer::go($HOME);
}
$SCHOOL = Preferences::getSchool(strtoupper($_REQUEST['school']));
if ($SCHOOL == null) {
  $mes = sprintf("No such school (%s).", $_REQUEST['school']);
  $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::ERROR);
  WebServer::go($HOME);
}
$schools = $USER->getSchools();
if (!isset($schools[$SCHOOL->id])) {
  $mes = sprintf("No permissions to edit school (%s).", $SCHOOL);
  $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::ERROR);
  WebServer::go($HOME);
}

//
// Requested page
//
if (!isset($_REQUEST['p'])) {
  // Go home by default
  require_once('prefs/PrefsHomePane.php');
  $PAGE = new PrefsHomePane($USER, $SCHOOL);
}
else {
  switch ($_REQUEST['p']) {
  case "home":
    require_once('prefs/PrefsHomePane.php');
    $PAGE = new PrefsHomePane($USER, $SCHOOL);
    break;

    // --------------- LOGO --------------- //
  case "logo":
  case "burgee":
    require_once('prefs/EditLogoPane.php');
    $PAGE = new EditLogoPane($USER, $SCHOOL);
    break;

    // --------------- SAILOR ------------- //
  case "sailor":
  case "sailors":
    require_once('prefs/SailorMergePane.php');
    $PAGE = new SailorMergePane($USER, $SCHOOL);
    break;

    // --------------- TEAMS ------------- //
  case "team":
  case "teams":
  case "name":
  case "names":
    require_once('prefs/TeamNamePrefsPane.php');
    $PAGE = new TeamNamePrefsPane($USER, $SCHOOL);
    break;

  default:
    $mes = sprintf("No such page (%s).", $_REQUEST['p']);
    $_SESSION['ANNOUNCE'][] = new Announcement($mes, Announcement::ERROR);
    WebServer::go($HOME);
  }
}

if (isset($_GET['_action']) && $_GET['_action'] == 'edit') {
  $PAGE->process($_POST);
  WebServer::goBack();
}
$PAGE->getHTML(array());
?>