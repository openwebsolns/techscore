<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

/**
 * Manages the user's own account information
 *
 * @author Dayan Paez
 * @version   2010-09-19
 */
class AccountPane extends AbstractUserPane {

  public function __construct(Account $user) {
    parent::__construct("My Account", $user);
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Reset session
    // ------------------------------------------------------------
    if (array_key_exists('reset', $args)) {
      Session::d('usurped_user');
      $this->redirect();
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

    if (DB::g(STN::MAIL_VERIFY_EMAIL) !== null) {
      $this->PAGE->addContent($p = new XPort("Change e-mail/username"));
      $p->add($form = $this->createForm());
      $form->add(new XP(array(),
                        array("Your e-mail, ", new XStrong($this->USER->email), ", is our primary point of contact and your username for the program. In order to change your e-mail address, we will need to send a verification message to the new address.")));

      $message = null;
      if ($this->USER->new_email) {
        if ($this->USER->isTokenActive($this->USER->new_email)) {
          $form->add(new XP(array('class'=>'warning'), sprintf("An active validation token has been sent. Please follow the link sent to %s.", $this->USER->new_email)));
        }
        $token = $this->USER->getToken($this->USER->new_email);
        $message = "We're currently validating this e-mail address. If you change the value, this e-mail address (and all validation e-mails) will be automatically expired.";
        $submitp = new XSubmitP('change-email', "Resend verification e-mail");
        $submitp->add(new XSubmitDelete('remove-email', "Cancel verification"));
      }
      else {
        $submitp = new XSubmitP('change-email', "Send verification e-mail");
      }
      $form->add(new FReqItem("New email:", new XEmailInput('new_email', $this->USER->new_email)), $message);
      $form->add($submitp);
    }
  }

  public function process(Array $args) {
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
    // new e-mail
    // ------------------------------------------------------------
    if (isset($args['change-email'])) {
      $new_email = DB::$V->reqEmail($args, 'new_email', "Invalid new email provided.");
      if ($new_email == $this->USER->email)
        throw new SoterException("The new e-mail address matches the old one.");
      if ($new_email != $this->USER->new_email && !DB::isAccountEmailAvailable($new_email))
        throw new SoterException("Invalid e-mail provided.");

      // Reset previous token
      if ($this->USER->new_email !== null && $this->USER->isTokenActive($this->USER->new_email)) {
        $this->USER->resetToken($this->USER->new_email);
        Session::pa(new PA(sprintf("Invalidated previous e-mail message for %s.", $this->USER->new_email), PA::I));
      }

      // Send new message
      $this->USER->new_email = $new_email;
      $token = $this->USER->createToken($new_email);
      if (!$this->sendVerificationEmail($token)) {
        DB::remove($token);
        throw new SoterException("There was an error attempting to send the e-mail. Please try again later.");
      }

      DB::set($this->USER);
      Session::pa(new PA(sprintf("To finish, please follow instructions sent to %s.", $this->USER->new_email)));
    }

    // ------------------------------------------------------------
    // remove e-mail
    // ------------------------------------------------------------
    if (isset($args['remove-email'])) {
      if ($this->USER->new_email === null)
        throw new SoterException("No new e-mail exists in the system for this user.");
      if (!$this->USER->isTokenActive($this->USER->new_email))
        throw new SoterException(sprintf("No active validation token exists for %s.", $this->USER->new_email));
      $this->USER->resetToken($this->USER->new_email);
      Session::pa(new PA(sprintf("Canceled verification message sent to %s.", $this->USER->new_email)));
      $this->USER->new_email = null;
      DB::set($this->USER);
    }
    return array();
  }

  private function sendVerificationEmail(Email_Token $token) {
    $verify_message = DB::g(STN::MAIL_VERIFY_EMAIL);
    if ($verify_message === null)
      return false;

    $acc = $token->account;
    $body = DB::keywordReplace($verify_message, $acc, $acc->getFirstSchool());
    $body = str_replace('{BODY}', sprintf('%sverify-email/%s', WS::alink('/'), $token), $body);
    return DB::mail(
      $token->email,
      sprintf("[%s] Verify new e-mail address", DB::g(STN::APP_NAME)),
      $body
    );
  }
}
?>