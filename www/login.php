<?php
  /**
   * Manages logging-in and out
   *
   * @author Dayan Paez
   * @created 2009-11-30
   */

require_once('../lib/conf.php');
session_start();

//
// Log-out
//
if (isset($_REQUEST['dir']) && $_REQUEST['dir'] == "out") {
  unset($_SESSION['user']);
  session_destroy();
  header("Location: " . HOME);
  exit(0);
}

//
// Log-in
//

$userid = (isset($_POST['userid'])) ? trim($_POST['userid']) : goBack();
$passwd = (isset($_POST['pass']))   ? $_POST['pass'] : goBack();

$user = AccountManager::approveUser($userid, $passwd);
if ($user) {
  $_SESSION['user'] = $user->username();
}
$def = (isset($_SESSION['last_page'])) ? $_SESSION['last_page'] : ".";
WebServer::goBack($def);
?>