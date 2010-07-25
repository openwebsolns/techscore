<?php
  /**
   * Edits traffic generated from index.php
   *
   * @author Dayan Paez
   * @version 2.0
   * @created 2009-10-16
   */
require_once("../lib/conf.php");
session_start();

//
// Logged-in?
//
if (!(isset($_SESSION['user']))) {
  // Registration?
  if (isset($_GET['p']) && $_GET['p'] == "register") {
    $PAGE = new RegisterPane();
    $_SESSION['POST'] = $PAGE->process($_REQUEST);
    WebServer::goBack();
    exit;
  }
  
  // Send home instead
  WebServer::go(HOME);
}
$USER = null;
try {
  $USER = new User($_SESSION['user']);
}
catch (Exception $e) {
  WebServer::go(HOME);
}

$page = "home";
if (isset($_REQUEST['p']))
  $page = $_REQUEST['p'];
  
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

  default:
    $_SESSION['ANNOUNCE'][] = new Announcement("No such page.", Announcement::ERROR);
    header("Location: .");
    exit;
  }
}

$_SESSION['POST'] = $PAGE->process($_REQUEST);
WebServer::goBack();
?>
