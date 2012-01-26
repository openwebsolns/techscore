<?php
/**
 * Directs traffic while editing school preferences
 *
 * @author Dayan Paez
 * @created 2010-03-05
 */

require_once('conf.php');
require_once('WS.php');

//
// Is logged-in
//
if (Conf::$USER === null) {
  Session::s('last_page', preg_replace(':^/pedit/:', '/', $_SERVER['REQUEST_URI']));

  // provide the login page
  Session::pa(new PA("Please login to proceed.", PA::I));
  require_once('xml/WelcomePage.php');
  $PAGE = new WelcomePage();
  $PAGE->printXML();
  exit;
}

$HOME = sprintf("/prefs/%s", Conf::$USER->school->id);

//
// School
//
if (!isset($_REQUEST['school'])) {
  // Redirect to the user's school
  WS::go($HOME);
}
$SCHOOL = DB::getSchool(strtoupper($_REQUEST['school']));
if ($SCHOOL == null) {
  $mes = sprintf("No such school (%s).", $_REQUEST['school']);
  Session::pa(new PA($mes, PA::E));
  WS::go($HOME);
}
$schools = Conf::$USER->getSchools();
if (!isset($schools[$SCHOOL->id])) {
  $mes = sprintf("No permissions to edit school (%s).", $SCHOOL);
  Session::pa(new PA($mes, PA::E));
  WS::go($HOME);
}

//
// Requested page
//
if (!isset($_REQUEST['p'])) {
  // Go home by default
  require_once('prefs/PrefsHomePane.php');
  $PAGE = new PrefsHomePane(Conf::$USER, $SCHOOL);
}
else {
  switch ($_REQUEST['p']) {
  case "home":
    require_once('prefs/PrefsHomePane.php');
    $PAGE = new PrefsHomePane(Conf::$USER, $SCHOOL);
    break;

    // --------------- LOGO --------------- //
  case "logo":
  case "burgee":
    require_once('prefs/EditLogoPane.php');
    $PAGE = new EditLogoPane(Conf::$USER, $SCHOOL);
    break;

    // --------------- SAILOR ------------- //
  case "sailor":
  case "sailors":
    require_once('prefs/SailorMergePane.php');
    $PAGE = new SailorMergePane(Conf::$USER, $SCHOOL);
    break;

    // --------------- TEAMS ------------- //
  case "team":
  case "teams":
  case "name":
  case "names":
    require_once('prefs/TeamNamePrefsPane.php');
    $PAGE = new TeamNamePrefsPane(Conf::$USER, $SCHOOL);
    break;

  default:
    $mes = sprintf("No such page (%s).", $_REQUEST['p']);
    Session::pa(new PA($mes, PA::E));
    WS::go($HOME);
  }
}

if (isset($_GET['_action']) && $_GET['_action'] == 'edit') {
  $PAGE->process($_POST);
  WS::goBack('/');
}
$PAGE->getHTML(array());
?>