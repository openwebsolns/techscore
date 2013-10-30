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
    DB::removeAll(DB::$WEBSESSION, new DBCond('id', $session_id));
    return true;
  }

  public static function gc($maxlifetime) {
    $t1 = new DateTime(sprintf('%d seconds ago', max($maxlifetime, self::IDLE_TIME)));
    DB::removeAll(DB::$WEBSESSION,
                  new DBBool(array(new DBCond('expires', null),
                                   new DBCond('last_modified', $t1, DBCond::LT))));
    DB::removeAll(DB::$WEBSESSION, new DBCond('expires', DB::$NOW, DBCond::LT));
  }

  public static function open($save_path, $name) {
    return true;
  }

  public static function read($session_id) {
    $s = DB::get(DB::$WEBSESSION, $session_id);
    return ($s === null) ? null : $s->sessiondata;
  }

  public static function write($session_id, $session_data) {
    $s = DB::get(DB::$WEBSESSION, $session_id);
    $update = true;
    if ($s === null) {
      $s = new Websession();
      $s->id = $session_id;
      $s->created = DB::$NOW;
      $update = false;
    }

    if (isset(self::$expires[$session_id]))
      $s->expires = self::$expires[$session_id];

    $s->sessiondata = $session_data;
    $s->last_modified = DB::$NOW;
    DB::set($s, $update);
    DB::commit();
    return true;
  }

  public static function register() {
    return session_set_save_handler('TSSessionHandler::open',
                                    'TSSessionHandler::close',
                                    'TSSessionHandler::read',
                                    'TSSessionHandler::write',
                                    'TSSessionHandler::destroy',
                                    'TSSessionHandler::gc');
  }

  public static function setLifetime($lifetime) {
    $t = clone(DB::$NOW);
    $t->add(new DateInterval(sprintf('P0DT%dS', $lifetime)));
    self::$expires[session_id()] = $t;
  }

  private static $expires = array();
}
?>