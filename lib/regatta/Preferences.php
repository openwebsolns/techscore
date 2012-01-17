<?php
/**
 * Manages some of the global preferences needed by certain aspects
 * of the program. For example, the parameters from the database
 * that describe what is permissible, or not...
 *
 * @author Dayan Paez
 * @version 2009-09-29
 * @package regatta
 */

/**
 * Connects to database and provides methods for extracting available
 * parameters.
 *
 * @author Dayan Paez
 * @version 2009-10-04
 */
class Preferences {

  /**
   * Sends the requested query to the database, throwing an Exception
   * if something went wrong.
   *
   * @param String $query the query to send
   * @return MySQLi_Result the result set
   */
  public static function query($query) {
    $con = DB::connection();
    $t = microtime(true);
    if ($q = $con->query($query)) {
      if (Conf::$LOG_QUERIES !== null)
       @error_log(sprintf("(%7.5f) %s\n", microtime(true) - $t, $query), 3, Conf::$LOG_QUERIES);
      return $q;
    }
    throw new BadFunctionCallException($con->error . ": " . $query);
  }

  /**
   * Returns a list of the seasons for which there are public regattas
   *
   * @return Array:Season the list
   */
  public static function getActiveSeasons() {
    $r = self::query('select distinct season from dt_regatta order by start_time desc');
    $l = array();
    while ($i = $r->fetch_object())
      $l[] = Season::parse($i->season);
    return $l;
  }
}
?>
