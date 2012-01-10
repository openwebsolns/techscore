<?php
/**
 * Gateway to the program TechScore. Manage all session information
 * and direct traffic.
 *
 * @author Dayan Paez
 * @version 2.0
 * @created 2009-10-16
 */

require_once("conf.php");
require_once("tscore/WebServer.php");

// ------------------------------------------------------------
// Not logged-in?
// ------------------------------------------------------------
if (!Session::has('user')) {
  // Registration?
  if (isset($_GET['p'])) {
    switch ($_GET['p']) {
    case 'register':
      require_once('users/RegisterPane.php');
      $PAGE = new RegisterPane();
      break;
      
    case 'password-recover':
      require_once('users/PasswordRecoveryPane.php');
      $PAGE = new PasswordRecoveryPane();
      break;

    case 'home':
    default:
      Session::s('last_page', preg_replace(':^/edit/:', '/', $_SERVER['REQUEST_URI']));

      // provide the login page
      Session::pa(new PA("Please login to proceed.", PA::I));
      require_once('xml/WelcomePage.php');
      $PAGE = new WelcomePage();
      $PAGE->printXML();
      exit;
    }
    if (isset($_GET['_action']) && $_GET['_action'] == 'edit') {
      Session::s('POST', $PAGE->process($_REQUEST));
      WebServer::goBack();
    }
    $PAGE->printXML();
    exit;
  }
}

// ------------------------------------------------------------
// Invalid login?
// ------------------------------------------------------------
$USER = null;
try {
  $USER = new User(Session::g('user'));
}
catch (Exception $e) {
  Session::s('user', null);
  WebServer::go('/');
}

// ------------------------------------------------------------
// Process requested page
// ------------------------------------------------------------
$page = "home";
if (isset($_REQUEST['p']))
  $page = $_REQUEST['p'];
  
// Check for license request
$PAGE = null;
if ($page == "license") {
  require_once('users/EULAPane.php');
  $PAGE = new EULAPane($USER);
}
else {
  DB::requireActive($USER);
  switch ($page) {
  case "home":
    require_once('users/UserHomePane.php');
    $PAGE = new UserHomePane($USER);
    break;

  case "inbox":
    require_once('users/MessagePane.php');
    $PAGE = new MessagePane($USER);
    break;

  case "create":
    require_once('users/NewRegattaPane.php');
    $PAGE = new NewRegattaPane($USER);
    break;

  case "pending":
    require_once('users/admin/PendingAccountsPane.php');
    $PAGE = new PendingAccountsPane($USER);
    break;

  case "venues":
  case "venue":
    require_once('users/admin/VenueManagement.php');
    $PAGE = new VenueManagement($USER);
    break;

  case "edit-venue":
    require_once('users/admin/VenueManagement.php');
    $PAGE = new VenueManagement($USER, VenueManagement::TYPE_EDIT);
    break;

  case "boat":
  case "boats":
    require_once('users/admin/BoatManagement.php');
    $PAGE = new BoatManagement($USER);
  break;

  case "account":
  case "accounts":
    require_once('users/AccountPane.php');
    $PAGE = new AccountPane($USER);
  break;

  case "compare-by-race":
    require_once('users/CompareSailorsByRace.php');
    $PAGE = new CompareSailorsByRace($USER);
    break;

  case "compare-sailors":
  case "compare-head-to-head":
  case "compare-head-head":
  case "head-to-head":
    require_once('users/CompareHeadToHead.php');
    $PAGE = new CompareHeadToHead($USER);
    break;

  case "aa":
    require_once('users/AllAmerican.php');
    $PAGE = new AllAmerican($USER);
    break;

  case "send-message":
  case "send-messages":
  case "send-email":
  case "send-emails":
    require_once('users/admin/SendMessage.php');
    $PAGE = new SendMessage($USER);
  break;

  default:
    Session::pa(new PA(sprintf("Invalid page requested (%s).", $_REQUEST['p']), PA::E));
    WebServer::go('/');
  }
}
if (isset($_GET['_action']) && $_GET['_action'] == 'edit') {
  Session::s('POST', $PAGE->process($_REQUEST));
  WebServer::goBack();
}
$post = Session::g('POST');
$args = array_merge($_GET, (is_array($post)) ? $post : array());
$PAGE->getHTML($args);
?>
