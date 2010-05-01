<?php
/**
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */
require_once('conf.php');

/**
 * Encapsulates and manages RPs for a regatta
 *
 */
class RpManager {

  // Private variables
  private $regatta;
  private static $con;

  /**
   * Instantiate a new rotation object
   *
   * @param Regatta $reg a regatta object
   */
  public function __construct(Regatta $reg) {
    $this->regatta = $reg;
  }

  /**
   * Returns the representative sailor for the given school
   *
   * @param School $school the school whose representative to get
   * @return Sailor the sailor, or null if none
   */
  public function getRepresentative(Team $team) {
    $q = sprintf('select %s from %s ' .
		 'inner join representative on (representative.sailor = sailor.id) ' .
		 'where representative.team = "%s"',
		 Sailor::FIELDS, Sailor::TABLES, $team->id);
    $q = $this->regatta->query($q);
    if ($q->num_rows == 0)
      return null;

    $q = $q->fetch_object("Sailor");
    $q->school = $team->school;
    return $q;
  }

  /**
   * Sets the representative for the given team
   *
   * @param Team $team the team
   * @param Sailor $sailor the sailor
   */
  public function setRepresentative(Team $team, Sailor $sailor) {
    $q = sprintf('replace into representative values ("%s", "%s")',
		 $team->id, $sailor->id);
    $this->regatta->query($q);
  }

  /**
   * Gets a list of sailors with the given role in the given team in
   * the specified division
   *
   * @param Team $team the team
   * @param Division $div the division
   * @param string $role the role (one of the sailor constants)
   * @return Array<RP> $rp a list of races, the sailor object, the
   * team, and the boat role
   */
  public function getRP(Team $team, Division $div, $role) {
    $role = RP::parseRole($role);
    $q = sprintf('select rp.sailor, ' .
		 'group_concat(race_num.number ' .
		 '             order by race_num.number ' .
		 '             separator ",") as races_nums ' .
		 'from race ' .
		 'inner join race_num using (id) ' .
		 'inner join rp on (race.id = rp.race) ' .
		 'where rp.team       = "%s" ' .
		 '  and race.division = "%s" ' .
		 '  and rp.boat_role  = "%s" ' .
		 'group by rp.sailor ' .
		 'order by races_nums',
		 $team->id, $div, $role);
    $q = $this->regatta->query($q);
    $list = array();
    while ($obj = $q->fetch_object("RP")) {
      $list[] = $obj;

      // Fix properties
      $obj->division = $div;
      $obj->team = $team;
      $obj->boat_role = $role;
      $obj->sailor = $this->getSailor($obj->sailor);
      $obj->sailor->school = $team->school;
    }
    return $list;
  }

  /**
   * Registers the RP info with the database. Takes care of only
   * registering for valid (existing) races regardless of races_nums
   *
   * @param RP $rp the RP to register
   */
  public function setRP(RP $rp) {
    foreach ($rp->races_nums as $num) {
      try {
	$race = $this->regatta->getRace($rp->division, $num);
	$q = sprintf('replace into rp values ("%s", "%s", "%s", "%s")',
		     $race->id,
		     $rp->sailor->id,
		     $rp->team->id,
		     $rp->boat_role);
	$this->regatta->query($q);
      } catch (Exception $e) {}
    }
  }

  // Static variable and functions

  /**
   * Sends a query to the database connection and returns the result
   * object.
   *
   * @param string $query the query to send to the database
   * @return the mysqli_result object
   * @throws BadFunctionCallException if there was an error with the
   * query
   */
  private static function query($query) {
    if (self::$con == null) {
      self::$con = new mysqli(SQL_HOST,
			      SQL_USER,
			      SQL_PASS,
			      SQL_DB);
    }
    if ($q = self::$con->query($query)) {
      return $q;
    }
    throw new BadFunctionCallException($q->error . ":" . $query);
  }

  /**
   * Returns a list of coaches as sailor objects for the specified
   * school
   *
   * @param School $school the school object
   * @return Array<Sailor> list of coaches
   */
  public static function getCoaches(School $school) {
    $q = sprintf('select %s from %s where school = "%s" ' .
		 'and role = "coach" ' .
		 'order by last_name',
		 Sailor::FIELDS, Sailor::TABLES, $school->id);
    $q = self::query($q);
    $list = array();
    while ($obj = $q->fetch_object("Sailor")) {
      $obj->school = $school;
      $list[] = $obj;
    }
    return $list;
  }

  /**
   * Returns a list of sailors for the specified school
   *
   * @param School $school the school object
   * @return Array<Sailor> list of sailors
   */
  public static function getSailors(School $school) {
    $q = sprintf('select %s from %s ' .
		 'where school = "%s" ' .
		 'and role = "student" ' .
		 'and icsa_id is not null ' .
		 'order by last_name',
		 Sailor::FIELDS, Sailor::TABLES, $school->id);
    $q = self::query($q);
    $list = array();
    while ($obj = $q->fetch_object("Sailor")) {
      $obj->school = $school;
      $list[] = $obj;
    }
    return $list;
  }

  /**
   * Returns a list of unregistered sailors for the specified school
   *
   * @param School $school the school object
   * @return Array<Sailor> list of sailors
   */
  public static function getUnregisteredSailors(School $school) {
    $q = sprintf('select %s from %s where school = "%s" ' .
		 'and role = "student" ' .
		 'and icsa_id is null ' .
		 'order by last_name',
		 Sailor::FIELDS, Sailor::TABLES, $school->id);
    $q = self::query($q);
    $list = array();
    while ($obj = $q->fetch_object("Sailor")) {
      $obj->school = $school;
      $list[] = $obj;
    }
    return $list;
  }

  /**
   * Replaces every instance of the temporary sailor id with the
   * current sailor id in the RP forms and the database
   *
   * @param Sailor $key the temporary sailor to replace
   * @param Sailor $replace the replacement sailor
   */
  public static function replaceTempActual(Sailor $key, Sailor $replace) {
    $q = sprintf('update rp set sailor = "%s" where sailor = "%s"',
		 $replace->id, $key->id);
    self::query($q);

    // Delete if temporary sailor
    $success = empty(self::$con->error);
    if ($success && !$key->registered) {
      $q = sprintf('delete from sailor where id = "%s"', $key->id);
      self::query($q);
      return empty(self::$con->error);
    }
    return $success;
  }

  /**
   * Deletes the RP information for this team
   *
   * @param Team $team the team
   */
  public function reset(Team $team) {
    $q1 = sprintf('delete from rp where team = "%s"', $team->id);
    $q2 = sprintf('delete from representative where team = "%s"',
		  $team->id);
    $this->regatta->query($q1);
    $this->regatta->query($q2);
  }

  /**
   * Fetches the Sailor with the given ID
   *
   * @param int id the ID of the person
   * @return Sailor the sailor
   */
  private function getSailor($id) {
    $q = sprintf('select %s from %s where id = "%s"',
		 Sailor::FIELDS, Sailor::TABLES, (int)$id);
    $q = $this->regatta->query($q);
    if ($q->num_rows == 0)
      throw InvalidArgumentException(sprintf("No sailor with id (%s).", $id));
    return $q->fetch_object("Sailor");
  }
}
?>