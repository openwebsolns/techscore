<?php
namespace users\utils;

use \Account;
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

  const MODE_USER = 'user';
  const MODE_SAILOR = 'sailor';

  private $emailTemplate = false;
  private $emailSubject;
  private $techsCore;
  private $mode;

  public function __construct($mode = self::MODE_USER) {
    $this->mode = $mode;
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
      if ($this->mode == self::MODE_SAILOR) {
        $this->emailTemplate = $core::g(STN::MAIL_REGISTER_STUDENT);
      }
      if (!$this->emailTemplate) {
        $this->emailTemplate = $core::g(STN::MAIL_REGISTER_USER);
      }
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
  public function sendRegistrationEmail(Account $account, $link) {
    $template = $this->getEmailTemplate();
    if ($template === null) {
      return false;
    }

    $core = $this->getCore();
    $body = $core::keywordReplace(
      $template,
      $account,
      $account->getFirstSchool()
    );
    $body = str_replace(
      '{BODY}',
      WS::alink($link),
      $body
    );

    return $core::mail(
      $account->email,
      $this->getEmailSubject(),
      $body
    );
  }

}