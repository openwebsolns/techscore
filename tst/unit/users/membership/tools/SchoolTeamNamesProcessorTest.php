<?php
namespace users\membership\tools;

use \AbstractUnitTester;
use \DB;
use \DBM;
use \DBObject;
use \InvalidArgumentException;
use \Regatta;
use \School;
use \Team;
use \UpdateRequest;

/**
 * Test the goodness of this processor.
 *
 * @author Dayan Paez
 * @version 2015-11-17
 */
class SchoolTeamNamesProcessorTest extends AbstractUnitTester {

  private $testObject;
  private $school;

  protected function setUp() {
    DB::setDbm(new SchoolTeamNamesProcessorTestDBM());
    $this->school = new SchoolTeamNamesProcessorTestSchool();
    $this->testObject = new SchoolTeamNamesProcessor();
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessNamesEmptyList() {
    $this->testObject->processNames($this->school, array());
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessNamesTrivialList() {
    $this->testObject->processNames($this->school, array(" ", ""));
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessNamesInvalidName() {
    $names = array('Foo 2');
    $this->testObject->processNames($this->school, $names);
  }

  public function testProcessNamesNotFirstTime() {
    $name = "TestName";
    $list = array("", $name);
    $this->school->setTeamNames(array("OldName"));
    $result = $this->testObject->processNames($this->school, array($name));
    $this->assertEquals(array($name), $result);
    $this->assertEmpty(SchoolTeamNamesProcessorTestDBM::getSetObjects());
  }

  /**
   * Here we test that very special feature of the processor which
   * retroactively updates all the teams that are using the school's
   * nick name as the foundation of their name, and queue an update
   * request.
   *
   * We do so by creating two mock regattas, one with a team that is
   * OK and one whose teams use variations of the school's nick name.
   */
  public function testProcessNamesFirstTime() {
    $schoolName = "SchoolName";
    $primaryName = "PrimaryName";
    $secondaryName = "SecondaryName";

    // Prep
    $goodTeam = new Team();
    $goodTeam->school = $this->school;
    $goodTeam->name = "TeamName Different from SchoolName";
    $goodRegatta = new SchoolTeamNamesProcessorTestRegatta(array($goodTeam));

    $teamToUpdate1 = new Team();
    $teamToUpdate1->name = $schoolName;
    $teamToUpdate1->school = $this->school;
    $teamToUpdate2 = new Team();
    $teamToUpdate2->name = $schoolName . " 2";
    $teamToUpdate2->school = $this->school;
    $interestingRegatta = new SchoolTeamNamesProcessorTestRegatta(
      array($teamToUpdate1, $teamToUpdate2)
    );

    $this->school->nick_name = $schoolName;
    $this->school->setRegattas(
      array($goodRegatta, $interestingRegatta)
    );

    // Test
    $result = $this->testObject->processNames(
      $this->school,
      array($primaryName, $secondaryName)
    );

    // Verify what was updated
    $setTeams = SchoolTeamNamesProcessorTestDBM::getSetObjects(new Team());
    $this->assertNotEmpty($setTeams);
    foreach ($setTeams as $setTeam) {
      $this->assertContains($primaryName, $setTeam->name);
      $this->assertTrue(
        $setTeam === $teamToUpdate1 || $setTeam === $teamToUpdate2
      );
    }

    $setUpdateRequests = SchoolTeamNamesProcessorTestDBM::getSetObjects(new UpdateRequest());
    $this->assertEquals(1, count($setUpdateRequests));
    $updateRequest = $setUpdateRequests[0];
    $this->assertSame($interestingRegatta, $updateRequest->regatta);
    $this->assertEquals(UpdateRequest::ACTIVITY_TEAM, $updateRequest->activity);
  }
}

/**
 * Mock DBM
 */
class SchoolTeamNamesProcessorTestDBM extends DBM {

  private static $setObjects = array();

  public static function set(DBObject $obj, $update = 'guess') {
    self::$setObjects[] = $obj;
  }

  public static function getSetObjects(DBObject $ofType = null) {
    $objects = array();
    foreach (self::$setObjects as $object) {
      if ($ofType === null || is_a($object, get_class($ofType))) {
        $objects[] = $object;
      }
    }
    return $objects;
  }
}

/**
 * Mock school.
 */
class SchoolTeamNamesProcessorTestSchool extends School {

  private $teamNames = array();
  private $regattas = array();

  public function setTeamNames(Array $names) {
    $this->teamNames = $names;
  }

  public function getTeamNames() {
    return $this->teamNames;
  }

  public function setRegattas(Array $regattas) {
    $this->regattas = $regattas;
  }

  public function getRegattas($inc_private = false) {
    if ($inc_private !== false) {
      throw new InvalidArgumentException("Should not be calling this with anything other than false.");
    }
    return $this->regattas;
  }
}

/**
 * Mock regatta.
 */
class SchoolTeamNamesProcessorTestRegatta extends Regatta {

  private $teams;

  public function __construct(Array $teams) {
    $this->teams = $teams;
  }

  public function getTeams(School $school = null) {
    return $this->teams;
  }
}
