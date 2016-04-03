<?php
namespace mail\senders;

use \mail\EmailMessage;

/**
 * Store e-mails in a local space.
 *
 * @author Dayan Paez
 * @version 2016-04-03
 */
class InMemoryMailSender implements EmailSender {

  private $inbox;

  public function __construct() {
    $this->emptyInbox();
  }

  public function emptyInbox() {
    $this->inbox = array();
  }

  public function sendEmail(EmailMessage $email) {
    $this->inbox[] = $email;
    return true;
  }

  public function getInbox() {
    return $this->inbox;
  }
}