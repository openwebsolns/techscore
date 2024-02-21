<?php
namespace aws;

use \InvalidArgumentException;

/**
 * Encompasses a generic (HTTP) request to an AWS service.
 *
 * @author Dayan Paez
 * @version 2017-06-09
 */
class AwsRequest {

  const DEFAULT_AWS_REGION = 'us-east-1';
  const METHOD_GET = 'GET';
  const METHOD_POST = 'POST';
  const METHOD_PUT = 'PUT';

  private $awsService;
  private $region;
  private $method;
  private $uri;
  private $queryParams;
  private $headers;

  /**
   * @var bytes the raw payload to send
   */
  private $payload;

  /**
   * If not provided, hash will be calculated from payload
   *
   * @var string sha256 hexits of the payload
   */
  private $payloadHash;

  public function __construct($awsService, $region = self::DEFAULT_AWS_REGION) {
    $this->awsService = $awsService;
    $this->region = $region;
    $this->method = self::METHOD_GET;
    $this->uri = '/';
    $this->queryParams = array();
    $this->headers = array();
    $this->payload = '';
    $this->payloadHash = null;
  }

  public function __get($name) {
    if (property_exists($this, $name)) {
      return $this->$name;
    }
    throw new InvalidArgumentException("No such property $name.");
  }

  // cloning

  public static function cloneRequest(AwsRequest $other) {
    return (new AwsRequest($other->awsService, $other->region))
      ->withMethod($other->method)
      ->withUri($other->uri)
      ->withPayload($other->payload)
      ->withPayloadHash($other->payloadHash)
      ->withHeaders($other->headers)
      ->withQueryParams($other->queryParams);
  }

  // "Builder" pattern below

  public function withRegion($region) {
    $this->region = $region;
    return $this;
  }

  public function withMethod($method) {
    $this->method = $method;
    return $this;
  }

  public function withUri($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function withPayload($payload) {
    $this->payload = $payload;
    return $this;
  }

  public function withPayloadHash($payloadHash) {
    $this->payloadHash = $payloadHash;
    return $this;
  }

  public function withHeaders(Array $headers) {
    $this->headers = $headers;
    return $this;
  }

  public function withQueryParams(Array $queryParams) {
    $this->queryParams = $queryParams;
    return $this;
  }
}
