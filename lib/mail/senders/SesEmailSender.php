<?php
namespace mail\senders;

use \aws\AwsRequest;
use \aws\auth\Aws4Signer;
use \aws\auth\AwsCreds;
use \aws\auth\AwsCredsProvider;
use \mail\EmailMessage;

use \InvalidArgumentException;

/**
 * Sends e-mail messages via Amazon SES.
 *
 * @author Dayan Paez
 * @version 2017-05-30
 */
class SesEmailSender implements EmailSender {

  const SES_ACTION_SEND_RAW_EMAIL = 'SendRawEmail';
  const SES_ENDPOINT = 'email.us-east-1.amazonaws.com';
  const SES_URI = '/';
  const SES_SERVICE_NAME = 'ses';
  const POST_CONTENT_TYPE = 'application/x-www-form-urlencoded';

  const PARAM_AWS_CREDS_PROVIDER = 'aws_creds_provider';
  const PARAM_REGION = 'region';

  private $awsRegion;
  private $awsCredsProvider;

  /**
   * Create a new sender using provided params.
   *
   * PARAM_REGION is required. To specify credentials, pass in a
   * AwsCredsProvider via PARAM_AWS_CREDS_PROVIDER.
   *
   * @param Array $params map of params indexed by class constants PARAM_*.
   */ 
  public function __construct(Array $params) {
    if (!array_key_exists(self::PARAM_REGION, $params) || !is_string($params[self::PARAM_REGION])) {
      throw new InvalidArgumentException(sprintf("%s must be a string", self::PARAM_REGION));
    }
    $this->awsRegion = $params[self::PARAM_REGION];

    if (!array_key_exists(self::PARAM_AWS_CREDS_PROVIDER, $params)) {
      throw new InvalidArgumentException("No credentials provider specified.");
    }
    $this->awsCredsProvider = $params[self::PARAM_AWS_CREDS_PROVIDER];
    if (!($this->awsCredsProvider instanceof AwsCredsProvider)) {
      throw new InvalidArgumentException(sprintf("%s must be AwsCredsProvider", self::PARAM_AWS_CREDS_PROVIDER));
    }
  }

  public function sendEmail(EmailMessage $email) {
    $translated = new SesMailSenderEmailTranslator($email);
    $rawMessage = base64_encode(sprintf(
      "%s\r\n%s",
      $translated->getHeaders(),
      $translated->getBody()
    ));
    $this->postToSes($email->getRecipients(), $rawMessage);
  }

  private function postToSes(Array $recipients, $rawMessage) {
    $params = array(
      'Action' => 'SendRawEmail',
      'RawMessage.Data' => $rawMessage,
    );
    foreach ($recipients as $i => $recipient) {
      $key = sprintf('Destinations.member.%d', $i + 1);
      $params[$key] = $recipient;
    }

    $payload = http_build_query($params);
    $headers = $this->generateHeaders(mb_strlen($payload));
    $request = (new AwsRequest(self::SES_SERVICE_NAME, $this->awsRegion))
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
      $returnValue = false;
    }

    $data = curl_getinfo($ch);
    if ($data['http_code'] >= 400) {
      $returnValue = false;
    }

    curl_close($ch);
    return $returnValue;
  }

  private function initRequest() {
    $ch = curl_init(sprintf('https://%s%s', self::SES_ENDPOINT, self::SES_URI));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TS3 Bot');
    curl_setopt($ch, CURLOPT_POST, 1);
    return $ch;
  }

  private function generateHeaders($contentLength) {
    return array(
      'Host' => self::SES_ENDPOINT,
      'Content-Type' => self::POST_CONTENT_TYPE,
      'Content-Length' => $contentLength,
    );
  }

  private function signRequest(AwsRequest $request) {
    $awsCreds = $this->awsCredsProvider->getCredentials();
    $awsSigner = new Aws4Signer($awsCreds);
    $awsSigner->signRequest($request);
  }
}
