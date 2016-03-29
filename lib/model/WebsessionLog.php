<?php
namespace model;

use \Conf;

/**
 * Track the requests, a la webserver's access logs.
 *
 * @author Dayan Paez
 * @version 2016-03-29
 */
class WebsessionLog extends Object {

  const DEFAULT_RESPONSE = '200';

  public $websession;
  public $method;
  public $url;
  public $user_agent;
  public $http_referer;
  public $post;
  public $response_code;

  public function db_name() {
    return 'websession_log';
  }

  /**
   * Creates a new entry based on global parameters.
   *
   * @return WebsessionLog the recorded entry.
   */
  public static function record() {
    $sessionId = session_id();
    if (empty($sessionId)) {
      return null;
    }

    $obj = new WebsessionLog();
    $obj->websession = $sessionId;
    $obj->method = self::getServerKey('REQUEST_METHOD');
    $obj->url = self::getServerKey('REQUEST_URI');
    $obj->user_agent = self::getServerKey('HTTP_USER_AGENT');
    $obj->http_referer = self::getServerKey('HTTP_REFERER');
    $obj->post = json_encode($_POST);
    $obj->response_code = self::DEFAULT_RESPONSE;

    $obj->db_commit();
    Conf::$DB->commit();
    return $obj;
  }

  private static function getServerKey($key, $default = null) {
    return array_key_exists($key, $_SERVER) ? $_SERVER[$key] : $default;
  }
}