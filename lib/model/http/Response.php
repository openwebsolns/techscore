<?php
namespace http;

use \InvalidArgumentException;

/**
 * Takes in a whole HTTP response (including header) and parses.
 *
 * @author Dayan Paez
 * @created 2015-03-10
 */
class Response {

  /**
   * @var String the raw input.
   */
  private $raw;
  /**
   * @var Head the head of the response.
   */
  private $head;
  /**
   * @var String the raw BODY of response.
   */
  private $body;

  /**
   * Creates a new object using optional argument.
   *
   * @param String $raw the entire head/body to parse.
   */
  public function __construct($raw = null) {
    if ($raw !== null) {
      $this->parse($raw);
    }
  }

  /**
   * Parse the given raw input which includes head/body.
   *
   * @param String $raw the input to parse.
   * @throws InvalidArgumentException
   */
  public function parse($raw) {
    $this->raw = (string)$raw;
    $parts = explode("\r\n\r\n", $this->raw);

    $this->head = new ResponseHead(array_shift($parts));

    $this->body = null;
    if (count($parts) > 0) {
      $this->body = new ResponseBody(implode("\r\n\r\n", $parts));
    }
  }

  /**
   * Get the raw content of the response.
   *
   * @return String the raw input.
   */
  public function getRaw() {
    return $this->raw;
  }

  public function getHead() {
    return $this->head;
  }

  public function getBody() {
    return $this->body;
  }

  public function getStatusLine() {
    return $this->statusLine;
  }
}
