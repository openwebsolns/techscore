<?php
namespace aws\auth;

class BasicAwsCredsProvider implements AwsCredsProvider {

  private $creds;

  public function __construct($access_key, $secret_key, $session_token = null) {
    $this->creds = new AwsCreds($access_key, $secret_key, $session_token);
  }

  public function getCredentials() {
    return $this->creds;
  }
}
