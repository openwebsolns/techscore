<?php
namespace http;

use \InvalidArgumentException;

/**
 * The HEAD portion of an HTTP response.
 *
 * @author Dayan Paez
 * @created 2015-03-10
 */
class ResponseHead {

  /**
   * @var String the raw HEAD of response.
   */
  private $raw;
  /**
   * @var String the first line of the head (HTTP/1...)
   */
  private $statusLine;
  /**
   * HTTP in 'HTTP/1.1 200 OK'
   */
  private $protocol;
  /**
   * 1.1 in 'HTTP/1.1 200 OK'
   */
  private $version;
  /**
   * 200 in 'HTTP/1.1 200 OK'
   */
  private $status;
  /**
   * OK in 'HTTP/1.1 200 OK'
   */  
  private $statusText;
  /**
   * @var Array map of headers, indexed by lower case.
   */
  private $headers;

  public function __construct($raw = null) {
    $this->headers = array();
    if ($raw !== null) {
      $this->parse($raw);
    }
  }

  /**
   * Parse and load the HEAD part of a response.
   *
   * @param String $rawHead
   */
  public function parse($rawHead) {
    $this->raw = (string)$rawHead;
    $parts = explode("\r\n", $this->raw);

    $this->statusLine = array_shift($parts);
    $matches = array();
    if (preg_match(':^([A-Z]+)/([^ ]+) +([0-9]+) +(.*)$:', $this->statusLine, $matches) == 0) {
      throw new InvalidArgumentException(
        sprintf("Unable to parse status line \"%s\".", $this->statusLine)
      );
    }
    $this->protocol = $matches[1];
    $this->version = $matches[2];
    $this->status = $matches[3];
    $this->statusText = $matches[4];

    // The rest of the HEAD are headers of the form 'Name: Value'
    $this->headers = array();
    $regexp = '/^([^: ]+) *: *(.+) *$/';
    foreach ($parts as $headerRaw) {
      $matches = array();
      if (preg_match($regexp, $headerRaw, $matches) == 0) {
        throw new InvalidArgumentException(
          sprintf("Unable to parse header \"%s\".", $headerRaw)
        );
      }
      $this->headers[strtolower($matches[1])] = $matches[2];
    }
  }

  /**
   * Get the raw content of the HEAD.
   *
   * @return String the raw input.
   */
  public function getRaw() {
    return $this->raw;
  }

  /**
   * Return the first line of the HEAD, containing the status.
   *
   * @return String the first line.
   */
  public function getStatusLine() {
    return $this->statusLine;
  }

  public function getProtocol() {
    return $this->protocol;
  }

  public function getProtocolVersion() {
    return $this->version;
  }

  public function getStatus() {
    return $this->status;
  }

  public function getStatusText() {
    return $this->statusText;
  }

  public function getHeader($header) {
    $key = strtolower($header);
    if (array_key_exists($key, $this->headers)) {
      return $this->headers[$key];
    }
    return null;
  }
}
