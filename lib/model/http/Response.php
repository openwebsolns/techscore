<?php
namespace http;

/**
 * Takes in a whole HTTP response (including header) and parses.
 *
 * @author Dayan Paez
 * @created 2015-03-10
 */
class Response {

  /**
   * @var String the raw input
   */
  private $raw;

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
   */
  public function parse($raw) {
    $this->raw = $raw;
    echo 'IN HERE';
    exit;
  }

  /**
   * Get the raw content of the response.
   *
   * @return String the raw input.
   */
  public function getRaw() {
    return $this->raw;
  }
}
?>