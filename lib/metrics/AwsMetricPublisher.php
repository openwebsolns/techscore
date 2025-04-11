<?php
namespace metrics;

use \aws\AwsRequest;
use \aws\auth\Aws4Signer;
use \aws\auth\AwsCreds;
use \aws\auth\AwsCredsProvider;

use \DB;
use \Metric;

/**
 * Emits metrics to AWS CloudWatch.
 *
 * @created 2024-03-14
 */
class AwsMetricPublisher implements MetricPublisher {

  const CW_ENDPOINT_FORMAT = 'monitoring.%s.amazonaws.com';
  const CW_SERVICE_NAME = 'monitoring';
  const CW_URI = '/doc/2010-08-01/';
  const POST_CONTENT_TYPE = 'application/x-www-form-urlencoded';

  const PARAM_AWS_CREDS_PROVIDER = 'aws_creds_provider';
  const PARAM_REGION = 'region';
  const PARAM_METRIC_NAMESPACE = 'metric_namespace';

  private $awsRegion;
  private $awsCredsProvider;
  private $metricNamespace;

  /**
   * Create publisher using provided params.
   *
   * PARAM_REGION is required. To specify credentials, pass in a
   * AwsCredsProvider via PARAM_AWS_CREDS_PROVIDER.
   *
   * @param Array $params map of params indexed by class constants PARAM_*.
   */
  public function __construct(Array $params) {
    if (!array_key_exists(self::PARAM_REGION, $params) || !is_string($params[self::PARAM_REGION])) {
      throw new \InvalidArgumentException(sprintf("%s must be a string", self::PARAM_REGION));
    }
    $this->awsRegion = $params[self::PARAM_REGION];

    if (!array_key_exists(self::PARAM_AWS_CREDS_PROVIDER, $params)) {
      throw new \InvalidArgumentException("No credentials provider specified.");
    }
    $this->awsCredsProvider = $params[self::PARAM_AWS_CREDS_PROVIDER];
    if (!($this->awsCredsProvider instanceof AwsCredsProvider)) {
      throw new \InvalidArgumentException(sprintf("%s must be AwsCredsProvider", self::PARAM_AWS_CREDS_PROVIDER));
    }

    $this->metricNamespace = isset($params[self::PARAM_METRIC_NAMESPACE]) ? $params[self::PARAM_METRIC_NAMESPACE] : 'Techscore';
  }

  /**
   * Publishes given metric.
   *
   * @param String $metricName name of the metric to emit
   * @param double $amount the count/amount for the metric
   * @param MetricUnit $unit the unit hint for the metric
   */
  public function publish($metricName, $amount, $unit = self::UNIT_COUNT) {
    $params = array(
      'Action' => 'PutMetricData',
      'Version' => '2010-08-01',
      'Namespace' => $this->metricNamespace,
      'MetricData.member.1.MetricName' => $metricName,
      'MetricData.member.1.Unit' => self::translateUnit($unit),
      'MetricData.member.1.Value' => $amount,
    );

    $payload = http_build_query($params);
    $headers = $this->generateHeaders(mb_strlen($payload));
    $request = (new AwsRequest(self::CW_SERVICE_NAME, $this->awsRegion))
      ->withUri(self::CW_URI)
      ->withMethod(AwsRequest::METHOD_POST)
      ->withHeaders($headers)
      ->withPayload($payload);
    $this->signRequest($request);

    $convertedHeaders = array();
    foreach ($request->headers as $key => $value) {
      $convertedHeaders[] = sprintf('%s: %s', $key, $value);
    }

    $ch = $this->initRequest();
    curl_setopt($ch, CURLOPT_HTTPHEADER, $convertedHeaders);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $output = curl_exec($ch);

    $returnValue = true;
    if ($output === false) {
      $error = curl_error($ch);
      throw new \InvalidArgumentException($error);
    }

    $data = curl_getinfo($ch);
    if ($data['http_code'] >= 400) {
      throw new \InvalidArgumentException('Error code: ' . $data['http_code']);
    }

    curl_close($ch);
  }

  private function initRequest() {
    $ch = curl_init(sprintf('https://%s%s', $this->monitoringEndpoint(), self::CW_URI));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TS3 Bot');
    curl_setopt($ch, CURLOPT_POST, 1);
    return $ch;
  }

  private function generateHeaders($contentLength) {
    return array(
      'Host' => $this->monitoringEndpoint(),
      'Content-Type' => self::POST_CONTENT_TYPE,
      'Content-Length' => $contentLength,
    );
  }

  private function signRequest(AwsRequest $request) {
    $awsCreds = $this->awsCredsProvider->getCredentials();
    $awsSigner = new Aws4Signer($awsCreds);
    $awsSigner->signRequest($request);
  }

  private function monitoringEndpoint() {
    return sprintf(self::CW_ENDPOINT_FORMAT, $this->awsRegion);
  }

  private static function translateUnit($unit) {
    // TODO: support other units
    return 'Count';
  }
}
