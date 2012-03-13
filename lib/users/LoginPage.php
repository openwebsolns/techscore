<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('users/AbstractUserPane.php');

/**
 * Welcome page
 *
 */
class LoginPage extends AbstractUserPane {

  /**
   * Create a new Welcome webpage, titled "Welcome", with a login
   * screen. If the user is already logged-in, redirect them to the
   * home page.
   *
   */
  public function __construct() {
    parent::__construct("Welcome!");
  }

  /**
   * Sets up the body of this page
   *
   */
  protected function fillHTML(Array $args) {
    if (Conf::$USER !== null)
      WS::go('/');
    Session::pa(new PA("Please login to proceed.", PA::I));

    // LOGIN MENU
    $this->PAGE->addContent($p = new XPort("Sign-in"));
    $p->add($form = new XForm("/dologin", XForm::POST));
    $form->add(new FItem("Username:", new XTextInput("userid", "", array("maxlength"=>"40"))));
    $form->add($fi = new FItem("Password:", new XPasswordInput("pass", "", array("maxlength"=>"48"))));
    $fi->add(new XMessage(new XA('/password-recover', "Forgot your password?")));

    $form->add(new XSubmitP("login", "Login"));

    // Announcements
    $this->PAGE->addContent($p = new XPort("Announcements"));
    $file = sprintf("%s/announcements.html", dirname(__FILE__));
    if (file_exists($file))
      $p->add(new XRawText(file_get_contents($file)));
    else
      $p->add(new XP(array(), "No announcements at this time."));

    $this->PAGE->addContent($p = new XPort("Register for TechScore"));
    $p->add(new XP(array(),
		   array("If you are affiliated with ",
			 new XA("http://www.collegesailing.org", "ICSA"),
			 " and would like an account with ", Conf::$NAME, ", you can ",
			 new XA("/register", "register here"), ".")));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Log-out
    // ------------------------------------------------------------
    if (isset($args['dir']) && $args['dir'] == "out") {
      Session::s('user', null);
      session_destroy();
      WS::go('/');
    }

    if (Conf::$USER !== null)
      WS::go('/');
    // ------------------------------------------------------------
    // Log-in
    // ------------------------------------------------------------
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
  }
}
?>
