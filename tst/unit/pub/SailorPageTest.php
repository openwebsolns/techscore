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

/**
 * Sailor page test, yo!
 *
 * @author Dayan Paez
 * @version 2015-12-04
 */
class SailorPageTest extends AbstractUnitTester {

  protected function setUp() {
    DB::setDbm(new SailorPageTestDBM());
    SailorPageTestDBM::resetForTest();
  }

  public function testPage() {
    $regatta = new Regatta();
    $season = new SailorPageTestSeason(
      array(
        $regatta,
      )
    );
    SailorPageTestDBM::setSeasons(array($season));

    $conference = new Conference();
    $conference->url = 'conference-url';

    $school = new School();
    $school->url = 'school-url';
    $school->conference = $conference;

    $sailor = new Sailor();
    $sailor->school = $school;

    $testObject = new SailorPage($sailor);
    // TEST?
  }

}

/**
 * Mock season.
 */
class SailorPageTestSeason extends Season {

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
class SailorPageTestDBM extends DBM {

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
    throw new InvalidArgumentException(
      sprintf("Did not expect ::getAll(%s)", get_class($obj))
    );
  }

}