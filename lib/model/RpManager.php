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
  public function __construct(FullRegatta $reg) {
    $this->regatta = $reg;
  }

  /**
   * Returns the representative sailor for the given school
   *
   * @param School $school the school whose representative to get
   * @return Representative the rep, or null if none
   */
  public function getRepresentative(Team $team) {
    $res = DB::getAll(DB::T(DB::REPRESENTATIVE), new DBCond('team', $team));
    return (count($res) == 0) ? null : $res[0];
  }

  /**
   * Sets the representative for the given team
   *
   * If name is null, remove representative instead
   *
   * @param Team $team the team
   * @param String|null $name the name of the representative
   */
  public function setRepresentative(Team $team, $name = null) {
    if ($name === null) {
      DB::removeAll(DB::T(DB::REPRESENTATIVE), new DBCond('team', $team));
      return;
    }

    // Ensure uniqueness
    $cur = $this->getRepresentative($team);
    if ($cur === null) {
      $cur = new Representative();
      $cur->team = $team;
    }
    $cur->name = $name;
    DB::set($cur);
  }

  /**
   * Get list of individual RPEntry matching given conditions.
   *
   * This method is useful to determine if there are any "no show"
   * entries at all in a given regatta. This is different than
   * checking if the regatta isComplete(), because "No show" entries
   * count towards completion.
   *
   * @param Team $team if provided, limit to given team.
   * @param Race $race if provided, limit to given race.
   * @param RP:Const $role if non-null, limit ro role (SKIPPER, CREW)
   * @return Array:RPEntry the list
   */
  public function getNoShowRpEntries(Team $team = null, Race $race = null, $role = null) {
    $cond = new DBBool(array(new DBCond('sailor', null)));
    if ($team === null && $race === null) {
      $cond->add(
        new DBCondIn(
          'race',
          DB::prepGetAll(DB::T(DB::RACE), new DBCond('regatta', $this->regatta), array('id'))
        )
      );
    }
    else {
      if ($team !== null) {
        $cond->add(new DBCond('team', $team));
      }
      if ($race !== null) {
        $cond->add(new DBCond('race', $race));
      }
    }

    if ($role !== null) {
      $cond->add(new DBCond('boat_role', RP::parseRole($role)));
    }
    return DB::getAll(DB::T(DB::RP_ENTRY), $cond);
  }

  /**
   * Fetches single RPEntry for given team-race-role combo
   *
   * @param Team $team the team whose entry to fetch
   * @param Race $race the specific race to fetch
   * @param RP:Const $role the role (SKIPPER, CREW)
   * @return Array:RPEntry objects
   */
  public function getRpEntries(Team $team, Race $race, $role) {
    return DB::getAll(DB::T(DB::RP_ENTRY),
                      new DBBool(array(new DBCond('team', $team),
                                       new DBCond('race', $race),
                                       new DBCond('boat_role', RP::parseRole($role)))));
  }

  /**
   * Sets the individual RP records for given list of sailors
   *
   * For $role of SKIPPER, $sailors should be at most one item. For
   * crews, this can be any number up to the race's boat's maximum
   * number of crews.
   *
   * In the process, this method will REMOVE all entries for the
   * team-race-role combination provided. Thus, a way to reset a
   * particular RP entry would be to provide an empty list for the
   * $sailors argument.
   *
   * @param Team $team the team whose RP entries to remove
   * @param Race $race the race
   * @param Const $role RP::SKIPPER or RP::CREW
   * @param Array:Sailor the sailors to assign, if any
   */
  public function setRpEntries(Team $team, Race $race, $role, Array $sailors = array()) {
    $role = RP::parseRole($role);
    if ($role == RP::SKIPPER && count($sailors) > 1)
      throw new InvalidArgumentException("Only one skipper allowed per boat.");
    if ($role == RP::CREW && count($sailors) > $race->boat->max_crews)
      throw new InvalidArgumentException("Number of crews exceeds capacity for race's boat.");

    DB::removeAll(DB::T(DB::RP_ENTRY),
                  new DBBool(array(new DBCond('team', $team),
                                   new DBCond('race', $race),
                                   new DBCond('boat_role', $role))));
    foreach ($sailors as $sailor) {
      $rp = new RPEntry();
      $rp->team = $team;
      $rp->race = $race;
      $rp->boat_role = $role;
      $rp->sailor = $sailor;
      DB::set($rp);
    }
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
    $res = DB::getAll(DB::T(DB::RP_ENTRY),
                      new DBBool(array(new DBCond('team', $team),
                                       new DBCond('boat_role', RP::parseRole($role)),
                                       new DBCondIn('race', DB::prepGetAll(DB::T(DB::RACE),
                                                                           new DBCond('division', (string)$div),
                                                                           array('id'))))));
    $rps = array();
    foreach ($res as $rpentry) {
      $id = "NULL";
      if ($rpentry->sailor !== null)
        $id = $rpentry->sailor->id;
      if (!isset($rps[$id]))
        $rps[$id] = array();
      $rps[$id][] = $rpentry;
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
   * @param Team $team optional team in the regatta
   * @return boolean true if it has any
   */
  public function hasGender($gender, Team $team = null) {
    $cond = ($team === null) ?
      new DBCondIn('race',
                   DB::prepGetAll(DB::T(DB::RACE), new DBCond('regatta', $this->regatta->id), array('id'))) :
      new DBCond('team', $team);

    $r = DB::getAll(DB::T(DB::RP_ENTRY),
                    new DBBool(array($cond,
                                     new DBCondIn('sailor',
                                                  DB::prepGetAll(DB::T(DB::SAILOR), new DBCond('gender', $gender), array('id'))))));
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
    DB::removeAll(DB::T(DB::RP_ENTRY),
                  new DBBool(array(new DBCondIn('race',
                                                DB::prepGetAll(DB::T(DB::RACE), new DBCond('regatta', $this->regatta->id), array('id'))),
                                   new DBCondIn('sailor',
                                                DB::prepGetAll(DB::T(DB::SAILOR), new DBCond('gender', $gender), array('id'))))));
  }

  /**
   * Determine whether there are races missing skippers
   *
   * This is a convenience method to determine, as quickly as
   * possible, if there is any missing RP information.
   *
   * Will return true if there is at least one race-team pairing that
   * does not have a skipper. Note that, because the number of crews
   * are conditional, these are not accounted.
   *
   * Implementation note: using serialized array from regatta object
   * is faster than subquery.
   *
   * @return boolean true if there is at least one race-team pairing
   */
  public function isMissingSkipper() {
    $races = $this->regatta->getRaces();
    $res = DB::getAll(DB::T(DB::RP_ENTRY),
                      new DBBool(array(new DBCond('boat_role', RP::SKIPPER),
                                       new DBCondIn('race', $races))));
    return count($res) < (count($races) * count($this->regatta->getTeams()));
  }

  /**
   * Is every scored race-team-role combination accounted for?
   *
   * @param Team $team optional team to check
   * @return boolean true if all information is present
   */
  public function isComplete(Team $team = null) {
    if ($team === null)
      $races = $this->regatta->getScoredRaces();
    else {
      $races = array();
      foreach ($this->regatta->getDivisions() as $div) {
        foreach ($this->regatta->getScoredRacesForTeam($div, $team) as $race)
          $races[] = $race;
      }
    }
    if (count($races) == 0)
      return true;

    $sum = 0;
    foreach ($races as $race)
      $sum += $race->boat->min_crews + 1;
    if ($team === null) {
      if ($this->regatta->scoring == Regatta::SCORING_TEAM)
        $sum *= 2;
      else
        $sum *= count($this->regatta->getTeams());
    }

    $cond = new DBCondIn('race', $races);
    if ($team !== null)
      $cond = new DBBool(array(new DBCond('team', $team), $cond));

    $tot = DB::getAll(DB::T(DB::RP_ENTRY), $cond);
    return (count($tot) >= $sum);
  }

  public function setCacheComplete(Team $team) {
    $val = null;
    if ($this->isComplete($team))
      $val = 1;
    if ($val != $team->dt_complete_rp) {
      $team->dt_complete_rp = $val;
      DB::set($team);
    }
  }

  /**
   * Convenience method returns the maximum number of crews
   *
   * Considers all the boats used in all the races for this regatta
   */
  public function getMaximumCrewsAllowed() {
    $max = 0;
    foreach ($this->regatta->getBoats() as $boat) {
      if ($boat->max_crews > $max)
        $max = $boat->max_crews;
    }
    return $max;
  }

  // Static variable and functions

  /**
   * Replaces every instance of the temporary sailor id with the
   * current sailor id in the RP forms and the database
   *
   * @param Sailor $key the temporary sailor to replace
   * @param Sailor $replace the replacement sailor
   * @param boolean $queueUpdate (default:true) to queue regatta update
   * @param Array $affected will fill this list with map of affected
   *   regattas, indexed by ID.
   */
  public static function replaceTempActual(Sailor $key, Sailor $replace, $queueUpdate = true, Array &$affected = array()) {
    foreach ($key->getRegattas() as $reg) {
      if ($queueUpdate) {
        require_once('public/UpdateManager.php');
        UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_RP, $key->school);
      }
      $affected[$reg->id] = $reg;
    }

    $q = DB::createQuery(DBQuery::UPDATE);
    $q->values(array('sailor'), array(DBQuery::A_STR), array($replace->id), DB::T(DB::RP_ENTRY)->db_name());
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
    DB::removeAll(DB::T(DB::RP_ENTRY), new DBCond('team', $team));
    DB::removeAll(DB::T(DB::REPRESENTATIVE), new DBCond('team', $team));
    if ($team->dt_complete_rp !== null) {
      $team->dt_complete_rp = null;
      DB::set($team);
    }
  }

  // RP form functions

  /**
   * Returns the RP form data if there exists a current RP form in the
   * database for this manager's regatta, false otherwise
   *
   * @return String|false the data, or <pre>false</pre> otherwise
   */
  public function getForm() {
    $r = DB::get(DB::T(DB::RP_FORM), $this->regatta->id);
    if ($r === null)
      return false;
    return $r->filedata;
  }

  /**
   * Sets the RP form (PDF) for the given regatta
   *
   * @param mixed $data the file contents
   */
  public function setForm($data) {
    $r = new RP_Form();
    $r->id = $this->regatta->id;
    $r->created_at = DB::T(DB::NOW);
    $r->filedata = $data;
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
    $r = DB::get(DB::T(DB::RP_FORM), $this->regatta->id);
    if ($r === null)
      return false;

    $l = DB::getAll(DB::T(DB::RP_LOG), new DBCond('regatta', $this->regatta->id));
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
    $sailor->active = 1;
    $sailor->regatta_added = $this->regatta->id;
    DB::set($sailor);
  }

  /**
   * Removes the given sailor from the regatta. Yes, this will delete
   * any RPs for that sailor; however only temporary sailors are
   * removed, and only those that were added in this regatta. Anything
   * else will silently fail.
   *
   * @param Sailor $temp the temporary sailor
   * @return boolean remove succeeded
   */
  public function removeTempSailor(Sailor $sailor) {
    if ($sailor->icsa_id === null &&
        $sailor->regatta_added == $this->regatta->id &&
        !$this->isParticipating($sailor)) {
      DB::remove($sailor);
      return true;
    }
    return false;
  }

  /**
   * Gets all the temporary sailors that have been added to this regatta
   *
   * @return Array:Sailor temporary sailor list
   */
  public function getAddedSailors() {
    return DB::getAll(DB::T(DB::SAILOR), new DBCond('regatta_added', $this->regatta->id));
  }

  /**
   * Returns whether the sailor is participating in this regatta
   *
   * @param Sailor $sailor the sailor
   * @deprecated 2011-05-11 use getTeam instead
   * @return boolean true if the sailor is participating (has RP)
   */
  public function isParticipating(Sailor $sailor) {
    $res = DB::getAll(DB::T(DB::RP_ENTRY),
                      new DBBool(array(new DBCond('sailor', $sailor),
                                       new DBCondIn('race', DB::prepGetAll(DB::T(DB::RACE), new DBCond('regatta', $this->regatta->id), array('id'))))));
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
  public function getParticipation(Member $sailor, $role = null, Division $div = null) {
    // Since RP objects all have the same role and division, we
    // create lists of roles and divisions
    $roles = ($role === null) ? array_keys(RP::getRoles()) : array($role);
    $divs  = ($div === null)  ? $this->regatta->getDivisions() : array($div);

    $rps = array();
    foreach ($roles as $role) {
      foreach ($divs as $div) {
        $c = new DBBool(array(new DBCond('sailor', $sailor),
                              new DBCond('boat_role', $role),
                              new DBCondIn('race',
                                           DB::prepGetAll(DB::T(DB::RACE),
                                                          new DBBool(array(new DBCond('regatta', $this->regatta->id),
                                                                           new DBCond('division', (string)$div))),
                                                          array('id')))));
        $res = DB::getAll(DB::T(DB::RP_ENTRY), $c);
        if (count($res) > 0)
          $rps[] = new RP($res);
      }
    }
    return $rps;
  }

  /**
   * Returns the list of RP entries for the given sailor in this regatta
   *
   * @param Sailor $sailor the sailor
   * @param const|null $role 'skipper', 'crew', or null for either
   * @param Division $div the division, if any, to narrow down to.
   * @return Array:RPEntry the teams
   */
  public function getParticipationEntries(Sailor $sailor, $role = null, Division $div = null) {
    // Since RP objects all have the same role and division, we
    // create lists of roles and divisions
    $roles = ($role === null) ? array_keys(RP::getRoles()) : array($role);
    $divs  = ($div === null)  ? $this->regatta->getDivisions() : array($div);

    $r = new DBCond('regatta', $this->regatta->id);
    if ($div !== null)
      $r = new DBBool(array($r, new DBCond('division', (string)$div)));

    $c = new DBBool(array(new DBCond('sailor', $sailor)));
    if ($role !== null)
      $c->add(new DBCond('boat_role', $role));
    $c->add(new DBCondIn('race', DB::prepGetAll(DB::T(DB::RACE), $r, array('id'))));

    return DB::getAll(DB::T(DB::RP_ENTRY), $c);
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

    $cond = new DBCondIn('id', DB::prepGetAll(DB::T(DB::RP_ENTRY), $cond, array('race')));
    if ($div !== null)
      $cond = new DBBool(array($cond, new DBCond('division', (string)$div)));

    return DB::getAll(DB::T(DB::REGATTA), new DBCondIn('id', DB::prepGetAll(DB::T(DB::RACE), $cond, array('regatta'))));
  }

  /**
   * Inactivates all the sailors with the given role in the
   * database. This is useful when syncing from a membership feed.
   *
   * @param Sailor::Const the role
   */
  public static function inactivateRole($role) {
    $q = DB::createQuery(DBQuery::UPDATE);
    $q->values(array('active'), array(DBQuery::A_STR), array(null), DB::T(DB::SAILOR)->db_name());
    $q->where(new DBCond('role', $role));
    DB::query($q);
  }
}
