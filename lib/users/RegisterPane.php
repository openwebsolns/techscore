<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('xml/WelcomePage.php');

/**
 * Allows for web users to petition for an account. In version 2.0 of
 * TechScore, the process of acquiring a new account requires the
 * following steps:
 *
 * <ul>
 *
 * <li>USER requests an account online. At this point, the account
 * status is REQUESTED.</li>
 *
 * <li>User proves that account is valid by clicking on e-mail message
 * sent to e-mail specified in step 1. The status becomes
 * PENDING.</li>
 *
 * <li>At this point, an administrative user from TechScore receives
 * an e-mail message saying there is a request for membership, at
 * which point the administrator can ACCEPT or REJECT the account
 * request.</li>
 *
 * <li>User logs in using the credentials created in Step 1 and must
 * sign the EULA, at which the point the account is fully ACTIVE and
 * ready to use.</li>
 *
 * </ul>
 *
 * This page deals with Step 1 of the above process.
 *
 * @author Dayan Paez
 * @version 2010-07-21
 */
class RegisterPane extends WelcomePage {

  /**
   * Fills web page depending on registration status, i.e. the
   * "registration-step" variable in the session.
   *
   */
  public function fillContent() {
    $step = null;
    $post = Session::g('POST');
    if (isset($post['registration-step']))
      $step = $post['registration-step'];
    elseif (isset($_REQUEST['registration-step']))
      $step = $_REQUEST['registration-step'];

    switch ($step) {
    case 2:
      $this->fillPending();
      break;
      
    case 1:
      $this->fillRequested();
      break;

    default:
      $this->fillDefault();
    }
    unset($post['registration-step']);
    Session::s('POST', $post);
  }

  /**
   * Create the form for new users to register
   */
  private function fillDefault() {
    $this->addContent(new XPageTitle("Registration"));
    $this->addContent($p = new XPort("Request new account"));
    $p->add(new XP(array(),
		   array("Please note that TechScore is an online scoring program specifically designed for College Sailing regattas. As such, account access is given only to valid ICSA users, or as approved by the registration committee. If you are not affiliated with ICSA, you might be more interested in accessing the public site at ",
			 new XA(Conf::$PUB_HOME, Conf::$PUB_HOME), ".")));
    
    $p->add(new XP(array(), "Through this form you will be allowed to petition for an account on TechScore. Every field is mandatory. Please enter a valid e-mail account which you check as you will be sent an e-mail there to verify your identity."));

    $p->add(new XP(array(), "Once your account request has been approved by the registration committee, you will receive another e-mail from TechScore with instructions on logging in."));
    $p->add($f = new XForm("/register-edit", XForm::POST));
    $f->add(new FItem("Email:", new XTextInput("email", "")));
    $f->add(new FItem("First name:", new XTextInput("first_name", "")));
    $f->add(new FItem("Last name:",  new XTextInput("last_name", "")));
    $f->add(new FItem("Password:", new XPasswordInput("passwd", "")));
    $f->add(new FItem("Confirm password:", new XPasswordInput("confirm", "")));
    $f->add(new FItem("Affiliation: ", $aff = new XSelect("school")));
    $f->add(new FItem("Role: ", XSelect::fromArray('role', Account::getRoles())));
    $f->add(new XSubmitInput("register", "Request account"));

    // Fill out the selection boxes
    foreach (DB::getConferences() as $conf) {
      $aff->add($opt = new FOptionGroup($conf));
      foreach ($conf->getSchools() as $school) {
	$opt->add(new FOption($school->id, $school->name));
      }
    }
  }

  /**
   * Helper method to display a messsage after a user requests an account
   *
   */
  private function fillRequested() {
    $this->addContent($p = new XPort("Account requested"));
    $p->add(new XP(array(), "Thank you for registering for an account with TechScore. You should receive an e-mail message shortly with a link to verify your account access."));
    $p->add(new XP(array(),
		   array("If you don't receive an e-mail, please check your junk-mail settings and enable mail from ",
			 new XEm(Conf::$TS_FROM_MAIL), ".")));
  }

  /**
   * Helper method to display instructions for pending accounts
   *
   */
  private function fillPending() {
    $this->addContent($p = new XPort("Account pending"));
    $p->add(new XP(array(), "Thank you for confirming your account. At this point, the registration committee has been notified of your request. They will review your request and approve or reject your account accordingly. Please allow up to three business days for this process."));
    $p->add(new XP(array(), "You will be notified of the committee's response to your request with an e-mail message."));
  }

  /**
   * Processes the requests made from filling out the form above
   *
   */
  public function process(Array $args) {
    if (isset($args['register'])) {
      // 1. Check for existing account
      $email = trim(addslashes($args['email']));
      if (strlen($email) == 0) {
	Session::pa(new PA("Email must not be empty.", PA::E));
	return $args;
      }
      $acc = DB::getAccount($email);
      if ($acc !== null) {
	Session::pa(new PA("Invalid email provided.", PA::E));
	return $args;
      }
      $acc = new Account();
      $acc->status = "requested";
      $acc->id = $email;
      
      // 2. Approve first and last name
      $acc->last_name  = trim(addslashes($args['last_name']));
      $acc->first_name = trim(addslashes($args['first_name']));
      if (empty($acc->last_name) || empty($acc->first_name)) {
	Session::pa(new PA("User first and last name must not be empty.", PA::E));
	return $args;
      }

      // 3. Affiliation
      $acc->school = DB::getSchool(trim(addslashes($args['school'])));
      if ($acc->school === null) {
	Session::pa(new PA("Invalid school affiliation requested.", PA::E));
	return $args;
      }

      // 4. Role (assume Staff if not recognized)
      $role = strtolower($args['role']);
      switch ($role) {
      case "student":
      case "coach":
      case "staff":
	$acc->role = $role;
      break;
      
      default:
	$acc->role = "staff";
	Session::pa(new PA("Invalid role, assumed staff.", PA::I));
      }

      // 5. Approve password
      if (!isset($args['passwd']) || !isset($args['confirm']) ||
	  $args['passwd'] != $args['confirm'] ||
	  strlen(trim($args['passwd'])) < 8) {
	Session::pa(new PA("Invalid or missing password. Make sure the passwords match and that it is at least 8 characters long.", PA::E));
	return $args;
      }
      $acc->password = sha1(trim($args['passwd']));

      // 6. Create account with status "requested";
      $res = DB::mail($acc->id, '[TechScore] New account request', $this->getMessage($acc));
      if ($res !== false) {
	DB::set($acc);
	Session::pa(new PA("Account successfully created."));
	return array("registration-step"=>1);
      }
      Session::pa(new PA("There was an error with your request. Please try again later.", PA::E));
      return $args;
    }

    // Mail verification
    if (isset($args['acc'])) {
      $hash = trim($args['acc']);
      $acc = DB::getAccountFromHash($hash);

      if ($acc === null) {
	Session::pa(new PA("Invalid account to approve.", PA::E));
	return $args;
      }
      $acc->status = 'pending';
      DB::set($acc);
      Session::pa(new PA("Account verified. Please wait until the account is approved. You will be notified by mail."));
      Session::s('POST', array('registration-step' => 2));
      // notify all admins
      $admins = array();
      foreach (DB::getAdmins() as $admin)
	$admins[] = sprintf('%s <%s>', $admin->getName(), $admin->id);

      DB::mail(implode(',', $admins), '[TechScore] New registration', $this->getAdminMessage($acc));
      WebServer::go("register");
    }
    return $args;
  }

  public function getMessage(Account $to) {
    return sprintf("Dear %s,\n\nYou are receiving this message because you, or someone in your name, " .
		   "has requested an account at TechScore under this e-mail address. If you did not " .
		   "request an account with TechScore, kindly disregard this message.\n\n" .
		   "To activate your account, you will need to follow the link below. You may need to " .
		   "copy and paste the link into your browser's location bar. After you follow the " .
		   "instructions, your account request will be sent to the registration committee for " .
		   "approval. You will be notified as soon as your account is activated.\n\n" .
		   "%s/register/acc=%s\n\nThank you,\n\nTechScore Administration",
		   $to->first_name, Conf::$HOME, DB::getHash($to));
  }
  public function getAdminMessage(Account $about) {
    return sprintf("Dear Administrators,\n\nThere is a new account request at TechScore. Please login " .
		   "and approve or reject as soon as possible.\n\n" .
		   "  Name: %s %s\n" .
		   " Email: %s\n" .
		   "School: %s\n" .
		   "  Role: %s\n",
		   $about->first_name, $about->last_name, $about->id,
		   $about->school->nick_name, $about->role);
  }
}
?>
