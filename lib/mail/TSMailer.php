<?php
namespace mail;

use \mail\senders\EmailSender;
use \Conf;
use \DB;
use \Metric;
use \STN;

/**
 * Simpler PHP mailer functionality
 *
 * @author Dayan Paez
 * @created 2014-10-19
 */
class TSMailer {

  const METRIC_NO_EMAIL_SENDER_SPECIFIED = 'TSMailer_no_email_sender_specified';
  const METRIC_SEND_SUCCESS = 'TSMailer_send_success';
  const METRIC_SEND_FAILURE = 'TSMailer_send_failure';

  /**
   * @var EmailSender Method by which mail can be sent.
   */
  private static $SENDER;

  public static function setEmailSender(EmailSender $sender) {
    self::$SENDER = $sender;
  }

  /**
   * Sends a multipart (MIME) mail message to the given user with the
   * given subject, appending the correct headers (i.e., the "from"
   * field).
   *
   * @param String|Array $to the e-mail address(es) to send to
   * @param String $subject the subject
   * @param Array $parts the different MIME parts, indexed by MIME type.
   * @param Array $extra_headers optional map of extra headers to send
   * @param Array:Attachment $attachments optional list of attachments
   * @return boolean the result, as returned by mail
   */
  public static function sendMultipart($to, $subject, Array $parts, Array $extra_headers = array(), Array $attachments = array()) {
    
    if (DB::g(STN::DIVERT_MAIL) !== null) {
      $originalRecipients = $to;
      if (!is_array($originalRecipients)) {
        $originalRecipients = array($originalRecipients);
      }
      $to = DB::g(STN::DIVERT_MAIL);
      $subject = 'DIVERTED: ' . $subject;
      foreach ($parts as $mime => $part) {
        if (Alternative::parseMimeType($mime) == Alternative::TEXT_PLAIN) {
          $parts[$mime] = "Intended recipients: " . implode(", ", $originalRecipients) . "\n--------------\n" . $part;
        }
      }
    }

    $message = new EmailMessage($subject);

    $extra_headers['From'] = DB::g(STN::TS_FROM_MAIL);
    $message->setHeaders($extra_headers);

    foreach ($parts as $mime => $part) {
      $message->addAlternative(new Alternative($part, $mime));
    }

    foreach ($attachments as $file) {
      $message->addAttachment($file);
    }

    if (!is_array($to)) {
      $to = array($to);
    }
    $res = true;
    foreach ($to as $recipient) {
      $newMessage = clone($message);
      $newMessage->setRecipients(array($recipient));
      $res = $res && self::send($newMessage);
    }
    return $res;
  }

  /**
   * Actually dispatches the message using internal strategy.
   *
   * @param String $recipient the e-mail address.
   * @param String $subject the subject.
   * @param String $body the body of the message.
   * @param String $headers to use.
   * @return boolean the result of sending the message.
   */
  private static function send(EmailMessage $email) {

    if (self::$SENDER == null) {
      $classname = Conf::$EMAIL_SENDER;
      if ($classname == null) {
        Metric::publish(self::METRIC_NO_EMAIL_SENDER_SPECIFIED);
        return false;
      }

      self::setEmailSender(new $classname(Conf::$EMAIL_SENDER_PARAMS));
    }

    if (!self::$SENDER->sendEmail($email)) {
      Metric::publish(self::METRIC_SEND_FAILURE);
      return false;
    }
    Metric::publish(self::METRIC_SEND_SUCCESS);
    return true;
  }
}
?>