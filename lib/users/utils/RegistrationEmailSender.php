<?php
namespace users\utils;

use \DB;
use \Email_Token;
use \STN;
use \WS;

/**
 * Sends registration e-mail for given email token.
 *
 * @author Dayan Paez
 * @version 2015-11-06
 */
class RegistrationEmailSender {

  private $emailTemplate = false;
  private $emailSubject;
  private $techsCore;

  public function __construct() {
  }

  public function setCore(DB $core) {
    $this->techsCore = get_class($core);
  }

  private function getCore() {
    if ($this->techsCore === null) {
      $this->techsCore = 'DB';
    }
    return $this->techsCore;
  }

  public function setEmailTemplate($emailTemplate) {
    $this->emailTemplate = $emailTemplate;
  }

  private function getEmailTemplate() {
    if ($this->emailTemplate === false) {
      $core = $this->getCore();
      $this->emailTemplate = $core::g(STN::MAIL_REGISTER_USER);
    }
    return $this->emailTemplate;
  }

  public function setEmailSubject($emailSubject) {
    $this->emailSubject = $emailSubject;
  }

  private function getEmailSubject() {
    if ($this->emailSubject === null) {
      $core = $this->getCore();
      $this->emailSubject = sprintf("[%s] New account request", $core::g(STN::APP_NAME));
    }
    return $this->emailSubject;
  }

  /**
   * Sends e-mail to user to verify account.
   *
   * E-mail will not be sent if no e-mail template exists.
   *
   * @param Account $account the account to notify.
   * @return true if template exists, and message sent.
   */
  public function sendRegistrationEmail(Email_Token $token) {
    $template = $this->getEmailTemplate();
    if ($template === null) {
      return false;
    }

    $core = $this->getCore();
    $acc = $token->account;
    $body = $core::keywordReplace(
      $template,
      $acc,
      $acc->getFirstSchool()
    );
    $body = str_replace(
      '{BODY}',
      sprintf('%sregister/%s', WS::alink('/'), $token),
      $body
    );

    return $core::mail(
      $acc->email,
      $this->getEmailSubject(),
      $body
    );
  }

}