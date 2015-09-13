<?php
namespace mail;

/**
 * Encapsulates an e-mail message.
 *
 * @author Dayan Paez
 * @created 2014-10-19
 */
class EmailMessage {

  /**
   * @var Array:Attachment list of attachments
   */
  private $attachments;

  /**
   * @var String optional 'from' field
   */
  private $from;

  /**
   * @var String the subject of the message.
   */
  private $subject;

  /**
   * @var Array alternatives to the message
   */
  private $alternatives;

  /**
   * @var Map headers for the message
   */
  private $headers;

  /**
   * @var Array the list of direct recipients.
   */
  private $recipients;

  /**
   * Creates a new e-mail message
   *
   * @param String $subject the subject of the message
   * @param String $message the body of the message
   * @param String $mime_type the MIME type of the message given
   * @param Array $headers optional map of headers
   * @param Array:Attachment optional list of attachments
   */
  public function __construct($subject = '', $message = '', $mime_type = Alternative::TEXT_PLAIN, Array $headers = array(), Array $attachments = array()) {

    $this->setSubject($subject);
    $this->setMessage($message, $mime_type);
    $this->setHeaders($headers);

    $this->attachments = array();
    foreach ($attachments as $attachment)
      $this->addAttachment($attachment);

    $this->recipients = array();
  }

  public function setHeader($key, $value) {
    $this->headers[$key] = $value;
    if (strcasecmp($key, 'from') == 0) {
      $this->from = $value;
    }
  }

  public function setHeaders(Array $headers = array()) {
    $this->headers = array();
    foreach ($headers as $key => $value) {
      $this->setHeader($key, $value);
    }
  }

  public function getHeaders() {
    return $this->headers;
  }

  public function getHeader($key) {
    if (array_key_exists($key, $this->headers)) {
      return $this->headers[$key];
    }
    return null;
  }

  public function addRecipient($address) {
    $this->recipients[] = $address;
  }

  public function setRecipients(Array $addresses) {
    $this->recipients = array_values($addresses);
  }

  public function getRecipients() {
    return $this->recipients;
  }

  public function getFrom() {
    return $this->from;
  }

  public function setSubject($subject) {
    $this->subject = (string) $subject;
  }

  public function getSubject() {
    return $this->subject;
  }

  /**
   * Sets the given message as the content for message
   *
   * @param String $message the message
   * @param String $mime_type the MIME type for message
   * @param String $charset the charset
   */
  public function setMessage($message, $mime_type = Alternative::TEXT_PLAIN, $charset = Alternative::UTF8) {
    $this->alternatives = array();
    $this->addAlternative(new Alternative($message, $mime_type, $charset));
  }

  /**
   * Adds an alternative representation of the message
   *
   * @param Alternative $alternative alternative to add.
   */
  public function addAlternative(Alternative $alternative) {
    $this->alternatives[] = $alternative;
  }

  public function getAlternatives() {
    return $this->alternatives;
  }

  public function getMimeTypes() {
    $types = array();
    foreach ($this->alternatives as $alternative) {
      $types[] = $alternative->getMimeTypes();
    }
    return $types;
  }

  /**
   * Adds an attachment to the message
   *
   * @param Attachment $attachment
   * @return int the number of attachments
   */
  public function addAttachment(Attachment $attachment) {
    $this->attachments[] = $attachment;
    return count($this->attachments);
  }

  public function getAttachments() {
    return $this->attachments;
  }
}
