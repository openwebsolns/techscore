<?php
use \http\Response;

require_once('utils/RegattaCreator.php');

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
   * Cache of the CSRF token for POST requests.
   */
  private static $csrf_token = null;
  /**
   * The singleton regatta creator to use.
   */
  protected static $regatta_creator = null;
  /**
   * Has the setup been performed?
   */
  private static $isSetupDone = false;

  public static function setUpBeforeClass() {
    if (!self::$isSetupDone) {
      register_shutdown_function('AbstractTester::cleanup');
      self::$regatta_creator = new RegattaCreator();
    }
  }

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
      $obj->db_set_order();
      if (count($users) == 0) {
        throw new InvalidArgumentException("No super/admin user exists!");
      }

      $user_id = $users[0]->id;
      $length = strlen($user_id);

      $partial = sprintf('a:1:{s:4:"user";s:%d:"%s";}', $length, $user_id);
      $data = sprintf('data|s:%d:"%s";', strlen($partial), $partial);
      TSSessionHandler::write($sid, $data);
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
    if (self::$regatta_creator !== null) {
      self::$regatta_creator->cleanup();
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

  /**
   * Helper method makes sure that there is a logged-in user.
   *
   */
  protected function login() {
    if (self::$session_id === null) {
      $response = $this->getUrl('/');
      $head = $response->getHead();
      $cookie = $head->getHeader('Set-Cookie');
      $cookie_parts = explode(';', $cookie);
      self::setSession($cookie_parts[0]);

      $body = $response->getBody();
      $root = $body->asXml();
      $tokens = $root->xpath('//html:input[@name="csrf_token"]');
      if (count($tokens) > 0) {
        self::$csrf_token = $tokens[0]['value'];
      }
    }
  }

  protected function fullUrl($url) {
    return sprintf('http://localhost:8080%s', $url);
  }

  protected function prepareCurlRequest($ch, $url, $method, Array $args = array(), Array $headers = array()) {
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
    if (count($headers) > 0) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    // Any arguments?
    if (count($args) > 0) {
      switch ($method) {
      case self::GET:
        $url .= '?' . http_build_query($args);
        break;

      case self::POST:
        $args['csrf_token'] = (string) self::$csrf_token;
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
        break;

      default:
        throw new InvalidArgumentException("Don't know how to use arguments for $method.");
      }
    }

    $url = $this->fullUrl($url);
    printf("Testing URL: %s\n", $url);
    curl_setopt($ch, CURLOPT_URL, $url);
  }

  protected function doUrl($url, $method = self::GET, Array $args = array(), Array $headers = array()) {
    $ch = curl_init();
    $this->prepareCurlRequest($ch, $url, $method, $args, $headers);
    $response = curl_exec($ch);
    return new Response($response);
  }

  /**
   * Performs a GET request on given URL, returns response.
   *
   */
  protected function getUrl($url, Array $args = array(), Array $headers = array()) {
    return $this->doUrl($url, self::GET, $args, $headers);
  }

  protected function postUrl($url, Array $args = array(), Array $headers = array()) {
    return $this->doUrl($url, self::POST, $args, $headers);
  }

  protected function headUrl($url, Array $headers = array()) {
    return $this->doUrl($url, self::HEAD, array(), $headers);
  }

  protected function findInputElement(SimpleXMLElement $root, $tagName, $inputName, $message = null, $count = 1) {
    if ($message == null) {
      $message = sprintf("Cannot find <%s name=\"%s\">.", $tagName, ($inputName == null) ? "*" : $inputName);
    }
    if ($inputName != null) {
      $xpath = sprintf('//html:%s[@name="%s"]', $tagName, $inputName);
    }
    else {
      $xpath = sprintf('//html:%s', $tagName);
    }
    $this->autoregisterXpathNamespace($root);
    $inputs = $root->xpath($xpath);
    $this->assertEquals($count, count($inputs), $message);
  }

  protected function autoregisterXpathNamespace(SimpleXMLElement $element, $prefix = 'html') {
    $namespaces = $element->getNamespaces();
    $element->registerXPathNamespace($prefix, array_shift($namespaces));
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
    $actualStatus = $head->getStatus();
    if ($message === null && self::$session_id !== null) {
      $message = sprintf(
        "Expected status \"%s\" but got \"%s\" instead. (Session=[%s])",
        $status,
        $actualStatus,
        TSSessionHandler::read(self::$session_id)
      );
    }
    $this->assertEquals($status,  $actualStatus, $message);
  }
}
?>