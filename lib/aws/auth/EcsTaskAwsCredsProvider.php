<?php
namespace aws\auth;

use \DateInterval;
use \DateTime;
use \RuntimeException;

/**
 * cURLs the ECS task server for a specific role's creds.
 *
 * See https://docs.aws.amazon.com/AmazonECS/latest/developerguide/task-iam-roles.html
 *
 * @created 2024-02-08
 */
class EcsTaskAwsCredsProvider implements AwsCredsProvider {

  const DEFAULT_HOSTNAME = '169.254.170.2';
  const FORMAT_INSTANCE_METADATA_URL = 'http://%s/latest/meta-data/iam/security-credentials/%s';

  /**
   * Wiggle room before credentials expire to mark them stale.
   */
  const EXPIRATION_BUFFER = 'P0DT5M';

  private $expirationDate;
  private $credentialsUri;

  private $creds;

  public function __construct($hostname = self::DEFAULT_HOSTNAME, $credentialsUri = null) {
    if ($credentialsUri == null) {
      $credentialsUri = $_SERVER['AWS_CONTAINER_CREDENTIALS_RELATIVE_URI'];
    }
    $this->credentialsUri = 'http://' . $hostname . $credentialsUri;
    $this->expirationDate = (new DateTime())->sub(new DateInterval(self::EXPIRATION_BUFFER));
  }

  /**
   * @return AwsCreds
   */
  public function getCredentials() {
    if ($this->isStale()) {
      $this->creds = $this->fetchCreds();
    }
    return $this->creds;
  }

  public function isStale() {
    return $this->expirationDate < new DateTime();
  }

  private function fetchCreds() {
    $ch = curl_init($this->credentialsUri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) {
      throw new RuntimeException("Unable to fetch creds from $url");
    }

    $payload = json_decode($res);
    if ($payload === null) {
      throw new RuntimeException("Unable to parse instance metadata response: $res");
    }

    $this->expirationDate = (new DateTime($payload->ExpirationDate))->sub(new DateInterval(EXPIRATION_BUFFER));
    return new AwsCreds($payload->AccessKeyId, $payload->SecretAccessKey, $payload->Token);
  }
}
