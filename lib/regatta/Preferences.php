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
   * Returns an ordered list of the team names for the given school
   *
   * @param School $school the school whose team names to fetch
   * @return Array ordered list of the school names
   */
  public static function getTeamNames(School $school) {
    $q = sprintf('select name from team_name_prefs where school = "%s" order by rank desc',
		 $school->id);
    $q = self::query($q);
    $list = array();
    while ($obj = $q->fetch_object())
      $list[] = $obj->name;
    return $list;
  }

  /**
   * Sets the team names for the given school
   *
   * @param School $school school whose valid team names to set
   * @param Array $names an ordered list of team names
   */
  public static function setTeamNames(School $school, Array $names) {

    // 1. Remove existing names
    $q = sprintf('delete from team_name_prefs where school = "%s"', $school->id);
    self::query($q);

    // 2. Add new names
    $q = array();
    $rank = count($names);
    foreach ($names as $name) {
      $q[] = sprintf('("%s", "%s", %s)', $school->id, $name, $rank--);
    }
    self::query(sprintf('insert into team_name_prefs values %s', implode(', ', $q)));
  }

  /**
   * Traverses a list and returns the first object with the specified
   * property value for the specified property name, or null otherwise
   *
   * @param Array $array the array of objects
   * @param string $prop_name the property name to check
   * @param mixed  $prop_value the value of the property to check
   * @return the object, or null if not found
   */
  public static function getObjectWithProperty(Array $array,
					       $prop_name,
					       $prop_value) {
    foreach ($array as $obj) {
      if ($obj->$prop_name == $prop_value) {
	return $obj;
      }
    }
    return null;
  }

  /**
   * Returns a list of the years for which there are regattas in the
   * database
   *
   * @return Array:int the list of years, indexed by the years
   */
  public static function getYears() {
    $q = sprintf('select distinct year(start_time) as year from regatta order by year desc');
    $r = self::query($q);
    $l = array();
    while ($i = $r->fetch_object())
      $l[$i->year] = $i->year;
    return $l;
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
