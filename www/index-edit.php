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
  header("Location: .");
  exit;
}
$USER = null;
try {
  $USER = new User($_SESSION['user']);
}
catch (Exception $e) {
  print(new WelcomePage());
  return;
}

$PAGE = null;
if (!isset($_REQUEST['p']))
  $PAGE = new UserHomePane($USER);
else {
  switch ($_REQUEST['p']) {
  case "home":
    $PAGE = new UserHomePane($USER);
    break;

  case "inbox":
    $PAGE = new MessagePane($USER);
    break;

  case "create":
    $PAGE = new NewRegattaPane($USER);
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
