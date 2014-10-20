<?php
/*
 * This file is part of TechScore
 */

/**
 * E-mail message with headers and body
 *
 * @author Dayan Paez
 * @created 2014-10-19
 */
class Email {
  /**
   * @var Array $headers
   */
  public $headers;
  /**
   * @var String
   */
  private $body;

  public function __construct() {
    $this->body = '';
  }

  public function add($text) {
    $this->body .= (string)$text;
  }

  public function getBody() {
    return $this->body;
  }

  public function getHeaders() {
    return $this->headers;
  }
}
?>