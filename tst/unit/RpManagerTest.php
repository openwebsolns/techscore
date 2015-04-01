<?php

require_once('AbstractUnitTester.php');

/**
 * Test the RpManager functionality.
 *
 * @author Dayan Paez
 * @created 2015-03-17
 */
class RpManagerTest extends AbstractUnitTester {

  private $standardRegatta;

  /**
   * Create standard regatta, add school, and some sailors.
   *
   */
  protected function setUp() {
    $this->standardRegatta = self::getRegatta(Regatta::SCORING_STANDARD);
  }

  /**
   * Test the attendee adding logic: make sure that existing list of
   * attendees is unaffected by addition of new ones.
   *
   */
  public function testSetAttendees() {

    $schools = DB::getSchools();
    if (count($schools) == 0) {
      throw new InvalidArgumentException("No schools in system!");
    }

    $sailors = array();
    $attempts = 0;
    while (count($sailors) < 2 && $attempts < 10) {
      $attempts++;
      $school = $schools[rand(0, count($schools) - 1)];
      $sailors = $school->getSailors();
    }

    if (count($sailors) == 0) {
      throw new InvalidArgumentException("No sailors found.");
    }

    $someSailors = array();
    for ($i = 0; $i < count($sailors) - 1 && $i < 5; $i++) {
      $someSailors[] = $sailors[$i];
    }

    $team = new Team();
    $team->school = $school;
    $team->name = "Unit Test";
    $this->standardRegatta->addTeam($team);

    $rpManager = $this->standardRegatta->getRpManager();
    $rpManager->setAttendees($team, $someSailors);

    $attendees = $rpManager->getAttendees($team);
    $this->assertEquals(count($someSailors), count($attendees), "Comparing initially set attendee list");

    $attendeeIds = array();
    foreach ($attendees as $attendee) {
      $attendeeIds[$attendee->sailor->id] = $attendee->id;
    }

    // Add a new one, and remove last one
    $newSailor = $sailors[$i];
    $someSailors[] = $newSailor;
    array_shift($someSailors);
    $rpManager->setAttendees($team, $someSailors);

    $attendees = $rpManager->getAttendees($team);
    $this->assertEquals(count($someSailors), count($attendees), "Comparing new attendee list");

    foreach ($attendees as $attendee) {
      if ($attendee->sailor->id != $newSailor->id) {
        $this->assertContains($attendee->id, $attendeeIds, "Is old attendee still intact?");
      }
    }
  }

  public function testGetParticipationEntries() {
    $rpManager = $this->standardRegatta->getRpManager();
  }
}
