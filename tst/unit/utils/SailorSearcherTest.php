<?php
namespace utils;

use \AbstractUnitTester;
use \Account;
use \Conference;
use \DB;
use \DBBool;
use \DBCond;
use \DBCondIn;
use \DBExpression;
use \DBM;
use \DBObject;
use \InvalidArgumentException;
use \Sailor;
use \School;

/**
 * Test the searcher.
 *
 * @author Dayan Paez
 * @version 2015-11-20
 */
class SailorSearcherTest extends AbstractUnitTester {

  private $testObject;

  protected function setUp() {
    SailorSearcherTestDBM::resetForTest();
    DB::setDbm(new SailorSearcherTestDBM());
    $this->testObject = new SailorSearcher();
  }

  public function testNone() {
    $this->testObject->doSearch();
    $conds = SailorSearcherTestDBM::getGetAllConds();
    $this->assertEquals(1, count($conds));
    $this->assertNull($conds[0]);
  }

  public function testAll() {
    $query = "TestQuery";
    $gender = Sailor::FEMALE;
    $school1 = new School();
    $school1->id = "TestSchool";
    $school2 = new School();
    $school2->id = null; // ignored
    $year = "TestYear";
    $status = Sailor::STATUS_REGISTERED;

    $this->testObject->setQuery($query);
    $this->testObject->setGender($gender);
    $this->testObject->setSchools(array($school1, $school2));
    $this->testObject->setYears(array($year, null));
    $this->testObject->setMemberStatus($status);

    $this->testObject->doSearch();
    $conds = SailorSearcherTestDBM::getGetAllConds();
    $this->assertEquals(1, count($conds));
    $cond = $conds[0];
    $this->assertTrue($cond instanceof DBBool);
    $this->assertEquals(DBBool::mAND, $cond->operator);
    $subConditions = $cond->expressions;

    $checkedQuery = false;
    foreach ($subConditions as $cond) {
      if ($cond instanceof DBBool) {
        $this->assertFalse($checkedQuery);
        $this->assertEquals(DBBool::mOR, $cond->operator);
        foreach ($cond->expressions as $sub) {
          $this->assertTrue(
            in_array($sub->field, array('first_name', 'last_name', 'concat(first_name, " ", last_name)'))
          );
        }
        $checkedQuery = true;
      }
      else {
        switch ($cond->field) {
        case 'school':
          $this->assertEquals(array($school1->id), $cond->values);
          $this->assertEquals(DBCondIn::IN, $cond->operator);
          break;

        case 'year':
          $this->assertEquals(array($year), $cond->values);
          $this->assertEquals(DBCondIn::IN, $cond->operator);
          break;

        case 'gender':
          $this->assertEquals($gender, $cond->value);
          $this->assertEquals(DBCond::EQ, $cond->operator);
          break;

        case 'register_status':
          $this->assertEquals($status, $cond->value);
          $this->assertEquals(DBCond::EQ, $cond->operator);
          break;

        default:
          $this->assertTrue(false, "Unknown field in condition: " . $cond->field);
        }
      }
    }
  }

  public function testUnregistered() {
    $status = Sailor::STATUS_UNREGISTERED;
    $this->testObject->setMemberStatus($status);

    $this->testObject->doSearch();
    $conds = SailorSearcherTestDBM::getGetAllConds();
    $cond = $conds[0];
    $this->assertTrue($cond instanceof DBBool);
    $this->assertEquals(DBBool::mAND, $cond->operator);

    $subConditions = $cond->expressions;
    $this->assertEquals(1, count($subConditions));
    $cond = $subConditions[0];
    $this->assertEquals('register_status', $cond->field);
    $this->assertEquals($status, $cond->value);
    $this->assertEquals(DBCond::EQ, $cond->operator);
  }

  public function testAccount() {
    $expectedCond = new DBCond('TestField', 'TestValue');
    $account = new SailorSearcherTestAccount($expectedCond);
    $this->testObject->setAccount($account);

    $this->testObject->doSearch();
    $conds = SailorSearcherTestDBM::getGetAllConds();
    $cond = $conds[0];
    $this->assertTrue($cond instanceof DBBool);
    $this->assertEquals(DBBool::mAND, $cond->operator);

    $subConditions = $cond->expressions;
    $this->assertEquals(1, count($subConditions));
    $cond = $subConditions[0];
    $this->assertEquals('school', $cond->field);

    $called = $account->getSchoolsDBCondCalled();
    $this->assertEquals(1, count($called));
  }
}

/**
 * Mock Account.
 */
class SailorSearcherTestAccount extends Account {

  private $dbCond;
  private $called;

  public function __construct(DBExpression $expression) {
    $this->dbCond = $expression;
    $this->called = 0;
  }

  public function getSchoolsDBCond(Conference $conf = null, $effective = true) {
    if ($conf !== null) {
      throw new InvalidArgumentException("Expected null conf.");
    }
    if ($effective !== true) {
      throw new InvalidArgumentException("Expected true effective.");
    }
    $this->called++;
    return $this->dbCond;
  }

  public function getSchoolsDBCondCalled() {
    return $this->called;
  }
}

/**
 * Mock DBM.
 */
class SailorSearcherTestDBM extends DBM {

  private static $getAllConds;

  public static function resetForTest() {
    self::$getAllConds = array();
  }

  public static function getAll(DBObject $obj, DBExpression $cond = null, $limit = null) {
    if ($obj instanceof Sailor) {
      self::$getAllConds[] = $cond;
      return array();
    }
    throw new InvalidArgumentException("Did not expect a call to getAll for " . get_class($obj));
  }

  public static function getGetAllConds() {
    return self::$getAllConds;
  }
}