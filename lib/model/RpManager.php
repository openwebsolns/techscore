<?php
/**
 * Encapsulates and manages RPs for a regatta
 *
 * 2015-03-02: Also handle attendees. Attendees are the list of
 * sailors which are present at a given regatta from a given
 * school. Sailors that end up in the RP form are called participants;
 * the rest are labeled reserves.
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
    $cond = new DBBool(array(new DBCond('attendee', null)));
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
   * Sets the individual RP records for given list of attendees.
   *
   * For $role of SKIPPER, $attendees should be at most one item. For
   * crews, this can be any number up to the race's boat's maximum
   * number of crews.
   *
   * In the process, this method will REMOVE all entries for the
   * team-race-role combination provided. Thus, a way to reset a
   * particular RP entry would be to provide an empty list for the
   * $attendees argument.
   *
   * @param Team $team the team whose RP entries to remove
   * @param Race $race the race
   * @param Const $role RP::SKIPPER or RP::CREW
   * @param Array:Attendee the attendees to assign, if any
   */
  public function setRpEntries(Team $team, Race $race, $role, Array $attendees = array()) {
    $role = RP::parseRole($role);
    if ($role == RP::SKIPPER && count($attendees) > 1)
      throw new InvalidArgumentException("Only one skipper allowed per boat.");
    if ($role == RP::CREW && count($attendees) > $race->boat->max_crews)
      throw new InvalidArgumentException("Number of crews exceeds capacity for race's boat.");

    DB::removeAll(DB::T(DB::RP_ENTRY),
                  new DBBool(array(new DBCond('team', $team),
                                   new DBCond('race', $race),
                                   new DBCond('boat_role', $role))));
    foreach ($attendees as $attendee) {
      $rp = new RPEntry();
      $rp->team = $team;
      $rp->race = $race;
      $rp->boat_role = $role;
      $rp->attendee = $attendee;
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
      if ($rpentry->attendee !== null)
        $id = $rpentry->attendee->sailor->id;
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

    $r = DB::getAll(
      DB::T(DB::RP_ENTRY),
      new DBBool(
        array(
          $cond,
          new DBCondIn(
            'attendee',
            DB::prepGetAll(
              DB::T(DB::ATTENDEE),
              new DBCondIn(
                'sailor',
                DB::prepGetAll(
                  DB::T(DB::SAILOR),
                  new DBCond('gender', $gender),
                  array('id')
                )
              ),
              array('id')
            )
          )
        )
      )
    );

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

  public function isCompleteForTeam(Team $team) {
    foreach ($this->regatta->getDivisions() as $division) {
      foreach ($this->regatta->getScoredRacesForTeam($division, $team) as $race) {
        if (count($this->getRpEntries($team, $race, RP::SKIPPER)) == 0) {
          return false;
        }
        if (count($this->getRpEntries($team, $race, RP::CREW)) < $race->boat->min_crews) {
          return false;
        }
      }
    }
    return true;
  }

  /**
   * Recalculates and stores the RP completed cache for given team.
   *
   * @param Team $team the team whose RP status to recalculate.
   * @return boolean true if RP is complete.
   */
  public function resetCacheComplete(Team $team) {
    $val = null;
    if ($this->isCompleteForTeam($team)) {
      $val = 1;
    }
    if ($val != $team->dt_complete_rp) {
      $team->dt_complete_rp = $val;
      DB::set($team);
    }
    return ($val !== null);
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

  // ------------------------------------------------------------
  // Attendees functionality
  // ------------------------------------------------------------

  /**
   * Gets the attendees for this regatta.
   *
   * @param Team $team the team to search.
   * @return Array:Attendee the list of attendees.
   */
  public function getAttendees(Team $team) {
    return DB::getAll(DB::T(DB::ATTENDEE), new DBCond('team', $team));
  }

  /**
   * Set the list of attendees for the given team.
   *
   * @param Team $team the team in question.
   * @param Array $sailors the sailors to register.
   */
  public function setAttendees(Team $team, Array $sailors) {
    // Avoid deleting entries due to cascading nature of foreign
    // keys. Instead, compare the new list (newones) with the current
    // ones and add/remove accordingly.
    //
    // An interesting race condition is possible where the database
    // list of attendees changes from the time that it is fetched to
    // when the new list is committed. Of all the methods to fix the
    // situation, the only surefire one seems to be to leverage
    // MariaDB's "INSERT IGNORE" functionality, which will simply
    // ignore a duplicate unique key entry if attempted.
    //
    // We rely heavily on this functionality, exposed as a second
    // argument to the insertAll() method.

    $newones = array();
    foreach ($sailors as $sailor) {
      if (!($sailor instanceof Sailor)) {
        throw new InvalidArgumentException(
          sprintf("Expected list of sailors; found %s.", gettype($sailor)));
      }
      $newones[$sailor->id] = $this->prepareAttendee($team, $sailor);
    }

    foreach ($this->getAttendees($team) as $attendee) {
      if (!array_key_exists($attendee->sailor->id, $newones)) {
        DB::remove($attendee);
      }
    }

    DB::insertAll($newones, true);
  }

  /**
   * Adds the given individual attendee, if not already registered.
   *
   * @param Team $team the team to add.
   * @param Sailor $sailor the sailor in attendance.
   * @return boolean true if added; false if already present.
   */
  public function addAttendee(Team $team, Sailor $sailor) {
    if (!$this->isAttending($sailor, $team)) {
      DB::set($this->prepareAttendee($team, $sailor));
      return true;
    }
    return false;
  }

  /**
   * Get the attendee entries for given sailor.
   *
   * @param Sailor $sailor the sailor whose attendance to fetch.
   * @param Team $team the optional team to limit attendance to.
   * @return Array:Attendee should be only one per team.
   */
  public function getAttendance(Sailor $sailor, Team $team = null) {
    $cond = new DBBool(array(new DBCond('sailor', $sailor)));
    if ($team !== null) {
      $cond->add(new DBCond('team', $team));
    }
    else {
      $cond->add(
        new DBCondIn(
          'team',
          DB::prepGetAll(
            DB::T(DB::TEAM),
            new DBCond('regatta', $this->regatta),
            array('id')
          )
        )
      );
    }
    return DB::getAll(DB::T(DB::ATTENDEE), $cond);
  }

  /**
   * Determines whether given sailor is attending this regatta.
   *
   * @param Sailor $sailor the sailor in question.
   * @param Team $team the optional team to limit search to.
   * @return boolean true if attending.
   */
  public function isAttending(Sailor $sailor, Team $team = null) {
    $res = $this->getAttendance($sailor, $team);
    return count($res) > 0;
  }

  /**
   * Helper method to create and setup the Attendee object.
   *
   */
  private function prepareAttendee(Team $team, Sailor $sailor) {
    $attendee = new Attendee();
    $attendee->team = $team;
    $attendee->sailor = $sailor;
    $attendee->added_by = Conf::$USER;
    $attendee->added_on = DB::T(DB::NOW);
    return $attendee;
  }

  /**
   * Replace given sailor with given replacement.
   *
   * @param Sailor $original the sailor to replace.
   * @param Sailor $replacement the sailor with which to replace.
   * @return int number of replacements made.
   */
  public function replaceSailor(Sailor $original, Sailor $replacement) {
    $originalAttendance = $this->getAttendance($original);

    foreach ($originalAttendance as $attendee) {
      $replacementAttendance = $this->getAttendance($replacement, $attendee->team);
      if (count($replacementAttendance) > 0) {
        // If replacement is already attending for this team, transfer
        // there should only be one, use first
        $replacementAttendee = $replacementAttendance[0];
        $q = DB::createQuery(DBQuery::UPDATE);
        $q->values(
          array('attendee'),
          array(DBQuery::A_STR),
          array($replacementAttendee->id),
          DB::T(DB::RP_ENTRY)->db_name()
        );
        $q->where(new DBCond('attendee', $attendee));
        $q->where(new DBCond('team', $attendee->team));
        DB::query($q);

        DB::remove($attendee);
      }
      else {
        // Otherwise, update the sailor in the attendance object
        $attendee->sailor = $replacement;
        DB::set($attendee, true);
      }
    }

    return count($originalAttendance);
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
    $sailor->external_id = null;
    $sailor->register_status = Sailor::STATUS_UNREGISTERED;
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
    if (!$sailor->isRegistered()
        && $sailor->regatta_added == $this->regatta->id
        && count($this->getAttendance($sailor)) == 0) {
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
        $c = new DBBool(
          array(
            new DBCond('boat_role', $role),
            new DBCondIn(
              'attendee',
              DB::prepGetAll(
                DB::T(DB::ATTENDEE),
                new DBCond('sailor', $sailor),
                array('id')
              )
            ),
            new DBCondIn(
              'race',
              DB::prepGetAll(
                DB::T(DB::RACE),
                new DBBool(
                  array(
                    new DBCond('regatta', $this->regatta->id),
                    new DBCond('division', (string)$div)
                  )
                ),
                array('id')
              )
            )
          )
        );
        $res = DB::getAll(DB::T(DB::RP_ENTRY), $c);
        if (count($res) > 0)
          $rps[] = new RP($res);
      }
    }
    return $rps;
  }

  public function isParticipating(Member $sailor, $role = null, $division = null) {
    return count($this->getParticipation($sailor, $role, $division)) > 0;
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

    $cond = new DBCondIn(
      'attendee',
      DB::prepGetAll(
        DB::T(DT::ATTENDEE),
        new DBCond('sailor', $sailor),
        array('id')));

    $c = new DBBool(array($cond));
    if ($role !== null)
      $c->add(new DBCond('boat_role', $role));
    $c->add(new DBCondIn('race', DB::prepGetAll(DB::T(DB::RACE), $r, array('id'))));

    return DB::getAll(DB::T(DB::RP_ENTRY), $c);
  }

  /**
   * Returns list of sailors from the given team with RP entries.
   *
   * @param Team $team the team whose RP entries to search.
   * @return Array:Sailor the sailors (may be from other schools).
   */
  public function getParticipatingSailors(Team $team) {
    if ($this->regatta->getTeam($team->id) === null) {
      throw new InvalidArgumentException("Given team ($team) is not from this regatta.");
    }

    $sailorCond = new DBCondIn(
      'id',
      DB::prepGetAll(
        DB::T(DB::ATTENDEE),
        new DBCondIn(
          'id',
          DB::prepGetAll(
            DB::T(DB::RP_ENTRY),
            new DBCond('team', $team),
            array('attendee')
          )
        ),
        array('sailor')
      )
    );

    return DB::getAll(DB::T(DB::SAILOR), $sailorCond);
  }

  /**
   * Returns list of sailors from the given team with no RP entries.
   *
   * @param Team $team the team whose RP entries to search.
   * @return Array:Sailor the reserve sailors (may be from other schools).
   */
  public function getReserveSailors(Team $team) {
    if ($this->regatta->getTeam($team->id) === null) {
      throw new InvalidArgumentException("Given team ($team) is not from this regatta.");
    }

    $sailorCond = new DBCondIn(
      'id',
      DB::prepGetAll(
        DB::T(DB::ATTENDEE),
        new DBBool(
          array(
            new DBCond('team', $team),
            new DBCondIn(
              'id',
              DB::prepGetAll(
                DB::T(DB::RP_ENTRY),
                new DBCond('team', $team),
                array('attendee')
              ),
              DBCondIn::NOT_IN
            ),
          )
        ),
        array('sailor')
      )
    );

    return DB::getAll(DB::T(DB::SAILOR), $sailorCond);
  }

  // Static variable and functions

  /**
   * Inactivates all the sailors with the given role in the
   * database. This is useful when syncing from a membership feed.
   *
   * @param Sailor::Const the role
   */
  public static function inactivateRole($role) {
    $q = DB::createQuery(DBQuery::UPDATE);
    $q->values(array('active'), array(DBQuery::A_STR), array(null), DB::T(DB::SAILOR)->db_name());
    $q->where(
      new DBBool(
        array(
          new DBCond('role', $role),
          new DBCond('external_id', null, DBCond::NE),
        )));
    DB::query($q);
  }
}
