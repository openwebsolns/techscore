<?php
namespace mail\senders;

use \mail\EmailMessage;

/**
 * Sends e-mails using PHP's built-in mail function.
 *
 * @author Dayan Paez
 * @version 2015-09-13
 */
class PhpMailSender implements EmailSender {

  public function sendEmail(EmailMessage $email) {
    $translated = new PhpMailSenderEmailTranslator($email);
    return @mail(
      implode(', ', $email->getRecipients()),
      $email->getSubject(),
      $translated->getBody(),
      $translated->getHeaders()
    );
  }

}