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

  public function sendEmail(EmailMessage $email) {
    $inbox = Session::g(self::KEY_INBOX, array());
    $inbox[] = $email;
    Session::s(self::KEY_INBOX, $inbox);
    return true;
  }
}