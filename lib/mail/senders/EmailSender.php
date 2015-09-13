<?php
namespace mail\senders;

use \mail\EmailMessage;

/**
 * Sends an e-mail message.
 *
 * @author Dayan Paez
 * @created 2015-09-13
 */
interface EmailSender {

  /**
   * Sends a message.
   *
   * @param EmailMessage the e-mail object to dispatch.
   * @return boolean the result of sending the message.
   */
  public function sendEmail(EmailMessage $email);
}