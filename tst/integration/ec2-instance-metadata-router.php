<?php
/**
 * Router script expected by php -S to mimic EC2's instance metadata.
 *
 * @see InstanceMetadataAwsCredsProviderTest
 */

$expectedUri = '/latest/meta-data/iam/security-credentials/test';
$actualUri = $_SERVER['REQUEST_URI'];
if ($actualUri !== $expectedUri) {
  echo "Invalid URI; expected $expectedUri, got $actualUri.";
  exit;
}

echo '
{
  "Code" : "Success",
  "LastUpdated" : "2017-06-17T10:55:05Z",
  "Type" : "AWS-HMAC",
  "AccessKeyId" : "TEST_ACCESS_KEY",
  "SecretAccessKey" : "TEST_SECRET_KEY",
  "Token" : "TOKEN",
  "Expiration" : "2017-06-17T17:16:22Z"
}
';
