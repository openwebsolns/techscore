<?php
namespace pub;

use \AbstractUnitTester;

use \Conference;
use \DateTime;
use \DB;
use \DBM;
use \DBObject;
use \DBExpression;
use \InvalidArgumentException;
use \Member;
use \Regatta;
use \Sailor;
use \School;
use \Season;
use \Type;

/**
 * Sailor season page, should it ever be used.
 *
 * @author Dayan Paez
 * @version 2015-12-05
 */
class SailorSeasonPageTest extends AbstractUnitTester {

  protected function setUp() {
    DB::setDbm(new SailorSeasonPageTestDBM());
    SailorSeasonPageTestDBM::resetForTest();
  }

  public function testPage() {
    $regatta1 = new SailorSeasonPageTestRegatta();

    $regatta2 = new SailorSeasonPageTestRegatta();
    $regatta2->dt_status = Regatta::STAT_SCHEDULED;

    $currentRegatta = new SailorSeasonPageTestRegatta();
    $currentRegatta->dt_status = Regatta::STAT_FINAL;
    $currentRegatta->start_time = new DateTime('today');
    $currentRegatta->end_date = new DateTime('tomorrow');
    $currentRegatta->nick = 'current-regatta';
    $currentRegatta->type = new Type();
    $currentRegatta->type->rank = 2;

    $unscoredRegatta = new SailorSeasonPageTestRegatta();
    $unscoredRegatta->dt_status = Regatta::STAT_READY;
    $unscoredRegatta->start_time = new DateTime('today');
    $unscoredRegatta->end_date = new DateTime('tomorrow');
    $unscoredRegatta->nick = 'unscored-regatta';
    $unscoredRegatta->type = new Type();
    $unscoredRegatta->type->rank = 1;

    $pastRegatta = new SailorSeasonPageTestRegatta();
    $pastRegatta->dt_status = '3A';
    $pastRegatta->start_time = new DateTime('yesterday');
    $pastRegatta->end_date = new DateTime('yesterday');
    $pastRegatta->nick = 'past-regatta';

    $comingRegatta = new SailorSeasonPageTestRegatta();
    $comingRegatta->dt_status = Regatta::STAT_READY;
    $comingRegatta->start_time = new DateTime('tomorrow');
    $comingRegatta->end_date = new DateTime('tomorrow');
    $comingRegatta->nick = 'coming-regatta';

    $season = new SailorSeasonPageTestSeason(
      array(
        $regatta1,
        $regatta2,
        $currentRegatta,
        $unscoredRegatta,
        $pastRegatta,
        $comingRegatta,
      )
    );
    SailorSeasonPageTestDBM::setSeasons(array($season));

    $conference = new Conference();
    $conference->url = 'conference-url';

    $school = new School();
    $school->url = 'school-url';
    $school->conference = $conference;

    $sailor = new Sailor();
    $sailor->school = $school;

    $testObject = new SailorSeasonPage($sailor, $season);
    // TEST?
  }

  public function testComing() {
    $comingRegatta = new SailorSeasonPageTestRegatta();
    $comingRegatta->dt_status = Regatta::STAT_READY;
    $comingRegatta->start_time = new DateTime('tomorrow');
    $comingRegatta->end_date = new DateTime('tomorrow');
    $comingRegatta->nick = 'coming-regatta';

    $season = new SailorSeasonPageTestSeason(
      array(
        $comingRegatta,
      )
    );
    SailorSeasonPageTestDBM::setSeasons(array($season));

    $conference = new Conference();
    $conference->url = 'conference-url';

    $school = new School();
    $school->url = 'school-url';
    $school->conference = $conference;

    $sailor = new Sailor();
    $sailor->school = $school;

    $testObject = new SailorSeasonPage($sailor, $season);
    // TEST?
  }

}

/**
 * Mock season.
 */
class SailorSeasonPageTestSeason extends Season {

  private $regattas;

  public function __construct(Array $regattas) {
    $this->regattas = $regattas;
    $this->season = Season::FALL;
    $this->start_date = new DateTime();
    $this->url = 'f15';
  }

  public function getSailorAttendance(Member $sailor, $inc_private = false) {
    return $this->regattas;
  }
}

/**
 * Mock DBM.
 */
class SailorSeasonPageTestDBM extends DBM {

  private static $seasons;

  public static function resetForTest() {
    self::setSeasons(array());
  }

  public static function setSeasons(Array $seasons) {
    self::$seasons = $seasons;
  }

  public static function getAll(DBObject $obj, DBExpression $cond = null, $limit = null) {
    if ($obj instanceof Season) {
      return self::$seasons;
    }
    return DBM::getAll($obj, $cond, $limit);
  }
}

/**
 * Mock regatta.
 */
class SailorSeasonPageTestRegatta extends Regatta {

  private static $counter = 1;

  public function __construct() {
    $this->id = self::$counter++;
  }

  const DATA_SCORING = 'DataScoring';
  const HOST_VENUE = 'HostVenue';

  public function getDataScoring() {
    return self::DATA_SCORING;
  }

  public function getHostVenue() {
    return self::HOST_VENUE;
  }
}