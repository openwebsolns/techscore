<?php
namespace aws\auth;

use \AbstractTester;

require_once(dirname(dirname(__DIR__)) . '/AbstractTester.php');

/**
 * Test against tst/integration/ec2-instance-metadata-router.php
 */
class InstanceMetadataAwsCredsProviderTest extends AbstractTester {

  /*
   * @see ec2-instance-metadata-router.php
   */
  const ACCESS_KEY = 'TEST_ACCESS_KEY';
  const SECRET_KEY = 'TEST_SECRET_KEY';
  const ROLE = 'test';
  const HOSTNAME = 'localhost:8081';

  public function testValidRole() {
    $testObject = new InstanceMetadataAwsCredsProvider(self::ROLE, null, self::HOSTNAME);

    $creds = $testObject->getCredentials();
    $this->assertEquals(self::ACCESS_KEY, $creds->access_key);
    $this->assertEquals(self::SECRET_KEY, $creds->secret_key);
  }

  /**
   * @expectedException RuntimeException
   */
  public function testInvalidRole() {
    $testObject = new InstanceMetadataAwsCredsProvider(self::ROLE . '-invalid', null, self::HOSTNAME);
    $testObject->getCredentials();
  }
}