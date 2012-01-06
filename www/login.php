<?php
  /**
   * Manages logging-in and out. When logging in, make sure that the
   * user is either accepted or active.
   *
   * @author Dayan Paez
   * @created 2009-11-30
   */

require_once('conf.php');
require_once('tscore/WebServer.php');

//
// Log-out
//
if (isset($_REQUEST['dir']) && $_REQUEST['dir'] == "out") {
  Session::s('user', null);
  session_destroy();
  WebServer::go('/');
}

//
// Log-in
//

$userid = (isset($_POST['userid'])) ? trim($_POST['userid']) : WebServer::goBack();
$passwd = (isset($_POST['pass']))   ? $_POST['pass'] : WebServer::goBack();

$user = AccountManager::approveUser($userid, $passwd);
if ($user !== null) {
  Session::s('user', $user->username());
}

$def = Session::g('last_page');
if ($def === null)
  $def = '/';
WebServer::goBack($def);
?>
