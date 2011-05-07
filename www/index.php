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
session_start();

// ------------------------------------------------------------
// Not logged-in?
// ------------------------------------------------------------
if (!(isset($_SESSION['user']))) {
  // Registration?
  if (isset($_GET['p'])) {
    switch ($_GET['p']) {
    case 'register':
      $PAGE = new RegisterPane();
      break;
      
    case 'password-recover':
      $PAGE = new PasswordRecoveryPane();
      break;

    case 'home':
      $_SESSION['ANNOUNCE'][] = new Announcement("Please login to proceed.", Announcement::WARNING);
      $PAGE = new WelcomePage();
      $PAGE->printHTML();
      exit;

    default:
      $_SESSION['last_page'] = preg_replace(':^/edit/:', '/', $_SERVER['REQUEST_URI']);

      // provide the login page
      $_SESSION['ANNOUNCE'][] = new Announcement("Please login to proceed.", Announcement::WARNING);
      $PAGE = new WelcomePage();
      $PAGE->printHTML();
      exit;
    }
    if (isset($_GET['_action']) && $_GET['_action'] == 'edit') {
      $_SESSION['POST'] = $PAGE->process($_REQUEST);
      WebServer::goBack();
    }
    $PAGE->printHTML();
    exit;
  }

}

// ------------------------------------------------------------
// Invalid login?
// ------------------------------------------------------------
$USER = null;
try {
  $USER = new User($_SESSION['user']);
}
catch (Exception $e) {
  unset($_SESSION['user']);
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
  $PAGE = new EULAPane($USER);
}
else {
  AccountManager::requireActive($USER);
  switch ($page) {
  case "home":
    $PAGE = new UserHomePane($USER);
    break;

  case "inbox":
    $PAGE = new MessagePane($USER);
    break;

  case "create":
    $PAGE = new NewRegattaPane($USER);
    break;

  case "pending":
    $PAGE = new PendingAccountsPane($USER);
    break;

  case "venues":
  case "venue":
    $PAGE = new VenueManagement($USER);
    break;

  case "edit-venue":
    $PAGE = new VenueManagement($USER, VenueManagement::TYPE_EDIT);
    break;

  case "boat":
  case "boats":
    $PAGE = new BoatManagement($USER);
  break;

  case "account":
  case "accounts":
    $PAGE = new AccountPane($USER);
  break;

  case "compare-sailors":
    $PAGE = new CompareSailors($USER);
    break;

  case "aa":
    $PAGE = new AllAmerican($USER);
    break;

  default:
    $_SESSION['ANNOUNCE'][] = new Announcement(sprintf("Invalid page requested (%s).", $_REQUEST['p']),
					       Announcement::ERROR);
    WebServer::go('/');
  }
}
if (isset($_GET['_action']) && $_GET['_action'] == 'edit') {
  $_SESSION['POST'] = $PAGE->process($_REQUEST);
  WebServer::goBack();
}
$args = array_merge($_GET, (isset($_SESSION['POST']) && is_array($_SESSION['POST'])) ?
		    $_SESSION['POST'] :
		    array());
$PAGE->getHTML($args);
?>
