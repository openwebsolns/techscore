<?php
/*
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
    $res = DB::getAll(DB::$REPRESENTATIVE, new DBCond('team', $team));
    $r = (count($res) == 0) ? null : $res->sailor;
    unset($res);
    return $r;
  }

  /**
   * Sets the representative for the given team
   *
   * @param Team $team the team
   * @param Sailor $sailor the sailor
   */
  public function setRepresentative(Team $team, Sailor $sailor) {
    // Ensure uniqueness
    $cur = $this->getRepresentative($team, $sailor);
    if ($cur === null) {
      $cur = new Representative();
      $cur->team = $team;
    }
    $cur->sailor = $sailor;
    DB::set($cur);
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
    // Get sailors
    $res = DB::getAll(DB::$RP_ENTRY,
		      new DBBool(array(new DBCond('team', $team),
				       new DBCond('boat_role', RP2::parseRole($role)),
				       new DBCondIn('race', DB::prepGetAll(DB::$RACE,
									   new DBCond('division', (string)$div),
									   array('id'))))));
    $rps = array();
    foreach ($res as $rpentry) {
      if (!isset($rps[$rpentry->sailor->id]))
	$rps[$rpentry->sailor->id] = array();
      $rps[$rpentry->sailor->id][] = $rpentry;
    }
    $lst = array();
    foreach ($rps as $lists)
      $lst[] = new RP2($lists);
    return $lst;
  }

  /**
   * Registers the RP info with the database. Insert all the RPEntries
   * in the given list.
   *
   * @param Array:RPEntry $rp the RPEntries to register
   */
  public function setRP(Array $rp) {
    DB::insertAll($rp);
  }

  public function updateLog() {
    $r = new RP_Log();
    $r->regatta = $this->regatta->id();
    DB::set($r);
  }

  /**
   * Returns true if the regatta has gender of the variety given,
   * which should be one of the Sailor gender constants
   *
   * @param Sailor::MALE|FEMALE $gender the gender to check
   * @return boolean true if it has any
   */
  public function hasGender($gender) {
    $r = DB::getAll(DB::$RP_ENTRY,
		    new DBBool(array(new DBCondIn('race',
						  DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->regatta->id()), array('id'))),
				     new DBCondIn('sailor',
						  DB::prepGetAll(DB::$SAILOR, new DBCond('gender', $gender), array('id'))))));
    $res = (count($r) > 0);
    unset($r);
    return $res;
  }

  /**
   * Removes all the RP information for this regatta where the sailor
   * is of the given gender
   *
   * @param Sailor::MALE|FEMALE $gender the gender
   */
  public function removeGender($gender) {
    DB::removeAll(DB::$RP_ENTRY,
		  new DBBool(array(new DBCondIn('race',
						DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->regatta->id()), array('id'))),
				   new DBCondIn('sailor',
						DB::prepGetAll(DB::$SAILOR, new DBCond('gender', $gender), array('id'))))));
  }

  // Static variable and functions

  /**
   * Replaces every instance of the temporary sailor id with the
   * current sailor id in the RP forms and the database
   *
   * @param Sailor $key the temporary sailor to replace
   * @param Sailor $replace the replacement sailor
   */
  public static function replaceTempActual(Sailor $key, Sailor $replace) {
    $q = DB::createQuery(DBQuery::UPDATE);
    $q->values(array('sailor'), array($replace->id), DB::$RP_ENTRY->db_name());
    $q->where(new DBCond('sailor', $key));
    DB::query($q);

    // Delete if temporary sailor
    if (!$key->isRegistered())
      DB::remove($key);
  }

  /**
   * Deletes the RP information for this team
   *
   * @param Team $team the team
   */
  public function reset(Team $team) {
    DB::removeAll(DB::$RP_ENTRY, new DBCond('team', $team));
    DB::removeAll(DB::$REPRESENTATIVE, new DBCond('team', $team));
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

  // ------------------------------------------------------------
  // Temp sailors
  // ------------------------------------------------------------

  /**
   * Registers the new sailor into the temporary database. Updates the
   * sailor object with the database-given ID.
   *
   * @param Sailor $sailor the new sailor to register
   * @param Regatta $reg the regatta in which this temp sailor was added
   */
  public function addTempSailor(Sailor $sailor) {
    $con = DB::connection();
    $q = sprintf('insert into sailor ' .
		 '(school, first_name, last_name, year, gender, regatta_added) values ' .
		 '("%s", "%s", "%s", "%s", "%s", "%s")',
		 $sailor->school->id,
		 $con->real_escape_string($sailor->first_name),
		 $con->real_escape_string($sailor->last_name),
		 $con->real_escape_string($sailor->year),
		 $sailor->gender,
		 $this->regatta->id());
    $this->regatta->query($q);

    // fetch the last ID
    $sailor->id = $con->insert_id;
  }

  /**
   * Removes the given sailor from the regatta. Yes, this will delete
   * any RPs for that sailor; however only temporary sailors are
   * removed, and only those that were added in this regatta. Anything
   * else will silently fail.
   * thrown
   *
   * @param Sailor $temp the temporary sailor
   */
  public function removeTempSailor(Sailor $sailor) {
    $q = sprintf('delete from sailor where id = "%s" and icsa_id is null and regatta_added = "%s"',
		 $sailor->id, $this->regatta->id());
    $this->regatta->query($q);
  }

  /**
   * Gets all the temporary sailors that have been added to this regatta
   *
   * @return Array:Sailor temporary sailor list
   */
  public function getAddedSailors() {
    $q = sprintf('select %s from %s where regatta_added = "%s"',
		 Sailor::FIELDS, Sailor::TABLES, $this->regatta->id());
    $res = $this->regatta->query($q);
    $list = array();
    while ($obj = $res->fetch_object("Sailor"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Returns whether the sailor is participating in this regatta
   *
   * @param Sailor $sailor the sailor
   * @deprecated 2011-05-11 use getTeam instead
   * @return boolean true if the sailor is participating (has RP)
   */
  public function isParticipating(Sailor $sailor) {
    $q = sprintf('select race from rp where sailor = "%s" and race in (select id from race where regatta = "%s")',
		 $sailor->id,
		 $this->regatta->id());
    $q = $this->regatta->query($q);
    $part = ($q->num_rows > 0);
    $q->free();
    return $part;
  }

  /**
   * Returns the list of teams the given sailor is participating in
   * for this regatta.
   *
   * @param Sailor $sailor the sailor
   * @param const|null $role 'skipper', 'crew', or null for either
   * @param Division $div the division, if any, to narrow down to.
   * @return Array:RP the teams
   */
  public function getParticipation(Sailor $sailor, $role = null, Division $div = null) {
    $q = sprintf('select rp.sailor, rp.boat_role, rp.team, race.division, ' .
		 'group_concat(race.number ' .
		 '             order by race.number ' .
		 '             separator ",") as races_nums ' .
		 'from race ' .
		 'inner join rp on (race.id = rp.race) ' .
		 'where race.regatta  = "%s" %s %s ' .
		 '  and rp.sailor = "%s" ' .
		 'group by rp.sailor ' .
		 'order by races_nums',
		 $this->regatta->id(),
		 ($role != null) ? sprintf('and rp.boat_role = "%s"', $role) : '',
		 ($div  != null) ? sprintf('and race.division = "%s"', $div) : '',
		 $sailor->id);

    $q = $this->regatta->query($q);
    $list = array();
    while ($obj = $q->fetch_object("RP")) {
      $list[] = $obj;

      // Fix properties
      $obj->division = Division::get($obj->division);
      $obj->team = $this->regatta->getTeam($obj->team);
      $obj->sailor = DB::getSailor($obj->sailor);
    }
    return $list;
  }

  /**
   * Get all the regattas the given sailor has participated in
   *
   * @param Sailor $sailor the sailor
   * @param Const|null $role either SKIPPER or CREW to narrow down
   * @param Division $div specify one to narrow down
   * @return Array:RegattaSummary
   */
  public static function getRegattas(Sailor $sailor, $role = null, Division $div = null) {
    $where_role = '';
    if ($role !== null)
      $where_role = sprintf('and boat_role="%s"', $role);
    $where_div = '';
    if ($div !== null)
      $where_div = sprintf('division="%s" and ', $div);
    $q = sprintf('select %s from %s where id in ' .
		 ' (select regatta from race where %s id in ' .
		 '  (select race from rp where sailor = "%s" %s))',
		 RegattaSummary::FIELDS, RegattaSummary::TABLES, $where_div, $sailor->id, $where_role);
    $res = Preferences::query($q);
    $list = array();
    while ($obj = $res->fetch_object('RegattaSummary'))
      $list[] = $obj;
    return $list;
  }
}

/**
 * Log of RP changes, for updating to public site
 *
 * @author Dayan Paez
 * @version 2012-01-15
 */
class RP_Log extends DBObject {
  public $regatta;
  protected $updated_at;

  public function db_name() { return 'rp_log'; }
  public function db_type($field) {
    switch ($field) {
    case 'updated_at': return DB::$NOW;
    default:
      return parent::db_type($field);
    }
  }
}
DB::$RP_LOG = new RP_Log();
?>