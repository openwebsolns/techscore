<?php
namespace mail\bouncing;

use \Account;
use \DB;
use \aws\AwsRequest;
use \aws\auth\Aws4Signer;
use \aws\auth\AwsCreds;
use \aws\auth\AwsCredsProvider;
use \mail\EmailMessage;

use \InvalidArgumentException;

/**
 * Handles bounce messages from SQS queue.
 *
 * @author Dayan Paez
 * @version 2022-10-31
 */
class SqsBounceHandler {

  const SQS_ACTION_RECEIVE_MESSAGE = 'ReceiveMessage';
  const SQS_ACTION_DELETE_MESSAGE = 'DeleteMessage';
  const SQS_SERVICE_NAME = 'sqs';
  const SQS_VERSION = '2012-11-05';

  const PARAM_AWS_CREDS_PROVIDER = 'aws_creds_provider';
  const PARAM_REGION = 'region';
  const PARAM_QUEUE_URL = 'queue_url';

  private $awsRegion;
  private $awsCredsProvider;
  private $sqsQueueUrl;
  private $sqsQueueUrlParsed;

  /**
   * Create a new handler using provided params.
   *
   * PARAM_REGION, PARAM_QUEUE_URL are required. To specify credentials, pass in a
   * AwsCredsProvider via PARAM_AWS_CREDS_PROVIDER.
   *
   * @param Array $params map of params indexed by class constants PARAM_*.
   */ 
  public function __construct(Array $params) {
    foreach (array(self::PARAM_REGION, self::PARAM_QUEUE_URL) as $param) {
      if (!array_key_exists($param, $params) || !is_string($params[$param])) {
        throw new InvalidArgumentException(sprintf("%s must be a string", $param));
      }    
    }
    $this->awsRegion = $params[self::PARAM_REGION];
    $this->sqsQueueUrl = $params[self::PARAM_QUEUE_URL];
    $this->sqsQueueUrlParsed = parse_url($this->sqsQueueUrl);
    if ($this->sqsQueueUrlParsed === false) {
      throw new InvalidArgumentException("Unable to parse or missing SQS queue URL");
    }

    if (!array_key_exists(self::PARAM_AWS_CREDS_PROVIDER, $params)) {
      throw new InvalidArgumentException("No credentials provider specified.");
    }
    $this->awsCredsProvider = $params[self::PARAM_AWS_CREDS_PROVIDER];
    if (!($this->awsCredsProvider instanceof AwsCredsProvider)) {
      throw new InvalidArgumentException(sprintf("%s must be AwsCredsProvider", self::PARAM_AWS_CREDS_PROVIDER));
    }
  }

  public function handle() {
    $output = $this->receiveMessages();

    // Parse output
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($output);
    if (!$doc) {
      $errors = "";
      foreach(libxml_get_errors() as $error)
        $errors .= '@' . $error->line . ',' . $error->column . ': ' . $error->message . "\n";
      throw new InvalidArgumentException("Invalid response: $errors");
    }

    // Step through each message
    $accountsAffected = array();
    foreach ($doc->ReceiveMessageResult->Message as $message) {
      $receiptHandle = (string) $message->ReceiptHandle;
      $body = json_decode($message->Body);
      $recipients = array();
      foreach ($body->bounce->bouncedRecipients as $recipient) {
        $account = DB::getAccountByEmail($recipient->emailAddress);
        if ($account) {
          $account->email_inbox_status = Account::EMAIL_INBOX_STATUS_BOUNCING;
          DB::set($account);
          $accountsAffected[] = $account;
        }
      }

      $this->deleteMessage($receiptHandle);
    }

    return $accountsAffected;
  }

  private function receiveMessages() {
    return $this->execute(array(
      'Action' => self::SQS_ACTION_RECEIVE_MESSAGE,
      'Version' => self::SQS_VERSION,
      'MaxNumberOfMessages' => 10,
    ));
  }

  private function deleteMessage($receiptHandle) {
    return $this->execute(array(
      'Action' => self::SQS_ACTION_DELETE_MESSAGE,
      'Version' => self::SQS_VERSION,
      'ReceiptHandle' => $receiptHandle,
    ));
  }

  private function execute(Array $params) {
    $headers = array('Host' => $this->sqsQueueUrlParsed['host']);
    $request = (new AwsRequest(self::SQS_SERVICE_NAME, $this->awsRegion))
      ->withMethod(AwsRequest::METHOD_GET)
      ->withUri($this->sqsQueueUrlParsed['path'])
      ->withQueryParams($params)
      ->withHeaders($headers);
    $this->signRequest($request);

    $convertedHeaders = array();
    foreach ($request->headers as $key => $value) {
      $convertedHeaders[] = sprintf('%s: %s', $key, $value);
    }

    $ch = $this->initRequest($params);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $convertedHeaders);
    $output = curl_exec($ch);

    if ($output === false) {
      $error = curl_error($ch);
      throw new InvalidArgumentException($error);
    }

    $data = curl_getinfo($ch);
    if ($data['http_code'] >= 400) {
      throw new InvalidArgumentException(sprintf("Received HTTP %s", $data['http_code']));
    }

    curl_close($ch);
    return $output;
  }

  private function initRequest(Array $params) {
    $ch = curl_init(sprintf('%s?%s', $this->sqsQueueUrl, http_build_query($params)));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TS3 Bot');
    return $ch;
  }

  private function signRequest(AwsRequest $request) {
    $awsCreds = $this->awsCredsProvider->getCredentials();
    $awsSigner = new Aws4Signer($awsCreds);
    $awsSigner->signRequest($request);
  }
}
