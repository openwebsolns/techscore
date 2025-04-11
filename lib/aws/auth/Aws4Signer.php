<?php
namespace aws\auth;

use \aws\AwsRequest;

use \DateTime;
use \DateTimeZone;

/**
 * Creates AWS4 Signatures for making requests.
 *
 * @author Dayan Paez
 * @version 2017-06-02
 */
class Aws4Signer {
  
  const AWS_ALGORITHM = 'AWS4-HMAC-SHA256';
  const HASH = 'sha256';
  const SIGNATURE_VERSION = 'AWS4';
  const CREDENTIAL_SCOPE = 'aws4_request';

  private $awsCreds;

  public function __construct(AwsCreds $awsCreds) {
    $this->awsCreds = $awsCreds;
  }

  public function signRequest(AwsRequest $request, DateTime $date = null) {
    if ($date === null) {
      $date = new DateTime('now', new DateTimeZone('UTC'));
    }
    $headers = $request->headers;
    $headers['X-Amz-Date'] = $date->format('Ymd\THis\Z');
    if ($this->awsCreds->token !== null) {
      $headers['X-Amz-Security-Token'] = $this->awsCreds->token;
    }

    $signedRequest = AwsRequest::cloneRequest($request)->withHeaders($headers);
    $headers['Authorization'] = $this->generateAuthorizationHeader($signedRequest, $date);

    return $request->withHeaders($headers);
  }

  public function generateAuthorizationHeader(AwsRequest $request, DateTime $date) {
    $canonicalHeaders = $this->canonicalizeHeaders($request->headers);
    $signedHeaders = array_keys($canonicalHeaders);
    $canonicalParams = $this->canonicalizeQueryParams($request->queryParams);
    $request->withHeaders($canonicalHeaders);
    $request->withQueryParams($canonicalParams);

    $canonicalRequestHash = $this->generateCanonicalRequestHash($request);
    $credentialScope = $this->generateCredentialScope($request, $date);
    $stringToSign = $this->generateStringToSign($credentialScope, $canonicalRequestHash, $date);
    $signingKey = $this->generateSigningKey($request, $date);
    $signature = hash_hmac(self::HASH, $stringToSign, $signingKey);

    return sprintf(
      '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
      self::AWS_ALGORITHM,
      $this->awsCreds->access_key,
      $credentialScope,
      implode(';', $signedHeaders),
      $signature
    );
  }

  private function canonicalizeHeaders(Array $headers) {
    $canonicalHeaders = array();
    $headerNames = array();
    foreach ($headers as $name => $value) {
      $headerName = mb_strtolower(trim($name));
      $canonicalHeaders[$headerName] = trim($value);
      $headerNames[] = $headerName;
    }
    array_multisort($headerNames, $canonicalHeaders);
    return $canonicalHeaders;
  }

  private function canonicalizeQueryParams(Array $queryParams) {
    $canonicalQueryParams = array();
    $paramNames = array();
    foreach ($queryParams as $key => $value) {
      $paramNames[] = $key;
      $canonicalQueryParams[$key] = $value;
    }
    array_multisort($paramNames, $canonicalQueryParams);
    return $canonicalQueryParams;
  }

  private function generateCanonicalRequestHash(AwsRequest $request) {
    $headers = '';
    foreach ($request->headers as $header => $value) {
      $headers .= sprintf("%s:%s\n", $header, $value);
    }

    $requestHash = $request->payloadHash;
    if ($requestHash === null) {
      $requestHash = hash(self::HASH, $request->payload);
    }

    $canonicalRequestElems = array(
      $request->method,
      $request->uri,
      http_build_query($request->queryParams),
      $headers,
      implode(';', array_keys($request->headers)),
      $requestHash,
    );
    return hash(self::HASH, implode("\n", $canonicalRequestElems));
  }

  private function generateCredentialScope(AwsRequest $request, DateTime $date) {
    $credentialScope = array(
      $date->format('Ymd'),
      $request->region,
      $request->awsService,
      self::CREDENTIAL_SCOPE,
    );
    return implode('/', $credentialScope);
  }

  private function generateStringToSign($credentialScope, $canonicalRequestHash, DateTime $date) {
    $elems = array(
      self::AWS_ALGORITHM,
      $date->format('Ymd\THis\Z'),
      $credentialScope,
      $canonicalRequestHash
    );

    return implode("\n", $elems);
  }

  private function generateSigningKey(AwsRequest $request, DateTime $date) {
    $kDate = hash_hmac(self::HASH, $date->format('Ymd'), self::SIGNATURE_VERSION . $this->awsCreds->secret_key, true);
    $kRegion = hash_hmac(self::HASH, $request->region, $kDate, true);
    $kService = hash_hmac(self::HASH, $request->awsService, $kRegion, true);
    $kSigning = hash_hmac(self::HASH, self::CREDENTIAL_SCOPE, $kService, true);
    return $kSigning;
  }
}
