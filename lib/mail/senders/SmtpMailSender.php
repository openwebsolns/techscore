<?php
namespace mail\senders;

use \PHPMailer;
use \DB;
use \mail\Alternative;
use \mail\Attachment;
use \mail\EmailMessage;

/**
 * Use SMTP settings to send mail.
 *
 * @author Dayan Paez
 * @version 2015-10-13
 */
class SmtpMailSender implements EmailSender {

  const SERVER_HOSTNAME = 'server_hostname';
  const SENDER_HOSTNAME = 'sender_hostname';
  const SERVER_PORT = 'server_port';
  const USERNAME = 'username';
  const PASSWORD = 'password';

  const TEXT_HTML = 'text/html';

  private $params = array(
    self::SERVER_HOSTNAME => null,
    self::SENDER_HOSTNAME => null,
    self::SERVER_PORT => 25,
    self::USERNAME => null,
    self::PASSWORD => null
  );

  /**
   * Creates a new mailer with the given parameters (mandatory).
   *
   * @param Array $params map of params indexed by class constant.
   */
  public function __construct(Array $params) {
    $this->params[self::SERVER_HOSTNAME] = DB::$V->reqString($params, self::SERVER_HOSTNAME, 1, 1000, "No server hostname provided.");
    $this->params[self::SENDER_HOSTNAME] = DB::$V->reqString($params, self::SENDER_HOSTNAME, 1, 1000, "No sender hostname provided.");
    $this->params[self::SERVER_PORT] = DB::$V->incInt($params, self::SERVER_PORT, 1, 65000, $this->params[self::SERVER_PORT]);
    $this->params[self::USERNAME] = DB::$V->reqString($params, self::USERNAME, 1, 1000, "No username provided.");
    $this->params[self::PASSWORD] = DB::$V->reqString($params, self::PASSWORD, 1, 1000, "No password provided.");
  }

  /**
   * @Override Sends mail by delegating to PHPMailer.
   */
  public function sendEmail(EmailMessage $email) {
    $phpMailer = $this->createPhpMailer();
    $this->fillPhpMailer($phpMailer, $email);
    return $phpMailer->send();
  }

  private function createPhpMailer() {
    require_once('mail/senders/PHPMailer/PHPMailerAutoload.php');
    $phpMailer = new PHPMailer();
    $phpMailer->IsSMTP(true);
    $phpMailer->Host = $this->params[self::SERVER_HOSTNAME];
    $phpMailer->Helo = $this->params[self::SENDER_HOSTNAME];
    $phpMailer->Hostname = $this->params[self::SENDER_HOSTNAME];
    $phpMailer->SMTPAuth = true;
    $phpMailer->Username = $this->params[self::USERNAME];
    $phpMailer->Password = $this->params[self::PASSWORD];
    $phpMailer->SMTPSecure = 'tls';
    $phpMailer->Port = $this->params[self::SERVER_PORT];
    $phpMailer->CharSet = 'UTF-8';
    $phpMailer->Timeout = 30;
    $phpMailer->SMTPDebug = 0;
    return $phpMailer;
  }

  private function fillPhpMailer(PHPMailer $phpMailer, EmailMessage $email) {
    $phpMailer->Subject = $email->getSubject();

    $fromParts = $this->getEmailFromParts($email);
    $phpMailer->setFrom($fromParts[0], $fromParts[1]);

    foreach ($email->getRecipients() as $recipient) {
      $phpMailer->addAddress($recipient);
    }

    // Alternatives: phpMailer supports HTML as primary and plain text
    // as "alternate".
    $htmlAlternative = null;
    $plainAlternative = null;
    foreach ($email->getAlternatives() as $alternative) {
      $type = $alternative->getMimeType();
      if (strpos($type, self::TEXT_HTML) === 0) {
        $htmlAlternative = $alternative->getContent();
      }
      elseif (strpos($type, Alternative::TEXT_PLAIN) === 0) {
        $plainAlternative = $alternative->getContent();
      }
    }
    if ($htmlAlternative == null) {
      $phpMailer->IsHtml(false);
      $phpMailer->Body = $plainAlternative;
    }
    else {
      $phpMailer->isHtml(true);
      $phpMailer->Body = $htmlAlternative;
      $phpMailer->AltBody = $plainAlternative;
    }

    // Attachments
    foreach ($email->getAttachments() as $attachment) {
      $filepath = $attachment->getFilePath();
      if ($filepath != null) {
        $phpMailer->addAttachment($filepath);
      }
    }
  }

  /**
   * Breaks a from field of the form: "Name <email>" into a list of
   * two parts: "Name", and "email".
   *
   * @param Email $email the e-mail whose From field to parse.
   * @return Array the parts as "email", "Alias".
   */
  private function getEmailFromParts(EmailMessage $email) {
    $from = $email->getFrom();
    $matches = array();
    if (preg_match('/^([^<]+)\s+<([^>]+)>$/', $from, $matches) == 1) {
      return array($matches[2], $matches[1]);
    }
    return array($from, null);
  }
}