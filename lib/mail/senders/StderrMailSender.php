<?php
namespace mail\senders;

use \mail\EmailMessage;

/**
 * Prints e-mails to STDERR using error_log.
 *
 * @author Dayan Paez
 * @version 2026-03-28
 */
class StderrMailSender implements EmailSender {

  public function emptyInbox() {
    // do nothing
  }

  public function sendEmail(EmailMessage $email) {
    error_log("Sending email:");
    error_log("> SUBJECT=" . $email->getSubject());
    error_log("> RECIPIENT=" . json_encode($email->getRecipients()));
    foreach ($email->getAlternatives() as $alternative) {
        error_log("> ALTERNATIVE=" . $alternative->getContent());
    }
    return true;
  }

  public function getInbox() {
    return array();
  }
}
