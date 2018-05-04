<?php
namespace aws\auth;

use \AbstractUnitTester;

class BasicAwsCredsProviderTest extends AbstractUnitTester {

  public function testPopo() {
    $accessKey = 'ACCESS_KEY';
    $secretKey = 'SECRET_KEY';

    $provider = new BasicAwsCredsProvider($accessKey, $secretKey);
    $creds = $provider->getCredentials();
    $this->assertEquals($accessKey, $creds->access_key);
    $this->assertEquals($secretKey, $creds->secret_key);
  }
}
