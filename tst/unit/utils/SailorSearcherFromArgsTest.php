<?php
use \utils\SailorSearcher;

require_once(dirname(__DIR__) . '/AbstractUnitTester.php');

/**
 * Tests the static methods of SailorSearcher.
 *
 * @author Dayan Paez
 * @version 2015-11-20
 */
class SailorSearcherFromArgsTest extends AbstractUnitTester {

  const VALID_SCHOOL_ID = 'SchoolId';
  const NO_ACCESS_SCHOOL_ID = 'NoAccessSchoolId';
  const MISSING_SCHOOL_ID = 'BadSchoolId';
  const VALID_YEAR = 2008;
  const INVALID_YEAR = 'Two thousand eight';
  const QUERY = 'QueryString';

  private $account;
  private $school;

  protected function setUp() {
    $this->account = new SailorSearcherFromArgsTestAccount();

    $this->school = new School();
    $this->school->id = self::VALID_SCHOOL_ID;
    $this->school->name = "Name";

    $noAccessSchool = new School();
    $noAccessSchool->id = self::NO_ACCESS_SCHOOL_ID;

    DB::setDbm(new SailorSearcherFromArgsTestDBM());
    SailorSearcherFromArgsTestDBM::resetForTest();
    SailorSearcherFromArgsTestDBM::setSchoolsById(
      array(
        self::VALID_SCHOOL_ID => $this->school,
        self::NO_ACCESS_SCHOOL_ID => $noAccessSchool,
      )
    );
  }

  public function testFromArgsSingleEntries() {
    $args = array(
      SailorSearcher::FIELD_QUERY => self::QUERY,
      SailorSearcher::FIELD_MEMBER_STATUS => SailorSearcher::STATUS_REGISTERED,
      SailorSearcher::FIELD_GENDER => Member::FEMALE,
      SailorSearcher::FIELD_YEAR => self::VALID_YEAR,
      SailorSearcher::FIELD_SCHOOL => self::VALID_SCHOOL_ID,
    );

    $result = SailorSearcher::fromArgs($this->account, $args);
    $this->assertSame($this->account, $result->getAccount());
    $this->assertEquals(self::QUERY, $result->getQuery());
    $this->assertEquals(SailorSearcher::STATUS_REGISTERED, $result->getMemberStatus());
    $this->assertEquals(Member::FEMALE, $result->getGender());
    $this->assertEquals(array(self::VALID_YEAR), $result->getYears());
    $this->assertEquals(array($this->school), $result->getSchools());
  }

  public function testFromArgsFull() {
    $args = array(
      SailorSearcher::FIELD_YEAR => array(
        self::INVALID_YEAR,
        self::VALID_YEAR,
      ),
      SailorSearcher::FIELD_SCHOOL => array(
        self::VALID_SCHOOL_ID,
        self::MISSING_SCHOOL_ID,
        self::NO_ACCESS_SCHOOL_ID,
      ),
    );

    $result = SailorSearcher::fromArgs($this->account, $args);
    $this->assertEquals(array(self::VALID_YEAR), $result->getYears());
    $this->assertEquals(array($this->school), $result->getSchools());
  }

  public function testFromArgsEmpty() {
    $args = array();
    $result = SailorSearcher::fromArgs($this->account, $args);
    $this->assertEmpty($result->getYears());
    $this->assertEmpty($result->getSchools());
  }
}

/**
 * Mock DBM.
 */
class SailorSearcherFromArgsTestDBM extends DBM {

  private static $schoolsById;

  public static function resetForTest() {
    self::$schoolsById = array();
  }

  public static function setSchoolsById(Array $db) {
    self::$schoolsById = $db;
  }

  public static function get(DBObject $obj, $id) {
    if ($obj instanceof School) {
      return (array_key_exists($id, self::$schoolsById))
        ? self::$schoolsById[$id]
        : null;
    }
    throw new InvalidArgumentException(
      sprintf(
        "Did not expect a call to get(%s, %s).",
        get_class($obj),
        $id
      )
    );
  }
}

/**
 * Mock account.
 */
class SailorSearcherFromArgsTestAccount extends Account {

  public function hasSchool(School $school) {
    return ($school->id == SailorSearcherFromArgsTest::VALID_SCHOOL_ID);
  }

}