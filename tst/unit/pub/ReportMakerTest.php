<?php
namespace pub;

use \AbstractUnitTester;
use \Division;
use \Regatta;
use \Season;

use \DateTime;

/**
 * Unit test for the most important class.
 *
 * @author Dayan Paez
 * @version 2015-12-04
 */
class ReportMakerTest extends AbstractUnitTester {

  private $regatta;
  private $testObject;

  protected function setUp() {
    parent::setUp();
    $this->regatta = new ReportMakerTestRegatta();
    $this->testObject = new ReportMaker($this->regatta);
  }

  public function testGetScoresPage() {
    $page = $this->testObject->getScoresPage();
    $this->assertNotNull($page);
  }

  public function testGetFullPage() {
    $page = $this->testObject->getFullPage();
    $this->assertNotNull($page);
  }

  public function testGetRotationPage() {
    $page = $this->testObject->getRotationPage();
    $this->assertNotNull($page);
  }

  public function testGetDivisionPage() {
    $page = $this->testObject->getDivisionPage(Division::A());
    $this->assertNotNull($page);
  }

  public function testGetNoticesPage() {
    $page = $this->testObject->getNoticesPage();
    $this->assertNotNull($page);
  }

  public function testGetRegistrationsPage() {
    $page = $this->testObject->getRegistrationsPage();
    $this->assertNotNull($page);
  }

  public function testGetAllRacesPage() {
    $page = $this->testObject->getAllRacesPage();
    $this->assertNotNull($page);
  }

  public function testGetSailorsPage() {
    $page = $this->testObject->getSailorsPage();
    $this->assertNotNull($page);
  }

  public function testGetCombinedPage() {
    $page = $this->testObject->getCombinedPage();
    $this->assertNotNull($page);
  }

}

/**
 * Mock regatta.
 */
class ReportMakerTestRegatta extends Regatta {

  const NICK = 'nick';
  const DATA_SCORING = 'DataScoring';
  const SUMMARY = 'Summary';

  private $season;

  public function __construct() {
    $season = new Season();
    $season->season = Season::FALL;
    $season->start_date = new DateTime();
    $season->url = 'f15';
    $this->setSeason($season);

    $this->nick = self::NICK;
    $this->start_time = new DateTime();
    $this->end_date = new DateTime('tomorrow');
  }

  public function getDataScoring() {
    return self::DATA_SCORING;
  }

  public function getSeason() {
    return $this->season;
  }

  public function setSeason(Season $season) {
    $this->season = $season;
  }

  public function getSummary(DateTime $day) {
    return self::SUMMARY;
  }
}