<?php
use \users\AbstractUserPane;

/**
 * Allows a user to reset their password. The process requires sending
 * an e-mail message to the user which, when followed, allows a user
 * to reset their password.
 *
 * @author Dayan Paez
 * @version 2011-04-04
 */
class PasswordRecoveryPane extends AbstractUserPane {

  public function __construct() {
    parent::__construct("Recover Password");
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // 3. Request to reset password
    // ------------------------------------------------------------
    if (DB::$V->hasString($hash, $args, 'acc', 1, 65)) {
      $acc = DB::getAccountFromToken($hash);
      if ($acc === null) {
        Session::pa(new PA("Invalid account to reset.", PA::E));
      }
      elseif (!$acc->isTokenActive()) {
        Session::pa(new PA("Token provided has expired. Please try again.", PA::E));
      }
      else {
        $this->PAGE->addContent($p = new XPort("Reset password"));
        $p->add($f = $this->createForm());
        $f->add(new XP(array(), "Welcome $acc. Please enter the new password for your account."));
        $f->add(new FReqItem("New Password:", new XPasswordInput('new-password', "")));
        $f->add(new FReqItem("Confirm Password:", new XPasswordInput('confirm-password', "")));
        $f->add(new XHiddenInput('acc', trim($_GET['acc'])));
        $f->add(new XSubmitP('reset-password', "Reset password"));
        return;
      }
    }

    // ------------------------------------------------------------
    // 2. Message sent
    // ------------------------------------------------------------
    $POST = Session::g('POST');
    if (isset($POST['password-recovery-sent'])) {
      Session::s('POST', array());
      $this->PAGE->addContent($p = new XPort("Message sent"));
      $p->add(new XP(array(), "Message sent. Please check your e-mail and follow the directions provided."));
      return;
    }

    // ------------------------------------------------------------
    // 1. Default: request message
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Send e-mail"));
    $p->add(new XP(array(), "To reset the password, please enter your username below. You will receive an e-mail at the address provided with a link. Click that link to reset your password."));

    $p->add($f = $this->createForm());
    $f->add(new FReqItem("Email:", new XEmailInput('email', "")));
    $f->add(new XSubmitP("send-message", "Send message"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // 3. Reset password
    // ------------------------------------------------------------
    if (isset($args['reset-password'])) {
      if (($acc = DB::getAccountFromToken(DB::$V->reqString($args, 'acc', 1, 65, "Missing hash."))) === null)
        throw new SoterException("Invalid hash provided.");
      if (!$acc->isTokenActive())
        throw new SoterException("Token provided has expired.");

      $pw1 = DB::$V->reqRaw($args, 'new-password', 8, 101, "Password must be at least 8 characters long..");
      $pw2 = DB::$V->reqRaw($args, 'confirm-password', 8, 101, "Invalid confirmation.");
      if ($pw1 !== $pw2)
        throw new SoterException("Password mismatch. Make sure the passwords match and that it is at least 8 characters long.");
      $acc->password = DB::createPasswordHash($acc, $pw1);
      $acc->resetToken();
      if (!DB::mailAccount($acc, sprintf('[%s] Account password reset', DB::g(STN::APP_NAME)), $this->getSuccessMessage($acc)))
        Session::pa(new PA("No e-mail message could be sent, but password has been reset. Please log in with your new password now.", PA::I));
      else
        Session::pa(new PA("Account password successfully reset."));
      DB::set($acc);
      $this->redirect('');
    }

    // ------------------------------------------------------------
    // 1. Send message
    // ------------------------------------------------------------
    if (isset($args['send-message'])) {
      if (($acc = DB::getAccountByEmail(DB::$V->reqString($args, 'email', 1, 41, "No e-mail provided."))) === null)
        throw new SoterException("Invalid e-mail provided.");
      $token = $acc->createToken();
      if (!DB::mailAccount($acc, sprintf('[%s] Reset password request', DB::g(STN::APP_NAME)), $this->getMessage($acc, $token)))
        throw new SoterException("Unable to send message. Please try again later.");
      DB::set($acc);
      Session::pa(new PA("Message sent."));
      return array('password-recovery-sent'=>true);
    }
  }

  private function getMessage(Account $to, Email_Token $token) {
    return sprintf("Dear %s,\n\nYou are receiving this message because you, or someone in your name, has requested to reset the password for this account. If you did not request to reset your password, kindly disregard this message.\n\nTo enter a new password, please follow the link below. You may need to copy and paste the link into your browser's location bar.\n\n%s?acc=%s\n\nThank you,\n\nTechScore Administration",
                   $to->first_name, WS::alink('/password-recover'), $token);
  }

  private function getSuccessMessage(Account $to) {
    return sprintf("Dear %s,\n\nWe are sending this message to let you know that your account password has been successfully reset. Please log-in now using the password you chose.\n\nThank you,\n\nTechScore Administration",
                   $to->first_name);
  }
}
?>
