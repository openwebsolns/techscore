<?php

/**
 * Convenience class for creating test regattas.
 *
 * This class also serves as a regatta registry, tracking the regattas
 * created and eventually deleting them from the database (using a
 * shutdown listener).
 *
 * @author Dayan Paez
 * @version 2015-10-18
 */
class RegattaCreator {

  private $regattaRegistry = array();

  public function createStandardRegatta(
    $num_teams,
    $num_divisions,
    $num_races,
    $boat = null,
    $type = null,
    $participation = Regatta::PARTICIPANT_COED,
    $name = 'Standard Test Regatta'
  ) {
    return $this->createRegatta(
      $num_teams,
      $num_divisions,
      $num_races,
      $boat,
      $type,
      Regatta::SCORING_STANDARD,
      $participation,
      $name
    );
  }

  public function createCombinedRegatta(
    $num_teams,
    $num_races,
    $num_divisions = 2,
    $boat = null,
    $type = null,
    $participation = Regatta::PARTICIPANT_COED,
    $name = 'Combined Test Regatta'
  ) {
    return $this->createRegatta(
      $num_teams,
      $num_divisions,
      $num_races,
      $boat,
      $type,
      Regatta::SCORING_COMBINED,
      $participation,
      $name
    );
  }

  public function createRegatta(
    $num_teams,
    $num_divisions,
    $num_races,
    $boat = null,
    $type = null,
    $scoring = Regatta::SCORING_STANDARD,
    $participation = Regatta::PARTICIPANT_COED,
    $name = 'Test Regatta'
  ) {
    if ($type === null) {
      $types = DB::getAll(DB::T(DB::ACTIVE_TYPE));
      if (count($types) == 0) {
        throw new SoterException("No regatta types exist.");
      }
      $type = $types[rand(0, count($types) - 1)];
    }
    if ($boat === null) {
      $boats = DB::getBoats();
      if (count($boats) == 0) {
        throw new SoterException("No boats exist.");
      }
      $boat = $boats[rand(0, count($boats) - 1)];
    }
    $startTime = new DateTime();
    $endTime = new DateTime();
    $endTime->add(new DateInterval('P2DT0H'));

    $regatta = Regatta::createRegatta(
      'Standard Test Regatta',
      $startTime,
      $endTime,
      $type,
      $scoring,
      $participation,
      true // private
    );

    // Setup the teams
    foreach ($this->createNTeams($num_teams) as $team) {
      $regatta->addTeam($team);
    }

    // Setup the races
    foreach (Division::listOfSize($num_divisions) as $division) {
      for ($i = 0; $i < $num_races; $i++) {
        $race = new Race();
        $race->number = ($i + 1);
        $race->division = $division;
        $race->boat = $boat;
        $regatta->setRace($race);
      }
    }

    DB::commit();
    $this->regattaRegistry[] = $regatta;
    return $regatta;
  }

  /**
   * Create as many teams as specified from list of given schools.
   *
   * Pass in an empty list of schools to attempt to create from as
   * many different schools as possible.
   *
   * @param int $num_teams the number of teams to create
   * @param Array:School $schools the list of schools to cycle through when
   *   creating the teams.
   * @return Array:Team regatta-less teams of size $num_teams.
   * @throws InvalidArgumentException if unable to create the teams.
   */
  public function createNTeams($num_teams, Array $schools = array()) {
    if ($num_teams <= 0) {
      throw new InvalidArgumentException("Number of teams must be greater than 0.");
    }
    if (count($schools) == 0) {
      foreach (DB::getSchools() as $school) {
        if (count($schools) >= $num_teams) {
          break;
        }
        $schools[] = $school;
      }
      if (count($schools) == 0) {
        throw new SoterException("There are no available schools.");
      }
    }

    $schoolCount = count($schools);
    $teams = array();
    for ($i = 0; $i < $num_teams; $i++) {
      $team = new Team();
      $team->school = $schools[$i % $schoolCount];
      $team->name = "Test Team " . ($i + 1);
      $teams[] = $team;
    }
    return $teams;
  }

  /**
   * Delete all regattas created.
   *
   */
  public function cleanup() {
    foreach ($this->regattaRegistry as $regatta) {
      //DB::remove($regatta);
    }
  }
}