<?php
namespace mail;

/**
 * Represents an e-mail message alternative, with MIME type and charset.
 *
 * @author Dayan Paez
 * @version 2015-10-14
 */
class Alternative {

  const TEXT_PLAIN = 'text/plain';
  const UTF8 = 'UTF-8';

  /**
   * @var String MIME type for the alternative.
   */
  protected $mime_type;
  /**
   * @var String the charset.
   */
  protected $charset;
  /**
   * @var String the actual content.
   */
  protected $content;

  /**
   * Creates a new email content with given parameters.
   *
   * @param String $content the actual content.
   * @param String $mime_type the MIME type.
   * @param String $charset the character set.
   */
  public function __construct($content, $mime_type = self::TEXT_PLAIN, $charset = self::UTF8) {
    $this->content = (string) $content;
    $this->mime_type = (string) $mime_type;
    $this->charset = (string) $charset;
  }

  public function getContent() {
    return $this->content;
  }

  public function getMimeType() {
    return $this->mime_type;
  }

  public function getCharset() {
    return $this->charset;
  }

  public static function parseMimeType($mime) {
    $parts = explode(';', $mime);
    return $parts[0];
  }
}