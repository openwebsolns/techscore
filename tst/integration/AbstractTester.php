<?php
use \http\Response;

/**
 * Provides common facilities for integration tests, such as cURL
 * functionality.
 *
 * @author Dayan Paez
 * @created 2015-03-04
 */
abstract class AbstractTester extends PHPUnit_Framework_TestCase {

  const GET = 'GET';
  const POST = 'POST';
  const HEAD = 'HEAD';

  protected function fullUrl($url) {
    return sprintf('http://localhost:8080%s', $url);
  }

  protected function prepareCurlRequest($ch, $method) {
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    if ($method == self::POST) {
      curl_setopt($ch, CURLOPT_POST, 1);
    }
    if ($method == self::HEAD) {
      curl_setopt($ch, CURLOPT_NOBODY, 1);
    }
  }

  protected function doUrl($url, $method = self::GET) {
    $ch = curl_init($this->fullUrl($url));
    $this->prepareCurlRequest($ch, $method);
    $response = curl_exec($ch);
    return new Response($response);
  }

  /**
   * Performs a GET request on given URL, returns response.
   *
   */
  protected function getUrl($url, Array $args = array()) {
    return $this->doUrl($url, self::GET);
  }

  protected function postUrl($url, Array $args = array()) {
    return $this->doUrl($url, self::POST);
  }

  protected function headUrl($url) {
    return $this->doUrl($url, self::HEAD);
  }
}
?>