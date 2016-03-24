<?php

require_once('AbstractTester.php');

/**
 * The maximum size for team name preferences is 20 characters, as
 * specified in the database. However, the full team name may be this
 * slug plus a unique identifier, which is currently an increasing
 * numerical value.
 *
 * This test ascertains that as many as ten teams from the same school
 * can be created where the preference name is already 20 characters
 * long.
 *
 * @author Dayan Paez
 * @version 2016-03-24
 */
class CreateTeamNamesLongerThan20CharactersTest extends AbstractTester {

  protected function setUp() {
    $this->login();
  }

  public function testCreationOfLongNames() {
    try {
      $regatta = self::$regatta_creator->createStandardRegatta(1, 1, 1);
    }
    catch (SoterException $e) {
      $this->markTestSkipped($e->getMessage());
      return;
    }

    $numTeams = 10;
    $teams = self::$regatta_creator->createNTeams($numTeams);
    $slug = 'Equal 20 characters';
    foreach ($teams as $i => $team) {
      $team->name = $slug . " " . ($i + 1);
      $regatta->addTeam($team);
    }

    // Assert team names
    $registeredTeams = $regatta->getTeams();
    $this->assertEquals($numTeams + 1, count($registeredTeams));

    for ($i = 1; $i <= $numTeams; $i++) {
      $team = $registeredTeams[$i];
      $this->assertEquals(sprintf('%s %d', $slug, $i), $team->name);
    }
  }

}