<?php
/**
 * Gateway to the program TechScore. Manage all session information
 * and direct traffic.
 *
 * @author Dayan Paez
 * @version 2.0
 * @created 2009-10-16
 */

require_once('conf.php');

// ------------------------------------------------------------
// Verify method
// ------------------------------------------------------------
if (!in_array(Conf::$METHOD, array('POST', 'GET')))
  Conf::do405();

// ------------------------------------------------------------
// Construct the URI
// ------------------------------------------------------------
$URI = explode('/', WS::unlink($_SERVER['REQUEST_URI'], true));
array_shift($URI);
$BASE = array_shift($URI);

// ------------------------------------------------------------
// Not logged-in?
// ------------------------------------------------------------
if (Conf::$USER === null) {
  // Registration?
  switch ($BASE) {
  case 'register':
    if (Conf::$ALLOW_REGISTER === false)
      WS::go('/');

    // When following mail verification, simulate POST
    if (count($URI) > 0) {
      Conf::$METHOD = 'POST';
      $_POST['acc'] = $URI[0];
    }
    require_once('users/RegisterPane.php');
    $PAGE = new RegisterPane();
    break;

  case 'password-recover':
    require_once('users/PasswordRecoveryPane.php');
    $PAGE = new PasswordRecoveryPane();
    break;

  default:
    Session::s('last_page', $_SERVER['REQUEST_URI']);

    // provide the login page
    require_once('users/LoginPage.php');
    $PAGE = new LoginPage();
    break;
  }
  if (Conf::$METHOD == 'POST') {
    Session::s('POST', $PAGE->processPOST($_POST));
    WS::goBack('/');
  }
  $PAGE->getHTML($_GET);
  exit;
}

// ------------------------------------------------------------
// Process requested page
// ------------------------------------------------------------
$page = "home";
if (isset($_GET['p']))
  $page = $_GET['p'];

// Check for license request
$PAGE = null;
if ($page == 'license') {
  require_once('users/EULAPane.php');
  $PAGE = new EULAPane(Conf::$USER);
}
else {
  DB::requireActive(Conf::$USER);
  switch ($page) {
  case 'login':
  case 'logout':
    require_once('users/LoginPage.php');
    $PAGE = new LoginPage();
    break;

    // ------------------------------------------------------------
    // Preferences
    // ------------------------------------------------------------

  case 'prefs-home':
    require_once('prefs/PrefsHomePane.php');
    $PAGE = new PrefsHomePane(Conf::$USER);
    break;

    // --------------- LOGO --------------- //
  case 'prefs-logo':
  case 'prefs-burgee':
    require_once('prefs/EditLogoPane.php');
    $PAGE = new EditLogoPane(Conf::$USER);
    break;

  // --------------- SAILOR ------------- //
  case 'prefs-sailor':
  case 'prefs-sailors':
    require_once('prefs/SailorMergePane.php');
    $PAGE = new SailorMergePane(Conf::$USER);
    break;

  // --------------- TEAMS ------------- //
  case 'prefs-team':
  case 'prefs-teams':
  case 'prefs-name':
  case 'prefs-names':
    require_once('prefs/TeamNamePrefsPane.php');
    $PAGE = new TeamNamePrefsPane(Conf::$USER);
    break;

    // ------------------------------------------------------------
    // User-related
    // ------------------------------------------------------------

  case "home":
    require_once('users/HomePane.php');
    $PAGE = new HomePane(Conf::$USER);
    break;

  case "archive":
    require_once('users/UserArchivePane.php');
    $PAGE = new UserArchivePane(Conf::$USER);
    break;

  case "inbox":
    require_once('users/MessagePane.php');
    $PAGE = new MessagePane(Conf::$USER);
    break;

  case "create":
    require_once('users/NewRegattaPane.php');
    $PAGE = new NewRegattaPane(Conf::$USER);
    break;

  case "pending":
    require_once('users/admin/PendingAccountsPane.php');
    $PAGE = new PendingAccountsPane(Conf::$USER);
    break;

  case "venues":
  case "venue":
    require_once('users/admin/VenueManagement.php');
    $PAGE = new VenueManagement(Conf::$USER);
    break;

  case "edit-venue":
    require_once('users/admin/VenueManagement.php');
    $PAGE = new VenueManagement(Conf::$USER, VenueManagement::TYPE_EDIT);
    break;

  case "boat":
  case "boats":
    require_once('users/admin/BoatManagement.php');
    $PAGE = new BoatManagement(Conf::$USER);
  break;

  case "account":
  case "accounts":
    require_once('users/AccountPane.php');
    $PAGE = new AccountPane(Conf::$USER);
  break;

  case "compare-by-race":
    require_once('users/CompareSailorsByRace.php');
    $PAGE = new CompareSailorsByRace(Conf::$USER);
    break;

  case "compare-sailors":
  case "compare-head-to-head":
  case "compare-head-head":
  case "head-to-head":
    require_once('users/CompareHeadToHead.php');
    $PAGE = new CompareHeadToHead(Conf::$USER);
    break;

  case "aa":
    require_once('users/AllAmerican.php');
    $PAGE = new AllAmerican(Conf::$USER);
    break;

  case "send-message":
  case "send-messages":
  case "send-email":
  case "send-emails":
    require_once('users/admin/SendMessage.php');
    $PAGE = new SendMessage(Conf::$USER);
  break;

  default:
    Session::pa(new PA(sprintf("Invalid page requested (%s).", $_GET['p']), PA::E));
    WS::go('/');
  }
}
if (Conf::$METHOD == 'POST') {
  Session::s('POST', $PAGE->processPOST($_POST));
  WS::goBack('/');
}
$post = Session::g('POST');
$args = array_merge((is_array($post)) ? $post : array(), $_GET);
$PAGE->getHTML($args);
?>
