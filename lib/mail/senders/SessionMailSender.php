<?php
namespace mail\senders;

use \mail\EmailMessage;
use \Session;

/**
 * Stores e-mails in Session.
 *
 * @author Dayan Paez
 * @version 2016-04-03
 */
class SessionMailSender implements EmailSender {
  const KEY_INBOX = 'SessionMailSender/Inbox';

  private $lastRequestTimeFloat = null;

  public function sendEmail(EmailMessage $email) {
    // You want to know what a hack is? This that appears below is a
    // hack. If e-mails are tracked in the session, then the inbox is
    // never emptied. This is uncool. So we create a hack whereby the
    // inbox is emptied with every request. We determine that this is
    // a new request by the REQUEST_TIME_FLOAT entry in the global
    // $_SERVER variable (told you it was ugly).
    $thisRequestTimeFloat = null;
    if (array_key_exists('REQUEST_TIME_FLOAT', $_SERVER)) {
      $thisRequestTimeFloat = $_SERVER['REQUEST_TIME_FLOAT'];
    }

    $inbox = Session::g(self::KEY_INBOX, array());
    if ($thisRequestTimeFloat != $this->lastRequestTimeFloat) {
      $inbox = array();
      $this->lastRequestTimeFloat = $thisRequestTimeFloat;
    }
    $inbox[] = $email;
    Session::s(self::KEY_INBOX, $inbox);
    return true;
  }

  public static function empty() {
    Session::d(self::KEY_INBOX);
  }
}