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
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid account to reset.", Announcement::ERROR);
	return $args;
      }
      $this->addContent(new PageTitle("Recover Password"));
      $this->addContent($p = new Port("Reset password"));
      $p->add($f = new XForm("/password-recover-edit"));
      $f->add(new Para("Welcome $acc. Please enter the new password for your account."));
      $f->add(new FItem("New Password:", new FPassword('new-password', "")));
      $f->add(new FItem("Confirm Password:", new FPassword('confirm-password', "")));
      $f->add(new FHidden('acc', trim($_GET['acc'])));
      $f->add(new FSubmit('reset-password', "Reset password"));
      return;
    }

    // ------------------------------------------------------------
    // 2. Message sent
    // ------------------------------------------------------------
    if (isset($_SESSION['password-recovery-sent'])) {
      unset($_SESSION['password-recovery-sent']);
      $this->addContent(new PageTitle("Recover Password"));
      $this->addContent($p = new Port("Message sent"));
      $p->add(new Para("Message sent. Please check your e-mail and follow the directions provided."));
      return;
    }

    // ------------------------------------------------------------
    // 3. Default: request message
    // ------------------------------------------------------------
    $this->addContent(new PageTitle("Recover Password"));
    $this->addContent($p = new Port("Send e-mail"));
    $p->add(new Para("To reset the password, please enter your username below. You will receive an e-mail at the address provided with a link. Click that link to reset your password."));
    
    $p->add($f = new XForm("/password-recover-edit"));
    $f->add(new FItem("Email:", new FText("email", "")));
    $f->add(new FSubmit("send-message", "Send message"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // 3. Reset password
    // ------------------------------------------------------------
    if (isset($args['reset-password'])) {
      if (!isset($args['acc']) ||
	  ($acc = AccountManager::getAccountFromHash(trim($args['acc']))) === null) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid hash provided.", Announcement::ERROR);
	return $args;
      }
      if (!isset($args['new-password']) || !isset($args['confirm-password']) ||
	  $args['new-password'] != $args['confirm-password'] ||
	  strlen(trim($args['new-password'])) < 8) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid or missing password. Make sure the passwords match and that it is at least 8 characters long.", Announcement::ERROR);
	return $args;
      }
      $acc->password = sha1(trim($args['new-password']));
      $res = Preferences::mail($acc->id, '[TechScore] Account password reset', $this->getSuccessMessage($acc));
      if ($res !== false) {
	AccountManager::setAccount($acc);
	$_SESSION['ANNOUNCE'][] = new Announcement("Account password successfully reset.");
	WebServer::go('/');
      }
      else
	$_SESSION['ANNOUNCE'][] = new Announcement("Unable to reset password. Please try again later.", Announcement::ERROR);
      return $args;
    }

    // ------------------------------------------------------------
    // 1. Send message
    // ------------------------------------------------------------
    if (isset($args['send-message'])) {
      if (!isset($args['email']) || ($acc = AccountManager::getAccount(trim($args['email']))) === null) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid e-mail provided.", Announcement::ERROR);
	return false;
      }
      $res = Preferences::mail($acc->id, '[TechScore] Reset password request', $this->getMessage($acc));
      if ($res) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Message sent.");
	$_SESSION['password-recovery-sent'] = true;
      }
      else
	$_SESSION['ANNOUNCE'][] = new Announcement("Unable to send message. Please try again.", Announcement::WARNING);
      return true;
    }
  }

  public function getMessage(Account $to) {
    return sprintf("Dear %s,\n\nYou are receiving this message because you, or someone in your name, has requested to reset the password for this account. If you did not request to reset your password, kindly disregard this message.\n\nTo enter a new password, please follow the link below. You may need to copy and paste the link into your browser's location bar.\n\n%s/password-recover?acc=%s\n\nThank you,\n\nTechScore Administration",
		   $to->first_name, HOME, AccountManager::getHash($to));
  }

  public function getSuccessMessage(Account $to) {
    return sprintf("Dear %s,\n\nWe are sending this message to let you know that your account password has been successfully reset. Please log-in now using the password you chose.\n\nThank you,\n\nTechScore Administration",
		   $to->first_name);
  }
}
?>
