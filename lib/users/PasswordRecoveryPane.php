<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('xml/WelcomePage.php');

/**
 * Allows a user to reset their password. The process requires sending
 * an e-mail message to the user which, when followed, allows a user
 * to reset their password.
 *
 * @author Dayan Paez
 * @version 2011-04-04
 */
class PasswordRecoveryPane extends WelcomePage {

  public function fillContent() {
    // ------------------------------------------------------------
    // 3. Request to reset password
    // ------------------------------------------------------------
    if (isset($_GET['acc'])) {
      $acc = AccountManager::getAccountFromHash(trim($_GET['acc']));
      if ($acc === null) {
	Session::pa(new PA("Invalid account to reset.", PA::E));
	return $args;
      }
      $this->addContent(new XPageTitle("Recover Password"));
      $this->addContent($p = new XPort("Reset password"));
      $p->add($f = new XForm("/password-recover-edit", XForm::POST));
      $f->add(new XP(array(), "Welcome $acc. Please enter the new password for your account."));
      $f->add(new FItem("New Password:", new XPasswordInput('new-password', "")));
      $f->add(new FItem("Confirm Password:", new XPasswordInput('confirm-password', "")));
      $f->add(new XHiddenInput('acc', trim($_GET['acc'])));
      $f->add(new XSubmitInput('reset-password', "Reset password"));
      return;
    }

    // ------------------------------------------------------------
    // 2. Message sent
    // ------------------------------------------------------------
    $POST = Session::g('POST');
    if (isset($POST['password-recovery-sent'])) {
      Session::s('POST', array());
      $this->addContent(new XPageTitle("Recover Password"));
      $this->addContent($p = new XPort("Message sent"));
      $p->add(new XP(array(), "Message sent. Please check your e-mail and follow the directions provided."));
      return;
    }

    // ------------------------------------------------------------
    // 3. Default: request message
    // ------------------------------------------------------------
    $this->addContent(new XPageTitle("Recover Password"));
    $this->addContent($p = new XPort("Send e-mail"));
    $p->add(new XP(array(), "To reset the password, please enter your username below. You will receive an e-mail at the address provided with a link. Click that link to reset your password."));
    
    $p->add($f = new XForm("/password-recover-edit", XForm::POST));
    $f->add(new FItem("Email:", new XTextInput("email", "")));
    $f->add(new XSubmitInput("send-message", "Send message"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // 3. Reset password
    // ------------------------------------------------------------
    if (isset($args['reset-password'])) {
      if (!isset($args['acc']) ||
	  ($acc = AccountManager::getAccountFromHash(trim($args['acc']))) === null) {
	Session::pa(new PA("Invalid hash provided.", PA::E));
	return $args;
      }
      if (!isset($args['new-password']) || !isset($args['confirm-password']) ||
	  $args['new-password'] != $args['confirm-password'] ||
	  strlen(trim($args['new-password'])) < 8) {
	Session::pa(new PA("Invalid or missing password. Make sure the passwords match and that it is at least 8 characters long.", PA::E));
	return $args;
      }
      $acc->password = sha1(trim($args['new-password']));
      $res = DB::mail($acc->id, '[TechScore] Account password reset', $this->getSuccessMessage($acc));
      if ($res !== false) {
	AccountManager::setAccount($acc);
	Session::pa(new PA("Account password successfully reset."));
	WebServer::go('/');
      }
      else
	Session::pa(new PA("Unable to reset password. Please try again later.", PA::E));
      return $args;
    }

    // ------------------------------------------------------------
    // 1. Send message
    // ------------------------------------------------------------
    if (isset($args['send-message'])) {
      if (!isset($args['email']) || ($acc = AccountManager::getAccount(trim($args['email']))) === null) {
	Session::pa(new PA("Invalid e-mail provided.", PA::E));
	return false;
      }
      $res = DB::mail($acc->id, '[TechScore] Reset password request', $this->getMessage($acc));
      if ($res) {
	Session::pa(new PA("Message sent."));
	Session::s('POST', array('password-recovery-sent'=>true));
      }
      else
	Session::pa(new PA("Unable to send message. Please try again.", PA::I));
      return true;
    }
  }

  public function getMessage(Account $to) {
    return sprintf("Dear %s,\n\nYou are receiving this message because you, or someone in your name, has requested to reset the password for this account. If you did not request to reset your password, kindly disregard this message.\n\nTo enter a new password, please follow the link below. You may need to copy and paste the link into your browser's location bar.\n\n%s/password-recover?acc=%s\n\nThank you,\n\nTechScore Administration",
		   $to->first_name, Conf::$HOME, AccountManager::getHash($to));
  }

  public function getSuccessMessage(Account $to) {
    return sprintf("Dear %s,\n\nWe are sending this message to let you know that your account password has been successfully reset. Please log-in now using the password you chose.\n\nThank you,\n\nTechScore Administration",
		   $to->first_name);
  }
}
?>
