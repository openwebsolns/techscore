<?php
  /**
   * Manages logging-in and out. When logging in, make sure that the
   * user is either accepted or active.
   *
   * @author Dayan Paez
   * @created 2009-11-30
   */

require_once('conf.php');
require_once('tscore/WS.php');

//
// Log-out
//
if (isset($_REQUEST['dir']) && $_REQUEST['dir'] == "out") {
  Session::s('user', null);
  session_destroy();
  WS::go('/');
}

//
// Log-in
//

$userid = (isset($_POST['userid'])) ? trim($_POST['userid']) : WS::goBack('/');
$passwd = (isset($_POST['pass']))   ? $_POST['pass'] : WS::goBack('/');

$user = DB::getAccount($userid);
if ($user !== null && $user->password === sha1($passwd))
  Session::s('user', $user->id);
else
  Session::pa(new PA("Invalid username/password.", PA::E));

$def = Session::g('last_page');
if ($def === null)
  $def = '/';
WS::goBack($def);
?>
