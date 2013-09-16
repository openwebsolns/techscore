<?php
class TwitterWriterException extends Exception {}

/**
 * Post a message to Twitter
 *
 * @author Dayan Paez
 * @created 2013-09-13
 */
class TwitterWriter {

  const TWITTER_API_DOMAIN = 'api.twitter.com';
  const TWITTER_API_PATH = '/1.1';

  /**
   * @var String the Consumer Key
   */
  protected $oauth_consumer_key;
  /**
   * @var String the Consumer Secret
   */
  protected $oauth_consumer_secret;
  /**
   * @var String the Application Token
   */
  protected $oauth_token;
  /**
   * @var String the Application Token Secret
   */
  protected $oauth_token_secret;

  public function __construct($ckey, $csecret, $apptoken, $appsecret) {
    $this->oauth_consumer_key = $ckey;
    $this->oauth_consumer_secret = $csecret;
    $this->oauth_token = $apptoken;
    $this->oauth_token_secret = $appsecret;
  }

  public function getConfigUrl() {
    return sprintf('https://%s%s/help/configuration.json', self::TWITTER_API_DOMAIN, self::TWITTER_API_PATH);
  }

  public function getPostUrl() {
    return sprintf('https://%s%s/statuses/update.json', self::TWITTER_API_DOMAIN, self::TWITTER_API_PATH);
  }

  /**
   * Helper method: initiates the cURL request to POST message
   *
   * @param String $url the URL for the connection
   * @return resource curl connection
   * @throws TwitterWriterException
   */
  protected function prepRequest($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TS3 Bot');
    return $ch;
  }

  /**
   * Returns the signature based on given parameters
   *
   * @see https://dev.twitter.com/docs/auth/creating-signature
   */
  public function sign($method, $url, $status, $nonce, $date) {
    $fields = array('oauth_consumer_key' => $this->oauth_consumer_key,
                    'oauth_nonce' => $nonce,
                    'oauth_signature_method' => 'HMAC-SHA1',
                    'oauth_timestamp' => $date,
                    'oauth_token' =>  $this->oauth_token,
                    'oauth_version' => "1.0");
    if ($status !== null)
      $fields['status'] = $status;

    $string = "";
    $i = 0;
    foreach ($fields as $key => $value) {
      if ($i > 0)
        $string .= "&";
      $i++;
      $string .= rawurlencode($key) . "=" . rawurlencode($value);
    }
    
    $base = strtoupper($method) . "&" . rawurlencode($url) . "&" . rawurlencode($string);
    $key = rawurlencode($this->oauth_consumer_secret) . "&" . rawurlencode($this->oauth_token_secret);
    return base64_encode(hash_hmac('sha1', $base, $key, true));
  }

  /**
   * Gets the content of the Authorization: header for the request
   *
   * @param String $signature the signature to use
   */
  protected function getAuthorizationHeader($signature, $nonce, $date) {
    $fields = array('oauth_consumer_key' => $this->oauth_consumer_key,
                    'oauth_nonce' => $nonce,
                    'oauth_signature' => $signature,
                    'oauth_signature_method' => 'HMAC-SHA1',
                    'oauth_timestamp' => $date,
                    'oauth_token' =>  $this->oauth_token,
                    'oauth_version' => "1.0");
    $auth = "OAuth ";
    $i = 0;
    foreach ($fields as $key => $value) {
      if ($i > 0)
        $auth .= ", ";
      $i++;
      $auth .= rawurlencode($key) . "=\"" . rawurlencode($value) . "\"";
    }
    return $auth;
  }

  public function checkConfig() {
    $url = $this->getConfigUrl();
    $nonce = base64_encode(md5(date('U') . "\0" . Conf::$HOME . "\0" . rand()));
    $date = date('U');
    $sig = $this->sign("GET", $url, null, $nonce, $date);
    $auth = $this->getAuthorizationHeader($sig, $nonce, $date);

    $ch = $this->prepRequest($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: " . $auth));
    if (($output = curl_exec($ch)) === false) {
      $mes = curl_error($ch);
      curl_close($ch);
      throw new TwitterWriterException($mes);
    }
    curl_close($ch);

    $obj = json_decode($output, true);
    if (isset($obj['errors']))
      throw new TwitterWriterException(sprintf("%s: %s", $obj['errors'][0]['code'], $obj['errors'][0]['message']));
    return $obj;
  }

  /**
   * Post the given tweet
   *
   * @param String $status the content of the tweet
   * @boolean success?
   */
  public function tweet($status) {
    $url = $this->getPostURL();
    $nonce = base64_encode(md5(date('U') . "\0" . Conf::$HOME . "\0" . rand()));
    $date = date('U');
    $sig = $this->sign("POST", $url, $status, $nonce, $date);
    $auth = $this->getAuthorizationHeader($sig, $nonce, $date);

    $ch = $this->prepRequest($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "status=" . rawurlencode($status));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: " . $auth));
    if (($output = curl_exec($ch)) === false) {
      $mes = curl_error($ch);
      curl_close($ch);
      throw new TwitterWriterException($mes);
    }

    curl_close($ch);
    $obj = json_decode($output, true);
    if (isset($obj['errors']))
      throw new TwitterWriterException(sprintf("%s: %s", $obj['errors'][0]['code'], $obj['errors'][0]['message']));
    return $obj;
  }
}
?>