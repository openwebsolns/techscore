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
    $this->page_url = 'login';
  }

  /**
   * Sets up the body of this page
   *
   */
  protected function fillHTML(Array $args) {
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

    // LOGIN MENU
    $this->PAGE->addContent($p = new XPort("Sign-in"));
    $p->add($form = $this->createForm());
    $form->add(new FItem("Email:", new XTextInput("userid", "", array("maxlength"=>"40"))));
    $form->add($fi = new FItem("Password:", new XPasswordInput("pass", "", array("maxlength"=>"48"))));
    $fi->add(new XMessage(new XA('/password-recover', "Forgot your password?")));

    $form->add(new XSubmitP("login", "Login"));

    // ANNOUNCEMENTS?
    $entry = DB::get(DB::$TEXT_ENTRY, Text_Entry::ANNOUNCEMENTS);
    if ($entry !== null && $entry->html !== null) {
      $this->PAGE->addContent($p = new XPort("Announcements"));
      $p->add(new XRawText($entry->html));
    }

    if (Conf::$ALLOW_REGISTER) {
      $this->PAGE->addContent($p = new XPort("Register for TechScore"));
      $p->add(new XP(array(),
                     array("If you are affiliated with ",
                           new XA("http://www.collegesailing.org", "ICSA"),
                           " and would like an account with ", Conf::$NAME, ", you can ",
                           new XA("/register", "register here"), ".")));
    }
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
    $userid = DB::$V->reqString($args, 'userid', 1, 41, "No username provided.");
    $passwd = DB::$V->reqRaw($args, 'pass', 1, 101, "Please enter a password.");

    $user = DB::getAccount($userid);
    if ($user === null)
      throw new SoterException("Invalid username/password.");
    $hash = DB::createPasswordHash($user, $passwd);
    if ($user->password !== $hash)
      throw new SoterException("Invalid username/password.");
    if (is_array(Conf::$DEBUG_USERS) && !in_array($user->id, Conf::$DEBUG_USERS))
      throw new SoterException("We apologize, but log in has been disabled temporarily. Please try again later.");
    Session::s('user', $user->id);

    $def = Session::g('last_page');
    if ($def === null)
      $def = '/';
    WS::goBack($def);
  }
}
?>
