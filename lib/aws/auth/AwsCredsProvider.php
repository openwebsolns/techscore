<?php
namespace aws\auth;

interface AwsCredsProvider {
  /**
   * @return AwsCreds
   */
  public function getCredentials();
}