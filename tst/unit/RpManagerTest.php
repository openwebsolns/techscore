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
  private $standardRegattaTeam;
  private $availableSailors;
  private $participatingSailors;

  /**
   * Create standard regatta, add school, and some sailors.
   *
   */
  protected function setUp() {
    $this->standardRegatta = self::getRegatta(Regatta::SCORING_STANDARD);

    $schools = DB::getSchools();
    if (count($schools) == 0) {
      throw new InvalidArgumentException("No schools in system!");
    }

    $this->availableSailors = array();
    $attempts = 0;
    while (count($this->availableSailors) < 2 && $attempts < 10) {
      $attempts++;
      $school = $schools[rand(0, count($schools) - 1)];
      $this->availableSailors = $school->getSailors();
    }

    if (count($this->availableSailors) == 0) {
      throw new InvalidArgumentException("No sailors found.");
    }

    $this->participatingSailors = array();
    for ($i = 0; $i < count($this->availableSailors) - 1 && $i < 5; $i++) {
      $this->participatingSailors[] = $this->availableSailors[$i];
    }

    $this->standardRegattaTeam = new Team();
    $this->standardRegattaTeam->school = $school;
    $this->standardRegattaTeam->name = "Unit Test";
    $this->standardRegatta->addTeam($this->standardRegattaTeam);

    $rpManager = $this->standardRegatta->getRpManager();
    $rpManager->setAttendees($this->standardRegattaTeam, $this->participatingSailors);
  }

  /**
   * Test the attendee adding logic: make sure that existing list of
   * attendees is unaffected by addition of new ones.
   *
   */
  public function testSetAttendees() {

    $team = $this->standardRegattaTeam;

    $rpManager = $this->standardRegatta->getRpManager();
    $attendees = $rpManager->getAttendees($team);
    $this->assertEquals(count($this->participatingSailors), count($attendees), "Comparing initially set attendee list");

    $attendeeIds = array();
    foreach ($attendees as $attendee) {
      $attendeeIds[$attendee->sailor->id] = $attendee->id;
    }

    // Add a new one, and remove last one
    $newSailor = $this->availableSailors[count($attendees)];
    $this->participatingSailors[] = $newSailor;
    array_shift($this->participatingSailors);
    $rpManager->setAttendees($team, $this->participatingSailors);

    $attendees = $rpManager->getAttendees($team);
    $this->assertEquals(count($this->participatingSailors), count($attendees), "Comparing new attendee list");

    foreach ($attendees as $attendee) {
      if ($attendee->sailor->id != $newSailor->id) {
        $this->assertContains($attendee->id, $attendeeIds, "Is old attendee still intact?");
      }
    }
  }

  public function testReplaceSailor() {

    $team = $this->standardRegattaTeam;
    $rpManager = $this->standardRegatta->getRpManager();
    $attendees = $rpManager->getAttendees($team);
    $toReplace = $attendees[0]->sailor;

    // Case 1: "new" attendee is already attending same team.
    $replacement = $attendees[1]->sailor;
    $rpManager->replaceSailor($toReplace, $replacement);
    foreach ($rpManager->getAttendees($team) as $attendee) {
      $this->assertNotEquals($toReplace->id, $attendee->sailor->id);
    }

    // Case 2: "new" attendee is new to the regatta/team.
    $replacement2 = $this->availableSailors[count($this->participatingSailors) - 1];
    $rpManager->replaceSailor($replacement, $replacement2);
    foreach ($rpManager->getAttendees($team) as $attendee) {
      $this->assertNotEquals($replacement->id, $attendee->sailor->id);
    }
  }

  public function testGetParticipationEntries() {
    $rpManager = $this->standardRegatta->getRpManager();
  }
}
