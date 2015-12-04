<?php
namespace pub;

use \AbstractUnitTester;

use \Conference;
use \DateTime;
use \Regatta;
use \School;
use \Season;

/**
 * Test the maker.
 *
 * @author Dayan Paez
 * @version 2015-12-04
 */
class SchoolReportMakerTest extends AbstractUnitTester {

  public function testMainPage() {
    $regatta1 = new SchoolReportMakerTestRegatta();
    $regatta1->nick = 'reg-1';

    $regatta2 = new SchoolReportMakerTestRegatta();
    $regatta2->dt_status = Regatta::STAT_FINAL;
    $regatta2->start_time = new DateTime('2 days ago');
    $regatta2->end_date = new DateTime('yesterday');
    $regatta2->nick = 'reg-2';

    $regatta3 = new SchoolReportMakerTestRegatta();
    $regatta3->dt_status = "STATUS";
    $regatta3->start_time = new DateTime('yesterday');
    $regatta3->end_date = new DateTime('tomorrow');
    $regatta3->nick = 'reg-3';

    $regatta4 = new SchoolReportMakerTestRegatta();
    $regatta4->dt_status = "STATUS";
    $regatta4->start_time = new DateTime('tomorrow');
    $regatta4->end_date = new DateTime('tomorrow');
    $regatta4->nick = 'reg-4';
    $season = new SchoolReportMakerTestSeason(
      array(
        $regatta1,
        $regatta2,
        $regatta3,
        $regatta4,
      )
    );

    $school = new SchoolReportMakerTestSchool();

    $testObject = new SchoolReportMaker($school, $season);
    $page = $testObject->getMainPage();
    $this->assertNotNull($page);
  }

  public function testRosterPage() {
    $regatta1 = new SchoolReportMakerTestRegatta();
    $regatta2 = new SchoolReportMakerTestRegatta();
    $season = new SchoolReportMakerTestSeason(
      array(
        $regatta1,
        $regatta2,
      )
    );

    $school = new SchoolReportMakerTestSchool();

    $testObject = new SchoolReportMaker($school, $season);
    $page = $testObject->getRosterPage();
    $this->assertNotNull($page);
  }

}

/**
 * Mock season.
 */
class SchoolReportMakerTestSeason extends Season {

  private $regattas;

  public function __construct(Array $regattas) {
    parent::__construct();
    $this->season = self::FALL;
    $this->start_date = new DateTime();
    $this->end_date = new DateTime();
    $this->url = 'f15';

    $this->regattas = $regattas;
  }

  public function getParticipation(School $school, $inc_private = false) {
    return $this->regattas;
  }

}

/**
 * Mock school.
 */
class SchoolReportMakerTestSchool extends School {

  private static $counter = 1;

  public function __construct() {
    $this->id = self::$counter;
    $this->name = sprintf("Test School %d", self::$counter);
    $this->nick_name = $this->name;
    $this->url = sprintf('school-%d', self::$counter);
    $this->conference = new Conference();
    self::$counter++;
  }
}

/**
 * Mock regatta.
 */
class SchoolReportMakerTestRegatta extends Regatta {

  const DATA_SCORING = 'DataScoring';
  const HOST_VENUE = 'HostVenue';

  public function getDataScoring() {
    return self::DATA_SCORING;
  }

  public function getHostVenue() {
    return self::HOST_VENUE;
  }
}