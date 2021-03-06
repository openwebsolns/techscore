<?php
/**
 * Session management tools through static interfaces
 *
 * @author Dayan Paez
 * @created 2011-06-02
 */

/**
 * Simple announcement object renders itself to HTML as a list
 * item. This class requires HtmlLib in order to use its toHTML
 * method.
 *
 * @author Dayan Paez
 * @version 2010-10-28
 */
class PA {
  const S = "success";
  const E = "error";
  const I = "warn";
  const Q = "rabbit";

  private $c;
  private $t;
  public function __construct($string, $type = PA::S) {
    $this->c = $string;
    $this->t = $type;
  }
  public function getMessage() {
    return $this->c;
  }
  public function getType() {
    return $this->t;
  }
  /**
   * Serialize the announcement as a list item, with an image
   *
   */
  public function toHTML($img_path = '/inc/img') {
    $li = new XLi(array(), array("class"=>$this->t));
    switch ($this->t) {
    case self::S:
      $li->add(new XImg($img_path . '/s.png', ':)'));
      break;
    case self::E:
      $li->add(new XImg($img_path . '/e.png', 'X!'));
      break;
    case self::I:
      $li->add(new XImg($img_path . '/i.png', '??'));
      break;
    case self::Q:
      $li->add(new XImg($img_path . '/r.png', 'WR'));
    }
    if (is_array($this->c)) {
      foreach ($this->c as $c)
        $li->add($c);
    }
    else
      $li->add($this->c);
    return $li;
  }
}

/**
 * Manages the session data from a static namespaced class. Make
 * certain, please, that the session has already started.
 *
 * @author Dayan Paez
 * @version 2010-10-13
 */
class Session {

  const KEY_ANNOUNCE = 'announce';
  const KEY_DATA = 'data';

  // User-saved variables
  public static $DATA = array();

  // ------------------------------------------------------------
  // Announcement capabilities
  // ------------------------------------------------------------
  private static $announcements = array();

  /**
   * Queue the given announcement
   *
   * @param PA $pa the announcement to queue
   */
  public static function pa(PA $pa) {
    self::$announcements[] = $pa;
  }

  public static function info($message) {
    self::pa(new PA($message, PA::S));
  }

  public static function warn($message) {
    self::pa(new PA($message, PA::I));
  }

  public static function error($message) {
    self::pa(new PA($message, PA::E));
  }

  /**
   * Returns the announcements as a list
   *
   */
  public static function getAnnouncements($img_path = '/inc/img') {
    if (count(self::$announcements) == 0)
      return "";
    $ul = new XUL(array("id"=>"announcements"));
    while (count(self::$announcements) > 0) {
      $a = array_shift(self::$announcements);
      $ul->add($a->toHTML($img_path));
    }
    return $ul;
  }

  /**
   * Initializes the session class from the session object, opening a
   * session if one not already opened.
   *
   */
  public static function init() {
    if (session_id() == "") {
      session_set_cookie_params(0, WS::link('/'), Conf::$HOME, Conf::$SECURE_COOKIE, true);
      if (!session_start()) {
        trigger_error("Unable to start session from Session class.");
      }
    }

    // register commit()
    register_shutdown_function(array("Session", "commit"));

    if (array_key_exists(self::KEY_ANNOUNCE, $_SESSION)) {
      self::$announcements = unserialize($_SESSION[self::KEY_ANNOUNCE]);
    }

    // other parameters
    if (array_key_exists(self::KEY_DATA, $_SESSION)) {
      self::$DATA = unserialize($_SESSION[self::KEY_DATA]);
    }
  }

  /**
   * Call this method to actually send the information back to the
   * session. Note that this does NOT call session_write_close
   *
   */
  public static function commit() {
    // commit announcements
    $_SESSION[self::KEY_ANNOUNCE] = serialize(self::$announcements);

    // commit data
    $_SESSION[self::KEY_DATA] = serialize(self::$DATA);
  }

  /**
   * Sets the following variable to the session (will be committed at
   * the end of the script)
   *
   * @param String $key the key to set
   * @param mixed $value the value
   */
  public static function s($key, $value = null) {
    self::$DATA[$key] = $value;
  }

  /**
   * Returns the value for the given key, if one exists
   *
   * @param String $key the key
   * @return mixed the value, or null
   */
  public static function g($key, $default = null) {
    if (!self::has($key))
      return $default;
    return self::$DATA[$key];
  }

  /**
   * Removes the given value, whether or not it exists.
   */
  public static function d($key) {
    unset(self::$DATA[$key]);
  }

  public static function has($key) {
    return isset(self::$DATA[$key]);
  }

  /**
   * Return session-wide CSRF token
   *
   * Calculates a deterministic token based on the session ID, which
   * must exist.
   *
   * @return String
   * @throws RuntimeException if session does not exist
   */
  public static function getCsrfToken() {
    $id = session_id();
    if ($id == "")
      throw new RuntimeException("Session must be started, before CSRF token can be generated.");

    return hash_hmac('sha256', 'CSRFToken' . self::$CSRF_SALT, $id);
  }

  /**
   * @var String the random salt to use when generating CSTF tokens.
   */
  public static $CSRF_SALT = '849b0e2e595689b413963f52f9ab0f0b';
}
?>