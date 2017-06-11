<?php
namespace mail\senders;

use \mail\EmailMessage;

/**
 * Extends parent to include "To" header. 
 *
 * @author Dayan Paez
 * @version 2017-06-10
 */
class SesMailSenderEmailTranslator extends PhpMailSenderEmailTranslator {

  public function __construct(EmailMessage $email) {
    parent::__construct($email);
  }

  public function getHeaders() {
    $headers = '';
    foreach ($this->headers as $key => $val) {
      $headers .= sprintf("%s: %s\r\n", $key, $val);
    }
    // add-in the SUBJECT headers after FROM
    $headers .= sprintf("Subject: %s\r\n", $this->email->getSubject());
    return $headers;
  }
}