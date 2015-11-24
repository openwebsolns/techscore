<?php
namespace model;

use \AbstractUnitTester;
use \DB;
use \Boat;
use \Division;
use \Regatta;
use \RP;
use \Race;
use \Team;

/**
 * Test the RpManager RP complete functionality.
 *
 * @author Dayan Paez
 * @created 2015-10-30
 */
class RpManagerRpCompleteTest extends AbstractUnitTester {

  private $boat;

  protected function setUp() {
    $this->boat = new Boat();
    $this->boat->name = "Test";
    $this->boat->min_crews = 2;
    $this->boat->max_crews = 3;
    DB::set($this->boat);
  }

  protected function tearDown() {
    DB::remove($this->boat);
  }

  public function testIsCompleteForTeam() {
    $regatta = $this->standardRegatta = self::getRegatta(Regatta::SCORING_STANDARD);

    $schools = DB::getSchools();
    if (count($schools) == 0) {
      $this->markTestSkipped("No schools exist.");
      return;
    }

    $allSailors = DB::getAll(DB::T(DB::SAILOR));
    if (count($allSailors) < 4) {
      $this->markTestSkipped("Need 4 sailors for this test.");
      return;
    }
    $sailors = array();
    while (count($sailors) < 4) {
      $sailors[] = $allSailors[count($sailors)];
    }

    $team1 = new Team();
    $team1->school = $schools[rand(0, count($schools) - 1)];
    $team1->name = "Team 1";
    $regatta->addTeam($team1);

    $team2 = new Team();
    $team2->school = $schools[rand(0, count($schools) - 2)];
    $team2->name = "Team 2";
    $regatta->addTeam($team2);

    $testObject = $regatta->getRpManager();
    $this->assertTrue(
      $testObject->isCompleteForTeam($team1),
      "No races means complete"
    );

    $races = array();
    for ($i = 1; $i <= 5; $i++) {
      $race = new Race();
      $race->division = Division::A();
      $race->number = $i;
      $race->boat = $this->boat;
      $regatta->setRace($race);
      $races[] = $race;
    }

    $this->assertTrue(
      $testObject->isCompleteForTeam($team1),
      "With on scored races, RP is considered complete"
    );

    foreach ($races as $race) {
      $finishes = array();
      foreach (array($team1, $team2) as $i => $team) {
        $finish = $regatta->createFinish($race, $team);
        $finish->entered = DB::T(DB::NOW);
        $finish->earned = $i + 1;
        $finishes[] = $finish;
      }
      $regatta->commitFinishes($finishes);
    }

    $this->assertFalse(
      $testObject->isCompleteForTeam($team1),
      "With scored races, RP is considered incomplete"
    );

    // The real test, if you're still following: will entering RP
    // information for the first 4 races be enough?
    $testObject->setAttendees($team1, $sailors);
    $skippers = array();
    $crews = array();
    foreach ($testObject->getAttendees($team1) as $i => $attendee) {
      if ($i < 1) {
        $skippers[] = $attendee;
      }
      else {
        $crews[] = $attendee;
      }
    }

    for ($i = 0; $i < count($races) - 1; $i++) {
      $testObject->setRpEntries($team1, $races[$i], RP::SKIPPER, $skippers);
      $testObject->setRpEntries($team1, $races[$i], RP::CREW, $crews);
    }

    $this->assertFalse(
      $testObject->isCompleteForTeam($team1),
      "Count of participants is not enough"
    );

    // Finish by adding the last race
    $race = $races[count($races) - 1];
    $poppedCrew = array_pop($crews);
    $testObject->setRpEntries($team1, $race, RP::SKIPPER, $skippers);
    $testObject->setRpEntries($team1, $race, RP::CREW, $crews);
    $crews[] = $poppedCrew;

    $this->assertTrue(
      $testObject->isCompleteForTeam($team1),
      "All races have minimum number of RP entries."
    );
  }
}