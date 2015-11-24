<?php
namespace users\membership\tools;

use \AbstractUnitTester;
use \Conference;
use \DB;
use \DBM;
use \DBObject;
use \DBCond;
use \DBExpression;
use \InvalidArgumentException;
use \School;
use \SoterException;
use \STN;

require_once('xml5/TS.php');

/**
 * Test the school settings processor.
 *
 * @author Dayan Paez
 * @version 2015-11-17
 */
class EditSchoolProcessorTest extends AbstractUnitTester {

  private $testObject;
  private $school;

  protected function setUp() {
    DB::setDbm(new EditSchoolProcessorTestDBM());
    EditSchoolProcessorTestDBM::resetForTest();
    $this->school = new School();
    $this->testObject = new EditSchoolProcessor();
  }

  public function testNothingEditable() {
    $changed = $this->testObject->process(
      array(),
      $this->school,
      array() // nothing editable
    );

    $this->assertEmpty($changed);
    $this->assertEmpty(EditSchoolProcessorTestDBM::getSetObjects());
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessNameDuplicate() {
    $newName = "NewName";
    EditSchoolProcessorTestDBM::setSchoolsByName(
      array($newName => array(new School()))
    );
    $this->testObject->process(
      array(EditSchoolForm::FIELD_NAME => $newName),
      $this->school,
      array(EditSchoolForm::FIELD_NAME)
    );
  }

  public function testProcessName() {
    $newName = "NewName";
    $changed = $this->testObject->process(
      array(EditSchoolForm::FIELD_NAME => $newName),
      $this->school,
      array(EditSchoolForm::FIELD_NAME)
    );
    $this->assertEquals(array(EditSchoolForm::FIELD_NAME), $changed);
    $this->assertEquals($newName, $this->school->name);
  }

  public function testProcessNickName() {
    $newNickName = "NewNickName";
    $changed = $this->testObject->process(
      array(EditSchoolForm::FIELD_NICK_NAME => $newNickName),
      $this->school,
      array(EditSchoolForm::FIELD_NICK_NAME)
    );
    $this->assertEquals(array(EditSchoolForm::FIELD_NICK_NAME), $changed);
    $this->assertEquals($newNickName, $this->school->nick_name);
  }

  public function testProcessCity() {
    $newCity = "NewCity";
    $changed = $this->testObject->process(
      array(EditSchoolForm::FIELD_CITY => $newCity),
      $this->school,
      array(EditSchoolForm::FIELD_CITY)
    );
    $this->assertEquals(array(EditSchoolForm::FIELD_CITY), $changed);
    $this->assertEquals($newCity, $this->school->city);
  }

  public function testProcessState() {
    $newState = "MA";
    $changed = $this->testObject->process(
      array(EditSchoolForm::FIELD_STATE => $newState),
      $this->school,
      array(EditSchoolForm::FIELD_STATE)
    );
    $this->assertEquals(array(EditSchoolForm::FIELD_STATE), $changed);
    $this->assertEquals($newState, $this->school->state);
  }

  public function testProcessConference() {
    $newConferenceId = "NewConference";
    $newConference = new Conference();
    $newConference->id = $newConferenceId;
    EditSchoolProcessorTestDBM::setConferencesById(
      array($newConferenceId => $newConference)
    );

    $changed = $this->testObject->process(
      array(EditSchoolForm::FIELD_CONFERENCE => $newConferenceId),
      $this->school,
      array(EditSchoolForm::FIELD_CONFERENCE)
    );
    $this->assertEquals(array(EditSchoolForm::FIELD_CONFERENCE), $changed);
    $this->assertSame($newConference, $this->school->conference);
  }

  public function testProcessUrlValid() {
    $newUrl = "valid-url";
    $changed = $this->testObject->process(
      array(EditSchoolForm::FIELD_URL => $newUrl),
      $this->school,
      array(EditSchoolForm::FIELD_URL)
    );
    $this->assertEquals(array(EditSchoolForm::FIELD_URL), $changed);
    $this->assertEquals($newUrl, $this->school->url);
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessUrlDuplicate() {
    $newUrl = "valid-url";
    EditSchoolProcessorTestDBM::setSchoolsByUrl(
      array($newUrl => new School())
    );

    $this->testObject->process(
      array(EditSchoolForm::FIELD_URL => $newUrl),
      $this->school,
      array(EditSchoolForm::FIELD_URL)
    );
  }

  public function testProcessUrlInvalidUrls() {

    $testUrls = array(
      'WithCapitalLetters',
      'with spaces',
      '-leading-hyphens',
      'trailing-hyphens-',
      'utf8-✓',
    );
    foreach ($testUrls as $testUrl) {
      try {
        $this->testObject->process(
          array(EditSchoolForm::FIELD_URL => $testUrl),
          $this->school,
          array(EditSchoolForm::FIELD_URL)
        );
        $this->assertTrue(
          false,
          "Expected invalid URL for " . $testUrl
        );
      }
      catch (SoterException $e) {
        // No op. This is expected.
      }
    }
  }

  public function testProcessIdInvalidIds() {

    $testIds = array(
      'with spaces',
      'utf8-✓',
      'with,$',
    );
    foreach ($testIds as $testId) {
      try {
        $this->testObject->process(
          array(EditSchoolForm::FIELD_ID => $testId),
          $this->school,
          array(EditSchoolForm::FIELD_ID)
        );
        $this->assertTrue(
          false,
          "Expected invalid ID for " . $testId
        );
      }
      catch (SoterException $e) {
        // No op. This is expected.
      }
    }
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessIdDuplicate() {
    $newId = "NewId";
    $existingSchool = new School();
    $existingSchool->name = "ExistingName";
    EditSchoolProcessorTestDBM::setSchoolsById(
      array($newId => $existingSchool)
    );

    $this->testObject->process(
      array(EditSchoolForm::FIELD_ID => $newId),
      $this->school,
      array(EditSchoolForm::FIELD_ID)
    );
  }

  public function testProcessIdNewSchool() {
    $newId = "NewId";
    $changed = $this->testObject->process(
      array(EditSchoolForm::FIELD_ID => $newId),
      $this->school,
      array(EditSchoolForm::FIELD_ID)
    );
    $this->assertEquals(array(EditSchoolForm::FIELD_ID), $changed);
    $this->assertEquals($newId, $this->school->id);

    $setObjects = EditSchoolProcessorTestDBM::getSetObjects(new School());
    $this->assertEquals(1, count($setObjects));
    $this->assertSame($this->school, $setObjects[0]);
  }

  public function testProcessIdExistingSchool() {
    $oldId = "OldID";
    $this->school->id = $oldId;
    $newId = "NewId";
    $changed = $this->testObject->process(
      array(EditSchoolForm::FIELD_ID => $newId),
      $this->school,
      array(EditSchoolForm::FIELD_ID)
    );
    $this->assertEquals(array(EditSchoolForm::FIELD_ID), $changed);
    $this->assertEquals($newId, $this->school->id);

    $reIdCalled = EditSchoolProcessorTestDBM::getReIdMap();
    $this->assertEquals(array($oldId => $newId), $reIdCalled);
  }

  public function testNothingChanged() {
    $id = "TestId";
    $name = "TestName";
    $nickName = "TestNickName";
    $city = "TestCity";
    $state = "MA";
    $conferenceId = "TestConferenceId";
    $conference = new Conference();
    $conference->id = $conferenceId;
    $url = "test-url";

    $this->school->id = $id;
    $this->school->name = $name;
    $this->school->nick_name = $nickName;
    $this->school->city = $city;
    $this->school->state = $state;
    $this->school->conference = $conference;
    $this->school->url = $url;

    EditSchoolProcessorTestDBM::setConferencesById(
      array($conferenceId => $conference)
    );

    $changed = $this->testObject->process(
      array(
        EditSchoolForm::FIELD_ID => $id,
        EditSchoolForm::FIELD_NAME => $name,
        EditSchoolForm::FIELD_NICK_NAME => $nickName,
        EditSchoolForm::FIELD_CITY => $city,
        EditSchoolForm::FIELD_STATE => $state,
        EditSchoolForm::FIELD_CONFERENCE => $conferenceId,
        EditSchoolForm::FIELD_URL => $url,
      ),
      $this->school,
      array(
        EditSchoolForm::FIELD_ID,
        EditSchoolForm::FIELD_NAME,
        EditSchoolForm::FIELD_NICK_NAME,
        EditSchoolForm::FIELD_CITY,
        EditSchoolForm::FIELD_STATE,
        EditSchoolForm::FIELD_CONFERENCE,
        EditSchoolForm::FIELD_URL,
      )
    );

    $this->assertEmpty($changed);
  }
}

/**
 * Mock DBM.
 */
class EditSchoolProcessorTestDBM extends DBM {

  private static $reIdCalled = array();
  private static $setObjects = array();
  private static $schoolsByUrl = array();
  private static $schoolsByName = array();
  private static $schoolsById = array();
  private static $conferencesById = array();

  public static function resetForTest() {
    self::setSchoolsByUrl(array());
    self::setSchoolsByName(array());
    self::setSchoolsById(array());
    self::setConferencesById(array());
    self::$setObjects = array();
    self::$reIdCalled = array();
  }

  public static function set(DBObject $obj, $update = 'guess') {
    if (!in_array($obj, self::$setObjects)) {
      self::$setObjects[] = $obj;
    }
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

  public static function reID(DBObject $obj, $newId) {
    self::$reIdCalled[$obj->id] = $newId;
    $obj->id = $newId;
  }

  public static function getReIdMap() {
    return self::$reIdCalled;
  }

  public static function setSchoolsByUrl(Array $schools) {
    self::$schoolsByUrl = $schools;
  }

  public static function setSchoolsByName(Array $schools) {
    self::$schoolsByName = $schools;
  }

  public static function setSchoolsById(Array $schools) {
    self::$schoolsById = $schools;
  }

  public static function setConferencesById(Array $conferences) {
    self::$conferencesById = $conferences;
  }

  public static function getAll(DBObject $obj, DBExpression $cond = null, $limit = null) {
    if ($obj instanceof School) {
      if ($cond instanceof DBCond && $cond->field == 'url') {
        $value = $cond->value;
        return (array_key_exists($value, self::$schoolsByUrl))
          ? array(self::$schoolsByUrl[$value])
          : array();
      }

      if ($cond instanceof DBCond && $cond->field == 'name') {
        $value = $cond->value;
        return (array_key_exists($value, self::$schoolsByName))
          ? array(self::$schoolsByName[$value])
          : array();
      }
    }
    throw new InvalidArgumentException("Did not expect a call to getAll. Did you?");
  }

  public static function get(DBObject $obj, $id) {
    if ($obj instanceof School) {
      return array_key_exists($id, self::$schoolsById)
        ? self::$schoolsById[$id]
        : null;
    }
    if ($obj instanceof Conference) {
      return array_key_exists($id, self::$conferencesById)
        ? self::$conferencesById[$id]
        : null;
    }
    if ($obj instanceof STN) {
      $result = new STN();
      $result->id = $id;
      $result->value = $id;
      return $result;
    }
    throw new InvalidArgumentException("Did not expect a call to get. Did you?");
  }

}
