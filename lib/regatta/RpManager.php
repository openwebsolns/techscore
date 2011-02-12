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
    $q = sprintf('insert into representative values ("%s", "%s") on duplicate key update sailor = "%s"',
		 $team->id, $sailor->id, $sailor->id);
    $this->regatta->query($q);
  }

  /**
   * Gets a list of sailors with the given role in the given team in
   * the specified division
   *
   * @param Team $team the team
   * @param Division $div the division
   * @param string $role the role (one of the sailor constants)
   * @return Array:RP a list of races, the sailor object, the
   * team, and the boat role
   */
  public function getRP(Team $team, Division $div, $role) {
    $role = RP::parseRole($role);
    $q = sprintf('select rp.sailor, ' .
		 'group_concat(race.number ' .
		 '             order by race.number ' .
		 '             separator ",") as races_nums ' .
		 'from race ' .
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
    $txt = array();
    foreach ($rp->races_nums as $num) {
      $race = $this->regatta->getRace($rp->division, $num);
      if ($race !== null)
	$txt[] = sprintf('("%s", "%s", "%s", "%s")',
			 $race->id,
			 $rp->sailor->id,
			 $rp->team->id,
			 $rp->boat_role);
    }
    if (count($txt) == 0)
      return;

    $q = sprintf('insert into rp (race, sailor, team, boat_role) values %s', implode(',', $txt));
    $this->regatta->query($q);
  }

  public function updateLog() {
    $q = sprintf('insert into rp_log (regatta) values (%d)', $this->regatta->id());
    $this->regatta->query($q);
  }

  // Static variable and functions

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
    $q = Preferences::query($q);
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
   * @param RP::const $gender null for both or the gender code
   * @return Array<Sailor> list of sailors
   */
  public static function getSailors(School $school, $gender = null) {
    $g = ($gender === null) ? '' : sprintf('and gender = "%s" ', $gender);
    $q = sprintf('select %s from %s where school = "%s" ' .
		 'and role = "student" ' .
		 'and icsa_id is not null %s' .
		 'order by last_name',
		 Sailor::FIELDS, Sailor::TABLES, $school->id, $g);
    $q = Preferences::query($q);
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
   * @param RP::const $gender null for both or the gender code
   * @return Array<Sailor> list of sailors
   */
  public static function getUnregisteredSailors(School $school, $gender = null) {
    $g = ($gender === null) ? '' : sprintf('and gender = "%s" ', $gender);
    $q = sprintf('select %s from %s where school = "%s" ' .
		 'and role = "student" ' .
		 'and icsa_id is null %s' .
		 'order by last_name',
		 Sailor::FIELDS, Sailor::TABLES, $school->id, $g);
    $q = Preferences::query($q);
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
    Preferences::query($q);

    // Delete if temporary sailor
    if (!$key->registered) {
      $q = sprintf('delete from sailor where id = "%s"', $key->id);
      Preferences::query($q);
    }
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
    // $this->regatta->query($q1);
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

  // RP form functions

  /**
   * Returns the RP form data if there exists a current RP form in the
   * database for this manager's regatta, false otherwise
   *
   * @return String|false the data, or <pre>false</pre> otherwise
   */
  public function getForm() {
    $q = sprintf('select filedata from rp_form where regatta = %d', $this->regatta->id());
    $q = $this->regatta->query($q);
    if ($q->num_rows == 0)
      return false;
    return base64_decode($q->fetch_object()->filedata);
  }

  /**
   * Sets the RP form (PDF) for the given regatta
   *
   * @param mixed $data the file contents
   */
  public function setForm($data) {
    $q = sprintf('insert into rp_form (regatta, filedata) values (%d, "%1$s") on duplicate key update filedata = "%2$s", created_at = "%3$s"',
		 $this->regatta->id(), base64_encode($data), date('Y-m-d H:i:s'));
    $this->regatta->query($q);
  }

  /**
   * Determines whether there is an RP form in the database and
   * whether it is up to date.
   *
   * @return boolean true if there exists a form for this regatta and
   * it has a timestamp later than the update timestamp on the RP
   */
  public function isFormRecent() {
    $q = sprintf('select created_at from rp_form where regatta = %d', $this->regatta->id());
    $q = $this->regatta->query($q);
    if ($q->num_rows == 0)
      return false;
    $c = strtotime($q->fetch_object()->created_at);

    // Get updated timestamp
    $q = sprintf('select updated_at from rp_log where regatta = %d', $this->regatta->id());
    $q = $this->regatta->query($q);
    if ($q->num_rows == 0)
      return true;

    $u = strtotime($q->fetch_object()->updated_at);
    return ($c >= $u);
  }
}
?>