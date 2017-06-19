<?php
namespace aws\auth;

use \DateInterval;
use \DateTime;
use \RuntimeException;

/**
 * cURLs the EC2 instance metadata server for a specific role's creds.
 *
 * Caches the results for up to 5 minutes.
 */
class InstanceMetadataAwsCredsProvider implements AwsCredsProvider {

  const DEFAULT_CACHE_TIMEOUT = 'P0DT5M';
  const DEFAULT_HOSTNAME = '169.254.169.254';
  const FORMAT_INSTANCE_METADATA_URL = 'http://%s/latest/meta-data/iam/security-credentials/%s';

  private $role;
  private $cacheTimeout;
  private $hostname;

  private $creds;
  private $lastFetchTime;

  public function __construct($role, DateInterval $cacheTimeout = null, $hostname = self::DEFAULT_HOSTNAME) {
    $this->role = $role;
    $this->cacheTimeout = $cacheTimeout;
    if ($this->cacheTimeout === null) {
      $this->cacheTimeout = new DateInterval(self::DEFAULT_CACHE_TIMEOUT);
    }
    $this->hostname = $hostname;
  }

  /**
   * @return AwsCreds
   */
  public function getCredentials() {
    if ($this->isStale()) {
      $this->creds = $this->fetchCreds();
      $this->lastFetchTime = new DateTime();
    }
    return $this->creds;
  }

  public function isStale() {
    if ($this->lastFetchTime === null) {
      return true;
    }
    return (new DateTime())->diff($this->lastFetchTime) > $this->cacheTimeout;
  }

  private function fetchCreds() {
    $url = sprintf(self::FORMAT_INSTANCE_METADATA_URL, $this->hostname, $this->role);
    $ch = curl_init($url);
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

    return new AwsCreds($payload->AccessKeyId, $payload->SecretAccessKey, $payload->Token);
  }
}