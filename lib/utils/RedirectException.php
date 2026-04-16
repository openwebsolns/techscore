<?php
namespace utils;

/**
 * Exception to use when requesting a redirect.
 *
 * Using an exception allows for flow control that immediately ends processing
 * without needing to adhere to normal method semantics.
 *
 * @author Dayan Paez
 * @version 2026-04-15
 */
class RedirectException extends \Exception {
  private $url;

  public function __construct($url = null) {
    $this->url = $url;
  }

  public function __get($field) {
    return $this->$field;
  }
}
