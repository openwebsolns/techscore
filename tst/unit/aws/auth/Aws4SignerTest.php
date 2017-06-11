<?php
namespace aws\auth;

use \aws\AwsRequest;

use \AbstractUnitTester;
use \DateTime;

/**
 * Tests our implementation of Aws4Signer against the AWS docs examples.
 *
 * @author Dayan Paez
 * @version 2017-06-08
 */
class Aws4SignerTest extends AbstractUnitTester {

  private $testObject;

  /**
   * Follow example from https://docs.aws.amazon.com/general/latest/gr/sigv4_signing.html
   */
  public function testSignRequest() {
    $rawHeaders = array(
      'Host' => 'iam.amazonaws.com',
      'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
    );
    $rawQueryParams = array(
      'Version' => '2010-05-08',
      'Action' => 'ListUsers', 
    );

    $request = (new AwsRequest('iam', 'us-east-1'))
      ->withMethod(AwsRequest::METHOD_GET)
      ->withUri('/')
      ->withQueryParams($rawQueryParams)
      ->withHeaders($rawHeaders)
      ->withPayload('');

    $date = new DateTime('20150830T123600Z');

    $awsCreds = new AwsCreds('AKIDEXAMPLE', 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY');

    $testObject = new Aws4Signer($awsCreds);
    $testObject->signRequest($request, $date);
    $headers = $request->headers;

    $this->assertArrayHasKey('Authorization', $headers);
    $this->assertEquals(
      'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20150830/us-east-1/iam/aws4_request, SignedHeaders=content-type;host;x-amz-date, Signature=5d672d79c15b13162d9279b0855cfba6789a8edb4c82c400e06b5924a6f2b5d7',
      $headers['Authorization']
    );

    // Assert that headers and params are intact
    foreach ($rawHeaders as $key => $value) {
      $this->assertArrayHasKey($key, $headers);
      $this->assertEquals($value, $headers[$key]);
    }

    $params = $request->queryParams;
    foreach ($rawQueryParams as $key => $value) {
      $this->assertArrayHasKey($key, $params);
      $this->assertEquals($value, $params[$key]);
    }
  }
}