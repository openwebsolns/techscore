<?php
namespace aws\auth;

/**
 * Encapsulates AK/SK for an AWS request.
 */
class AwsCreds {
  public $access_key;
  public $secret_key;

  public function __construct($access_key = null, $secret_key = null) {
    $this->access_key = $access_key;
    $this->secret_key = $secret_key;
  }
}
