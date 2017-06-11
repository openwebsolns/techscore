<?php
namespace mail\senders;

use \mail\Alternative;
use \mail\Attachment;
use \mail\EmailMessage;

/**
 * Turns an EmailMessage into raw body and headers, complete with
 * boundaries, etc.
 *
 * @author Dayan Paez
 * @version 2015-10-14
 * @see PhpMailSender
 */
class PhpMailSenderEmailTranslator {

  const MIME_MIXED = 'multipart/mixed';
  const MIME_ALTERNATIVE = 'multipart/alternative';

  protected $email;
  protected $headers;
  protected $body;

  public function __construct(EmailMessage $email) {
    $this->email = $email;
    $this->body = '';
    $this->headers = array();

    $this->translate();
  }

  /**
   * @return String the headers ready for PHP's mail().
   */
  public function getHeaders() {
    $headers = '';
    foreach ($this->headers as $key => $val) {
      $headers .= sprintf("%s: %s\n", $key, $val);
    }
    return $headers;
  }

  /**
   * @return String the body of the message.
   */
  public function getBody() {
    return $this->body;
  }

  /**
   * Convenience method where magic takes place.
   */
  private function translate() {
    $this->headers = $this->email->getHeaders();
    $this->headers['MIME-Version'] = '1.0';

    $content_type = $this->getMessageContentType();

    $mixed_boundary = null;
    $alternative_boundary = null;
    if ($content_type == self::MIME_MIXED) {
      $mixed_boundary = $this->createBoundary();
      $content_type .= '; boundary=' . $mixed_boundary;
    }

    if (count($this->email->getAlternatives()) > 0) {
      $alternative_boundary = $this->createBoundary();
    }
    if ($content_type == self::MIME_ALTERNATIVE) {
      $content_type .= '; boundary=' . $alternative_boundary;
    }

    $this->headers['Content-Type'] = $content_type;

    // Build the body
    if ($mixed_boundary) {
      $this->add('--' . $mixed_boundary . "\n");
      if ($alternative_boundary) {
        $this->add('Content-Type: ' . self::MIME_ALTERNATIVE . '; boundary=' . $alternative_boundary . "\n\n");
      }
    }

    $this->fillBodyWithAlternatives($alternative_boundary);
    $this->fillBodyWithAttachments($mixed_boundary);
  }

  private function add($text) {
    $this->body .= (string)$text;
  }

  protected function getMessageContentType() {
    if (count($this->email->getAttachments()) > 0)
      return self::MIME_MIXED;
    if (count($this->email->getAlternatives()) > 0)
      return self::MIME_ALTERNATIVE;
    return $this->email->mime_types[0];
  }

  /**
   * Create a string not found in any part of message
   *
   * @return String
   */
  private function createBoundary() {
    // Need not only check the plain text alternatives because the
    // others (and the attachments) will be base64 encoded.
    $found = true;
    while ($found) {
      $bdry = uniqid(rand(100, 999), true);
      $found = false;
      foreach ($this->email->getAlternatives() as $segment) {
        if ($segment->getMimeType() == Alternative::TEXT_PLAIN && strstr($segment->getContent(), $bdry) !== false) {
          $found = true;
          break;
        }
      }
    }
    return $bdry;
  }

  private function fillBodyWithAlternatives($boundary = null) {
    // Alternatives
    foreach ($this->email->getAlternatives() as $alternative) {
      $content = $alternative->getContent();
      $type = $alternative->getMimeType();
      $charset = $alternative->getCharset();

      if ($boundary != null) {
        $this->add('--' . $boundary . "\n");

        if (!empty($charset)) {
          $type .= '; charset=' . $charset;
        }

        $this->add('Content-Type: ' . $type . "\n");
        if ($type != Alternative::TEXT_PLAIN) {
          $this->add("Content-Transfer-Encoding: base64\n");
          $content = base64_encode($content);
        }
        $this->add("\n");
      }

      $this->add($content);
      $this->add("\n");
    }
    if ($boundary) {
      $this->add('--' . $boundary . '--' . "\n");
    }
  }

  protected function fillBodyWithAttachments($boundary = null) {
    foreach ($this->email->getAttachments() as $f) {
      $this->add('--' . $boundary . "\n");
      $this->add('Content-Type: ' . $f->getMIME() . '; name="' . $f->getName() . '"' . "\n");
      $this->add('Content-Disposition: attachment; filename="' . $f->getName() . '"' . "\n");
      $this->add('Content-Transfer-Encoding: base64' . "\n");
      $this->add("\n");
      $this->add($f->getBase64EncodedData());
      $this->add("\n");
    }
    if ($boundary) {
      $this->add('--' . $boundary . '--' . "\n");
    }
  }
}