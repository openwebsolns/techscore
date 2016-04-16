<?php
use \users\AbstractUserPane;

/**
 * Manages the user's own account information
 *
 * @author Dayan Paez
 * @version   2010-09-19
 */
class AccountPane extends AbstractUserPane {

  const SUBMIT_DELETE = 'delete';
  const INPUT_CONFIRM_DELETE = 'confirm-delete';

  private $new_email = null;
  private $new_token = null;

  public function __construct(Account $user) {
    parent::__construct("My Account", $user);

    $this->new_email = Session::g('new_email');
    $this->new_token = Session::g('new_token');
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Reset session
    // ------------------------------------------------------------
    if (array_key_exists('reset', $args)) {
      Session::d('usurped_user');
      $this->redirect();
    }

    // ------------------------------------------------------------
    // Verifying new e-mail?
    // ------------------------------------------------------------
    if ($this->new_email && $this->new_token) {
      $this->PAGE->addContent($p = new XPort("Change e-mail/username"));
      $p->add(new XWarning(
                     array("You are currently in the process of changing your e-mail address to ",
                           new XStrong($this->new_email),
                           ". To continue, please enter the token sent to that address below. Please note that this process will be abandoned if you logout without verifying new e-mail address.")));
      $p->add($form = $this->createForm());
      $form->add(new FReqItem("E-mail token:", new XTextInput('token', '')));
      $form->add(new FReqItem("Password:", new XPasswordInput('password', ''), "For security reasons, please enter the password associated with this account."));
      $form->add(new XSubmitP('verify-new-email', "Verify token"));

      $this->PAGE->addContent($p = new XPort("Reset"));
      $p->add($form = $this->createForm());
      $form->add(new XP(array(), "Did not receive the message? You can try re-sending, or canceling the process by clicking the appropriate button below."));
      $form->add($xp = new XSubmitP('resend-token', "Resend token"));
      $xp->add(new XSubmitDelete('remove-email', "Cancel e-mail change"));
      return;
    }

    $this->PAGE->addContent($p = new XPort("My information"));
    $p->add($form = $this->createForm());
    $form->add(new FReqItem("First name:", new XTextInput("first_name", $this->USER->first_name, array('maxlength'=>30))));
    $form->add(new FReqItem("Last name:",  new XTextInput("last_name",  $this->USER->last_name, array('maxlength'=>30))));
    $form->add(new FReqItem("Role:",  XSelect::fromArray('role', Account::getRoles(), $this->USER->role)));
    $form->add(new XSubmitP('edit-info', "Edit"));

    $this->PAGE->addContent($p = new XPort("Change password"));
    $p->add($form = $this->createForm());
    $form->add(new FReqItem("Current password:", new XPasswordInput('current', "")));
    $form->add(new FReqItem("New password:",     new XPasswordInput("sake1", "")));
    $form->add(new FReqItem("Confirm password:", new XPasswordInput("sake2", "")));
    $form->add(new XSubmitP('edit-password', "Change"));

    $this->PAGE->addContent($p = new XPort("Change e-mail/username"));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(),
                      array("Your e-mail, ", new XStrong($this->USER->email), ", is our primary point of contact and your username for the program. In order to change your e-mail address, we will need to send a verification message to the new address. To start, fill out the form below:")));

    $form->add(new FReqItem("New email:", new XEmailInput('new_email', '')));
    $form->add(new XSubmitP('change-email', "Send verification e-mail"));

    $this->PAGE->addContent($p = new XPort("Delete my account"));
    $p->add($form = $this->createForm());
    $form->add(new XWarning(array("Press the button below to delete your account. ", new XStrong("Account deletion is permanent and immediate."), " You will not receive any further notifications from the system and will be immediately logged out.")));
    $form->add(new FReqItem("Confirm:", new FCheckbox(self::INPUT_CONFIRM_DELETE, 1, "Yes, delete my account.")));
    $form->add(new XSubmitP(self::SUBMIT_DELETE, "Delete", array('onclick' => 'return confirm("Are you sure you wish to delete your account?");'), true));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // new e-mail
    // ------------------------------------------------------------
    if (isset($args['change-email'])) {
      $new_email = DB::$V->reqEmail($args, 'new_email', "Invalid new email provided.");
      if ($new_email == $this->USER->email)
        throw new SoterException("The new e-mail address matches the old one.");
      if (!DB::isAccountEmailAvailable($new_email))
        throw new SoterException("Invalid e-mail provided.");

      // Send new message
      // Reset previous token
      $token = uniqid();
      if (!$this->sendVerificationEmail($new_email, $token)) {
        throw new SoterException("There was an error attempting to send the e-mail. Please try again later.");
      }

      Session::s('new_email', $new_email);
      Session::s('new_token', $token);
      Session::pa(new PA(sprintf("Message sent to %s. To finish, please follow instructions below.", $new_email)));
    }

    // ------------------------------------------------------------
    // resend token
    // ------------------------------------------------------------
    if (isset($args['resend-token'])) {
      if ($this->new_email === null || $this->new_token === null)
        throw new SoterException("There is no active token to send for this user.");
      if (!$this->sendVerificationEmail($this->new_email, $this->new_token)) {
        throw new SoterException("There was an error attempting to re-send the e-mail. Please try again later.");
      }
      Session::pa(new PA(sprintf("Message re-sent to %s.", $this->new_email)));
    }

    // ------------------------------------------------------------
    // remove e-mail
    // ------------------------------------------------------------
    if (isset($args['remove-email'])) {
      if ($this->new_email === null)
        throw new SoterException("No new e-mail exists in the system for this user.");
      Session::d('new_email');
      Session::d('new_token');
      Session::pa(new PA(sprintf("Canceled verification message sent to %s.", $this->new_email)));
    }

    // ------------------------------------------------------------
    // verify new email
    // ------------------------------------------------------------
    if (isset($args['verify-new-email'])) {
      if ($this->new_email === null || $this->new_token === null)
        throw new SoterException("There is no active token for this user.");

      $account = $this->USER;

      $password = DB::$V->reqString($args, 'password', 1, 256, "No password provided.");
      $hash = DB::createPasswordHash($account, $password);
      if ($account->password !== $hash)
        throw new SoterException("Invalid password provided.");

      $token = DB::$V->reqString($args, 'token', 1, 51, "No token provided.");
      if ($token != $this->new_token)
        throw new SoterException("Invalid token entered. Please try again.");

      // Change e-mail address
      $account->email = $this->new_email;
      $account->password = DB::createPasswordHash($account, $password);
      DB::set($account);
      Session::pa(new PA(sprintf("E-mail address changed to %s.", $account->email)));
      Session::d('new_email');
      Session::d('new_token');
    }

    // ------------------------------------------------------------
    // edit info
    // ------------------------------------------------------------
    if (isset($args['edit-info'])) {
      $this->USER->first_name = DB::$V->reqString($args, 'first_name', 1, 31, "First name cannot be empty (and must be less than 30 characters.");
      $this->USER->last_name = DB::$V->reqString($args, 'last_name', 1, 31, "Last name cannot be empty (and must be less than 30 characters.");
      $this->USER->role = DB::$V->reqKey($args, 'role', Account::getRoles(), "Invalid role provided.");
      DB::set($this->USER);
      Session::pa(new PA("Information updated."));
    }

    // ------------------------------------------------------------
    // password change?
    // ------------------------------------------------------------
    if (isset($args['edit-password'])) {
      $cur = DB::$V->reqRaw($args, 'current', 1, 101, "No current password provided.");
      if ($this->USER->password != DB::createPasswordHash($this->USER, $cur))
        throw new SoterException("Invalid current password provided.");

      $pw1 = DB::$V->reqRaw($args, 'sake1', 8, 101, "The password must be at least 8 characters long.");
      $pw2 = DB::$V->reqRaw($args, 'sake2', strlen($pw1), strlen($pw1) + 1, "The two passwords do not match.");
      if ($pw1 != $pw2)
        throw new SoterException("The password confirmation does not match.");
      $this->USER->password = DB::createPasswordHash($this->USER, $pw1);
      DB::set($this->USER);
      Session::pa(new PA("Password reset."));
    }

    // ------------------------------------------------------------
    // Delete account
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_DELETE, $args)) {
      DB::$V->reqInt($args, self::INPUT_CONFIRM_DELETE, 1, 2, "Please check the confirmation box to proceed.");
      $this->USER->status = Account::STAT_INACTIVE;
      DB::set($this->USER);
      Session::warn("Your account has been removed. Good-bye.");
    }

    return array();
  }

  private function sendVerificationEmail($email, $token) {
    $verify_message = DB::g(STN::MAIL_VERIFY_EMAIL);

    $acc = $this->USER;
    $body = DB::keywordReplace($verify_message, $acc, $acc->getFirstSchool());
    $body = str_replace('{BODY}', $token, $body);
    return DB::mail(
      $email,
      sprintf("[%s] Verify new e-mail address", DB::g(STN::APP_NAME)),
      $body
    );
  }
}
?>