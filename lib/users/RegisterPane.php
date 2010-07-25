<?php
/**
 * This file is part of TechScore
 *
 */

require_once('conf.php');

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
   * "registration-step" variable in the $_SESSION
   *
   */
  public function fillContent() {
    $step = null;
    if (isset($_SESSION['POST']['registration-step']))
      $step = $_SESSION['POST']['registration-step'];
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
    unset($_SESSION['POST']['registration-step']);
  }

  /**
   * Create the form for new users to register
   */
  private function fillDefault() {
    $this->addContent($p = new Port("Request new account"));
    $p->addChild(new Para("Please note that TechScore is an online scoring program specifically " .
			  "designed for College Sailing regattas. As such, account access is given " .
			  "only to valid ICSA users, or as approved by the registration committee. " .
			  "If you are not affiliated with ICSA, you might be more interested in " .
			  "accessing the public site at <a href=\"http://regatta.mit.edu\">" .
			  "http://regatta.mit.edu</a>."));
    
    $p->addChild(new Para("Through this form you will be allowed to petition for an account on " .
			  "TechScore. Every field is mandatory. Please enter a " .
			  "valid e-mail account which you check as you will be sent an e-mail there to " .
			  "verify your identity."));

    $p->addChild(new Para("Once your account request has been approved by the registration committee, " .
			  "you will receive another e-mail from TechScore with instructions on " .
			  "logging in."));
    $p->addChild($f = new Form("register-edit", "post"));
    $f->addChild(new FItem("Email:", new FText("email", "")));
    $f->addChild(new FItem("First name:", new FText("first_name", "")));
    $f->addChild(new FItem("Last name:",  new FText("last_name", "")));
    $f->addChild(new FItem("Affiliation: ", $aff = new FSelect("school")));
    $f->addChild(new FItem("Role: ", $rol = new FSelect("role")));
    $f->addChild(new FSubmit("register", "Request account"));

    // Fill out the selection boxes
    foreach (Preferences::getConferences() as $conf) {
      $aff->addChild($opt = new OptionGroup($conf));
      foreach (Preferences::getSchoolsInConference($conf) as $school) {
	$opt->addChild(new Option($school->id, $school->name));
      }
    }
    $rol->addChild(new Option("coach", "Coach"));
    $rol->addChild(new Option("staff", "Staff"));
    $rol->addChild(new Option("student", "Student"));
  }

  /**
   * Helper method to display a messsage after a user requests an account
   *
   */
  private function fillRequested() {
    $this->addContent($p = new Port("Account requested"));
    $p->addChild(new Para("Thank you for registering for an account with TechScore. You should " .
			  "receive an e-mail message shortly with a link to verify your account access."));
    $p->addChild(new Para("If you don't receive an e-mail, please check your junk-mail settings " .
			  "and enable mail from <em>" . TS_FROM_MAIL . "</em>."));
  }

  /**
   * Helper method to display instructions for pending accounts
   *
   */
  private function fillPending() {
    $this->addContent($p = new Port("Account pending"));
    $p->addChild(new Para("Thank you for confirming your account. At this point, the registration " .
			  "committee has been notified of your request. They will review your request " .
			  "and approve or reject your account accordingly. Please allow up to three " .
			  "business days for this process."));
    $p->addChild(new Para("You will be notified of the committee's response to your request with an " .
			  "e-mail message."));
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
	$_SESSION['ANNOUNCE'][] = new Announcement("Email must not be empty.", Announcement::ERROR);
	return $args;
      }
      $acc = AccountManager::getAccount($email);
      if ($acc !== null) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid email provided.", Announcement::ERROR);
	return $args;
      }
      $acc = new Account();
      $acc->status = "requested";
      $acc->username = $email;
      
      // 2. Approve first and last name
      $acc->last_name  = trim(addslashes($args['last_name']));
      $acc->first_name = trim(addslashes($args['first_name']));
      if (empty($acc->last_name) || empty($acc->first_name)) {
	$_SESSION['ANNOUNCE'][] = new Announcement("User first and last name must not be empty.",
						   Announcement::ERROR);
	return $args;
      }

      // 3. Affiliation
      $acc->school = Preferences::getSchool(trim(addslashes($args['school'])));
      if ($acc->school === null) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid school affiliation requested.",
						   Announcement::ERROR);
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
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid role, assumed staff.", Announcement::WARNING);
      }

      // 5. Create account with status "requested";
      $res = Preferences::mail($to->username, '[TechScore] New account request', $this->getMessage($to));
      if ($res !== false) {
	AccountManager::setAccount($acc);
	$_SESSION['ANNOUNCE'][] = new Announcement("Account successfully created.");
	return array("registration-step"=>1);
      }
      $_SESSION['ANNOUNCE'][] = new Announcement("There was an error with your request. " .
						 "Please try again later.",
						 Announcement::ERROR);
      return $args;
    }

    // Mail verification
    if (isset($args['acc'])) {
      $hash = preg_replace('/[^A-Za-z0-9]/', '', $args['acc']);
      $acc = AccountManager::getAccountFromHash($hash);

      if ($acc === null) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid account to approve.",
						   Announcement::ERROR);
	return $args;
      }
      $acc->status = 'pending';
      AccountManager::setAccount($acc);
      $_SESSION['ANNOUNCE'][] = new Announcement("Account approved.");
      $_SESSION['POST'] = array('registration-step' => 2);
      Preferences::mail(ADMIN_MAIL, '[TechScore] New registration', $this->getAdminMessage($acc));
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
		   $to->first_name, ROOT, AccountManager::getHash($to));
  }
  public function getAdminMessage(Account $about) {
    return sprintf("Dear Administrators,\n\nThere is a new account request at TechScore. Please login " .
		   "and approve or reject as soon as possible.\n\n" .
		   "  Name: %s %s\n" .
		   " Email: %s\n" .
		   "School: %s\n" .
		   "  Role: %s\n",
		   $about->first_name, $about->last_name, $about->username,
		   $about->school->nick_name, $about->role);
  }
}
?>