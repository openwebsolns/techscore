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
    $r = (count($res) == 0) ? null : $res[0]->sailor;
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
    $cur = $this->getRepresentative($team);
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
				       new DBCond('boat_role', RP::parseRole($role)),
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
      $lst[] = new RP($lists);
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
    $r->regatta = $this->regatta->id;
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
						  DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->regatta->id), array('id'))),
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
						DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->regatta->id), array('id'))),
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
    $r = DB::get(DB::$RP_FORM, $this->regatta->id);
    if ($r === null)
      return false;
    return base64_decode($r->filedata);
  }

  /**
   * Sets the RP form (PDF) for the given regatta
   *
   * @param mixed $data the file contents
   */
  public function setForm($data) {
    $r = new RP_Form();
    $r->id = $this->regatta->id;
    $r->created_at = DB::$NOW;
    $r->filedata = base64_encode($data);
    DB::set($r);
  }

  /**
   * Determines whether there is an RP form in the database and
   * whether it is up to date.
   *
   * @return boolean true if there exists a form for this regatta and
   * it has a timestamp later than the update timestamp on the RP
   */
  public function isFormRecent() {
    $r = DB::get(DB::$RP_FORM, $this->regatta->id);
    if ($r === null)
      return false;

    $l = DB::getAll(DB::$RP_LOG, new DBCond('regatta', $this->regatta->id));
    if (count($l) == 0)
      return true;
    return ($l[0]->updated_at < $r->created_at);
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
    // make sure it's temp and NEW!
    $sailor->id = null;
    $sailor->icsa_id = null;
    DB::set($sailor);
  }

  /**
   * Removes the given sailor from the regatta. Yes, this will delete
   * any RPs for that sailor; however only temporary sailors are
   * removed, and only those that were added in this regatta. Anything
   * else will silently fail.
   *
   * @param Sailor $temp the temporary sailor
   */
  public function removeTempSailor(Sailor $sailor) {
    if ($sailor->icsa_id === null && $sailor->regatta_added == $this->regatta->id)
      DB::remove($sailor);
  }

  /**
   * Gets all the temporary sailors that have been added to this regatta
   *
   * @return Array:Sailor temporary sailor list
   */
  public function getAddedSailors() {
    return DB::getAll(DB::$SAILOR, new DBCond('regatta_added', $this->regatta->id));
  }

  /**
   * Returns whether the sailor is participating in this regatta
   *
   * @param Sailor $sailor the sailor
   * @deprecated 2011-05-11 use getTeam instead
   * @return boolean true if the sailor is participating (has RP)
   */
  public function isParticipating(Sailor $sailor) {
    $res = DB::getAll(DB::$RP_ENTRY,
		      new DBBool(array(new DBCond('sailor', $sailor),
				       new DBCondIn('race', DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->regatta->id), array('id'))))));
    $part = count($res) > 0;
    unset($res);
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
    // Since RP objects all have the same role and division, we
    // create lists of roles and divisions
    $roles = ($role === null) ? array_keys(RP::getRoles()) : array($role);
    $divs  = ($role === null) ? $this->regatta->getDivisions() : array($div);
    
    $rps = array();
    foreach ($roles as $role) {
      foreach ($divs as $div) {
	$c = new DBBool(array(new DBCond('sailor', $sailor),
			      new DBCond('boat_role', $role),
			      new DBCondIn('race',
					   DB::prepGetAll(DB::$RACE,
							  new DBBool(array(new DBCond('regatta', $this->regatta->id),
									   new DBCond('division', (string)$div))),
							  array('id')))));
	$rps[] = new RP(DB::getAll(DB::$RP_ENTRY, $c));
      }
    }
    return $rps;
  }

  /**
   * Get all the regattas the given sailor has participated in
   *
   * @param Sailor $sailor the sailor
   * @param Const|null $role either SKIPPER or CREW to narrow down
   * @param Division $div specify one to narrow down
   * @return Array:Regatta
   */
  public static function getRegattas(Sailor $sailor, $role = null, Division $div = null) {
    $cond = new DBBool(array(new DBCond('sailor', $sailor)));
    if ($role !== null)
      $cond->add(new DBCond('boat_role', $role));
    
    $cond = new DBCondIn('id', DB::prepGetAll(DB::$RP_ENTRY, $cond, array('race')));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    
    return DB::getAll(DB::$REGATTA_SUMMARY, new DBCondIn('id', DB::prepGetAll(DB::$RACE, $cond, array('regatta'))));
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
  protected function db_order() { return array('updated_at'=>false); }
}

/**
 * Cached copy of RP physical, PDF form
 *
 * @author Dayan Paez
 * @version 2012-01-15
 */
class RP_Form extends DBObject {
  public $filedata;
  protected $created_at;

  public function db_type($field) {
    switch ($field) {
    case 'created_at': return DB::$NOW;
    case 'filedata': return DBQuery::A_BLOB;
    default:
      return parent::db_type($field);
    }
  }
}

DB::$RP_LOG = new RP_Log();
DB::$RP_FORM = new RP_Form();
?>