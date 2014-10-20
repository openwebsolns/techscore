<?php
/*
 * This file is part of TechScore
 */

require_once('Attachment.php');
require_once('Email.php');

/**
 * Creates an e-mail message
 *
 * @author Dayan Paez
 * @created 2014-10-19
 */
class EmailCreator {

  const TEXT_PLAIN = 'text/plain';
  const MIME_MIXED = 'multipart/mixed';
  const MIME_ALTERNATIVE = 'multipart/alternative';

  /**
   * @var Array:Attachment list of attachments
   */
  private $attachments;

  /**
   * @var String optional 'from' field
   */
  private $from;

  /**
   * @var Array alternatives to the message
   */
  private $alternatives;

  /**
   * @var Array corresponding set of mime_types
   */
  private $mime_types;

  /**
   * @var Array corresponding charsets
   */
  private $charsets;

  /**
   * @var Map headers for the message
   */
  private $headers;

  /**
   * Creates a new e-mail message
   *
   * @param String $message the body of the message
   * @param String $mime_type the MIME type of the message given
   * @param Array $headers optional map of headers
   * @param Array:Attachment optional list of attachments
   */
  public function __construct($message = '', $mime_type = self::TEXT_PLAIN, Array $headers = array(), Array $attachments = array()) {

    $this->setMessage($message, $mime_type);
    $this->setHeaders($headers);

    $this->attachments = array();
    foreach ($attachments as $attachment)
      $this->addAttachment($attachment);
  }

  public function setHeader($key, $value) {
    $this->headers[$key] = $value;
  }

  public function setHeaders(Array $headers = array()) {
    $this->headers = $headers;
  }

  /**
   * Sets the given message as the content for message
   *
   * @param String $message the message
   * @param String $mime_type the MIME type for message
   * @param String $charset the charset
   */
  public function setMessage($message, $mime_type = self::TEXT_PLAIN, $charset = 'UTF-8') {
    $this->alternatives = array((string)$message);
    $this->mime_types = array((string)$mime_type);
    $this->charsets = array((string)$charset);
  }

  /**
   * Adds an alternative representation of the message
   *
   * @param String $message the message
   * @param String $mime_type the MIME type for message
   * @param String $charset the charset
   */
  public function addAlternative($message, $mime_type = self::TEXT_PLAIN, $charset = 'UTF-8') {
    $this->alternatives[] = (string)$message;
    $this->mime_types[] = (string)$mime_type;
    $this->charsets[] = (string)$charset;
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

  /**
   * Create a string not found in any part of message
   *
   * @return String
   */
  protected function createBoundary() {
    // Need not only check the plain text alternatives because the
    // others (and the attachments) will be base64 encoded.
    $text_plain = 'text/plain';
    $length = strlen($text_plain);

    $found = true;
    while ($found) {
      $bdry = uniqid(rand(100, 999), true);
      $found = false;
      foreach ($this->alternatives as $i => $segment) {
        if ($this->mime_types[$i] == self::TEXT_PLAIN && strstr($segment, $bdry) !== false) {
          $found = true;
          break;
        }
      }
    }
    return $bdry;
  }

  protected function getMessageContentType() {
    if (count($this->attachments) > 0)
      return self::MIME_MIXED;
    if (count($this->alternatives) > 0)
      return self::MIME_ALTERNATIVE;
    return $this->mime_types[0];
  }

  protected function fillBodyWithAlternatives(Email $email, $boundary = null) {
    // Alternatives
    foreach ($this->alternatives as $i => $alternative) {
      if ($boundary) {
        $email->add('--' . $boundary . "\n");

        $type = $this->mime_types[$i];
        if (!empty($this->charsets[$i]))
          $type .= '; charset=' . $this->charsets[$i];

        $email->add('Content-Type: ' . $type . "\n");
        if ($this->mime_types[$i] != self::TEXT_PLAIN) {
          $email->add("Content-Transfer-Encoding: base64\n");
          $alternative = base64_encode($alternative);
        }
        $email->add("\n");
      }

      $email->add($alternative);
      $email->add("\n");
    }
    if ($boundary)
      $email->add('--' . $boundary . '--' . "\n");
  }

  protected function fillBodyWithAttachments(Email $email, $boundary = null) {
    foreach ($this->attachments as $f) {
      $email->add('--' . $boundary . "\n");
      $email->add('Content-Type: ' . $f->getMIME() . '; name="' . $f->getName() . '"' . "\n");
      $email->add('Content-Disposition: attachment; filename="' . $f->getName() . '"' . "\n");
      $email->add('Content-Transfer-Encoding: base64' . "\n");
      $email->add("\n");
      $email->add($f->getBase64EncodedData());
      $email->add("\n");
    }
    if ($boundary)
      $email->add('--' . $boundary . '--' . "\n");
  }

  /**
   * Calculates and return a map of headers
   *
   * Takes the alternatives and attachments into consideration.
   *
   * @return Email
   */
  public function createEmail() {
    $email = new Email();
    $email->headers = $this->headers;
    $email->headers['MIME-Version'] = '1.0';

    $content_type = $this->getMessageContentType();

    $mixed_boundary = null;
    $alternative_boundary = null;
    if ($content_type == self::MIME_MIXED) {
      $mixed_boundary = $this->createBoundary();
      $content_type .= '; boundary=' . $mixed_boundary;
    }

    if (count($this->alternatives) > 0) {
      $alternative_boundary = $this->createBoundary();
    }
    if ($content_type == self::MIME_ALTERNATIVE) {
      $content_type .= '; boundary=' . $alternative_boundary;
    }

    $email->headers['Content-Type'] = $content_type;

    // Build the body
    if ($mixed_boundary) {
      $email->add('--' . $mixed_boundary . "\n");
      if ($alternative_boundary) {
        $email->add('Content-Type: ' . self::MIME_ALTERNATIVE . '; boundary=' . $alternative_boundary . "\n\n");
      }
    }

    $this->fillBodyWithAlternatives($email, $alternative_boundary);
    $this->fillBodyWithAttachments($email, $mixed_boundary);

    return $email;
  }
}
?>