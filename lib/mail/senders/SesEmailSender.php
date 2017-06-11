<?php
namespace mail\senders;

use \aws\AwsRequest;
use \aws\auth\Aws4Signer;
use \aws\auth\AwsCreds;
use \mail\EmailMessage;

use \DB;

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

  const PARAM_ACCESS_KEY = 'access_key';
  const PARAM_SECRET_KEY = 'secret_key';
  const PARAM_REGION = 'region';

  private $awsSigner;
  private $awsRegion;

  /**
   * Create a new sender using provided params.
   *
   * @param Array $params map of params indexed by class constants PARAM_*.
   */ 
  public function __construct(Array $params) {
    $this->awsRegion = DB::$V->reqString($params, self::PARAM_REGION, 3, 50, "No valid region provided.");
    $this->awsSigner = new Aws4Signer(
      new AwsCreds(
        DB::$V->reqString($params, self::PARAM_ACCESS_KEY, 16, 129, "Invalid access key."),
        DB::$V->reqString($params, self::PARAM_SECRET_KEY, 1, 10000, "Invalid secret key.")
      )
    );
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
    $this->awsSigner->signRequest($request);

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
}
