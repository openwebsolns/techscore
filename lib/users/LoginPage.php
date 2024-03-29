<?php
use \metrics\TSMetric;
use \users\AbstractUserPane;
use \xml5\SessionParams;

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
    parent::__construct("Login");
  }

  /**
   * Sets up the body of this page
   *
   */
  protected function fillHTML(Array $args) {
    if (Conf::$USER !== null)
      $this->redirect('');

    // LOGIN MENU
    $this->PAGE->addContent($p = new XPort("Sign-in"));
    $p->set('id', 'login-port');
    $p->add($form = $this->createForm());
    $form->set('class', 'no-check-session');
    $form->add(new FReqItem("Email:", new XEmailInput('userid', "", array("maxlength"=>"40"))));
    $form->add($fi = new FReqItem("Password:", new XPasswordInput("pass", "", array("maxlength"=>"48"))));
    $fi->add(new XMessage(new XA('/password-recover', "Forgot your password?")));
    $form->add(new FItem("", new FCheckbox('remember', 1, "Keep me signed in")));

    $form->add(new XSubmitP("login", "Login"));

    // ANNOUNCEMENTS?
    $entry = DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::ANNOUNCEMENTS);
    if ($entry !== null && $entry->html !== null) {
      $this->PAGE->addContent($p = new XPort("Announcements"));
      $p->add(new XRawText($entry->html));
    }
  }

  /**
   * Override parent in order to supply 403 header
   *
   */
  public function processGET(Array $args) {
    header('HTTP/1.1 403 Forbidden');
    parent::processGET($args);
  }

  public function process(Array $args) {
    if (Conf::$USER !== null)
      $this->redirect('');

    // ------------------------------------------------------------
    // Log-in
    // ------------------------------------------------------------
    $userid = DB::$V->reqString($args, 'userid', 1, 101, "No username provided.");
    $passwd = DB::$V->reqRaw($args, 'pass', 1, 101, "Please enter a password.");
    $remember = DB::$V->incInt($args, 'remember', 1, 2, null);

    $user = DB::getAccountByEmail($userid);
    if ($user === null) {
      TSMetric::publish(Metric::INVALID_USERNAME);
      throw new SoterException("Invalid username/password.");
    }
    $hash = DB::createPasswordHash($user, $passwd);
    if ($user->password !== $hash) {
      TSMetric::publish(Metric::INVALID_PASSWORD);
      throw new SoterException("Invalid username/password.");
    }
    if (is_array(Conf::$DEBUG_USERS) && !in_array($user->email, Conf::$DEBUG_USERS)) {
      throw new SoterException("We apologize, but log in has been disabled temporarily. Please try again later.");
    }

    $def = Session::g('last_page');

    // If "remember", then destroy session and create a new,
    // long-lasting one
    if ($remember !== null) {
      // How many active sessions exist, and how many allowed
      $limit = DB::g(STN::LONG_SESSION_LIMIT);
      if ($limit !== null) {
        $other = TSSessionHandler::getLongTermActive($user);
        $count = count($other);
        $removed = 0;
        for ($i = $count - 1; $i >= $limit - 1; $i--) {
          DB::remove($other[$i]);
          $removed++;
        }
      }      

      session_regenerate_id(true);
      $id = session_id();
      session_destroy();
      session_id($id);
      session_set_cookie_params(345600, WS::link('/'), Conf::$HOME, true, true);
      session_start();
      TSSessionHandler::setLifetime(864000);
      if ($removed > 0)
        Session::pa(new PA(sprintf("Removed %d other long-term session(s) for security reasons.", $removed), PA::I));
    }
    Session::s(SessionParams::USER, $user->id);

    if ($def === null)
      $def = '/';

    Session::commit();
  }

  /**
   * Override parent in order to handle API calls differently
   *
   */
  public function processPOST(Array $args) {
    if (isset($_SERVER['HTTP_API']) && $_SERVER['HTTP_API'] == 'application/json') {
      try {
        $this->process($args);
        exit(0);
      } catch (SoterException $e) {
        header('HTTP/1.1 403 Forbidden');
        echo $e->getMessage();
        exit(0);
      }
    }
    else
      return parent::processPOST($args);
  }
}
?>
