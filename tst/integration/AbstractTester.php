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

  /**
   * Session ID cache for subsequent requests.
   *
   * @see setSession
   */
  private static $session_id = null;

  /**
   * Has the cleanup function been registered?
   *
   * @see setSession
   * @see cleanup
   */
  private static $isCleanupRegistered = false;

  /**
   * Records the session id for next requests...
   *
   * Also logs in the 'super' user through the database.
   *
   * @param String $session_id the session id to set.
   */
  protected static function setSession($session_id) {
    if ($session_id !== null) {
      require_once('TSSessionHandler.php');

      // Extract ID
      $sid = self::extractSessionId($session_id);

      // Find the session
      $data = TSSessionHandler::read($sid);
      if ($data === null) {
        throw new InvalidArgumentException("Session $sid not saved to database.");
      }

      // Find me a super user
      $obj = DB::T(DB::ACCOUNT);
      $obj->db_set_order(array('ts_role'=>false));
      $users = DB::getAdmins();
      if (count($users) == 0) {
        throw new InvalidArgumentException("No super/admin user exists!");
      }

      $user_id = $users[0]->id;
      $length = strlen($user_id);

      $partial = sprintf('a:1:{s:4:"user";s:%d:"%s";}', $length, $user_id);
      $data = sprintf('data|s:%d:"%s";', strlen($partial), $partial);
      TSSessionHandler::write($sid, $data);

      if (!self::$isCleanupRegistered) {
        register_shutdown_function('AbstractTester::cleanup');
        self::$isCleanupRegistered = true;
      }
    }

    self::$session_id = $session_id;
  }

  /**
   * Cleanup the session created in database, and other things.
   *
   */
  public static function cleanup() {
    if (self::$session_id !== null) {
      TSSessionHandler::destroy(self::extractSessionId(self::$session_id));
    }
  }

  /**
   * Takes in SID=<session_id> and returns <session_id>
   *
   */
  private static function extractSessionId($cookie_string) {
    $parts = explode('=', $cookie_string);
    if (count($parts) < 2) {
      throw new InvalidArgumentException("Invalid cookie string: $cookie_string.");
    }
    array_shift($parts);
    return implode('=', $parts);
  }

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
    if (self::$session_id !== null) {
      curl_setopt($ch, CURLOPT_COOKIE, self::$session_id);
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

  //
  // Assertions
  //

  /**
   * Assert that given response has the requested HTTP status.
   *
   * @param Response $respone the respone whose head to test.
   * @param int $status the HTTP status expected.
   * @param String $message the error message, if any.
   */
  protected function assertResponseStatus(Response $response, $status = 200, $message = null) {
    $head = $response->getHead();
    $this->assertEquals($status,  $head->getStatus(), $message);
  }
}
?>