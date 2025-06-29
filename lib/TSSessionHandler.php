<?php
/**
 * Static class provides namespacing for custom session handler
 *
 * Session information is stored in the database
 *
 * @author Dayan Paez
 * @created 2013-10-29
 */
class TSSessionHandler {

  /**
   * @const int the number of seconds after which an idle session is
   * terminated by the garbage collector
   */
  const IDLE_TIME = 3600;
  /**
   * @const int the number of seconds after which a session is
   * terminated, regardless of idle time
   */
  const MAX_LIFETIME = 7200; // 4 hours

  public static function close() {
    return true;
  }

  public static function destroy($session_id) {
    DB::removeAll(DB::T(DB::WEBSESSION), new DBCond('id', $session_id));
    DB::commit();
    return true;
  }

  public static function gc($maxlifetime) {
    $t1 = new DateTime(sprintf('%d seconds ago', self::IDLE_TIME));
    DB::removeAll(DB::T(DB::WEBSESSION),
                  new DBBool(array(new DBCond('expires', null),
                                   new DBCond('last_modified', $t1, DBCond::LT))));
    DB::removeAll(DB::T(DB::WEBSESSION), new DBCond('expires', DB::T(DB::NOW), DBCond::LT));
    return true;
  }

  public static function open($save_path, $name) {
    return true;
  }

  public static function read($session_id) {
    $s = DB::get(DB::T(DB::WEBSESSION), $session_id);
    if ($s === null)
      return '';
    if (self::isExpired($s))
      return '';
    return $s->sessiondata;
  }

  public static function write($session_id, $session_data) {
    $s = DB::get(DB::T(DB::WEBSESSION), $session_id);
    $update = true;
    if ($s === null) {
      $s = new Websession();
      $s->id = $session_id;
      $s->created = DB::T(DB::NOW);
      $update = false;
    }

    if (isset(self::$expires[$session_id]))
      $s->expires = self::$expires[$session_id];

    $s->sessiondata = $session_data;
    $s->last_modified = DB::T(DB::NOW);
    DB::set($s, $update);
    DB::commit();
    return true;
  }

  // ------------------------------------------------------------
  // Extra functionality
  // ------------------------------------------------------------

  public static function register() {
    return session_set_save_handler('TSSessionHandler::open',
                                    'TSSessionHandler::close',
                                    'TSSessionHandler::read',
                                    'TSSessionHandler::write',
                                    'TSSessionHandler::destroy',
                                    'TSSessionHandler::gc');
  }

  public static function setLifetime($lifetime) {
    $t = clone(DB::T(DB::NOW));
    $t->add(new DateInterval(sprintf('P0DT%dS', $lifetime)));
    self::$expires[session_id()] = $t;
  }

  /**
   * Returns the time at which the current session will no longer be
   * considered active.
   *
   * This will be IDLE_TIME from now, or the expiration time if
   * long-lived
   *
   * @return int the number of seconds since Jan 1, 1970
   */
  public static function getExpiration() {
    if (session_id() == "")
      return null;

    $params = session_get_cookie_params();
    if ($params['lifetime'] == 0)
      return time() + self::IDLE_TIME;
    $d = new DateTime($params['lifetime']);
    return $d->format('U');
  }

  private static $expires = array();

  public static function isExpired(Websession $s) {
    if ($s->expires === null) {
      if ($s->last_modified->format('U') < time() - self::IDLE_TIME)
        return true;
    }
    elseif ($s->expires < DB::T(DB::NOW)) {
      return true;
    }
    return false;
  }

  /**
   * Returns list of non-expired sessions
   *
   * @return Array:Websession sessions
   */
  public static function getActive() {
    // Only return entries that have log activity so as to exclude bots
    // and other sessions of non-logged in users.
    return DB::getAll(
      DB::T(DB::WEBSESSION),
      new DBBool(
        array(
          new DBCondIn('id', DB::prepGetAll(DB::T(DB::WEBSESSION_LOG), null, array('websession'))),
          new DBBool(
            array(
              new DBCond('expires', DB::T(DB::NOW), DBCond::GT),
              new DBBool(
                array(
                  new DBCond('expires', null),
                  new DBCond('last_modified', new DateTime(sprintf('%d seconds ago', self::IDLE_TIME)), DBCond::GT))
              )
            ),
            DBBool::mOR
          )
        )
      )
    );
  }

  /**
   * Returns all non-expired long-term sessions for given user
   *
   * @param Account $user optional user
   */
  public static function getLongTermActive(Account $user = null) {
    $cond = new DBCond('expires', DB::T(DB::NOW), DBCond::GT);
    if ($user !== null) {
      $term = sprintf('%%"user";s:%d:"%s"%%', mb_strlen($user->id), $user->id);
      $cond = new DBBool(array(new DBCond('sessiondata', $term, DBCond::LIKE), $cond));
    }
    return DB::getAll(DB::T(DB::WEBSESSION), $cond);
  }
}
?>
