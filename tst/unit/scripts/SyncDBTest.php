<?php
namespace scripts;

use \AbstractUnitTester;
use \Closure;
use \Conf;
use \Conference;
use \DB;
use \DBExpression;
use \DBObject;
use \RuntimeException;
use \STN;
use \School;
use \Season;
use \XDoc;
use \XElem;
use \XText;

require_once('xml5/XmlLib.php');

/**
 * Test the synchronicity of the database.
 *
 * @author Dayan Paez
 * @version 2016-03-04
 */
class SyncDBTest extends AbstractUnitTester {

  private $testObject;
  private $core;
  private $file;

  protected function setUp() {
    $this->core = new SyncDBTestDB();
    $this->testObject = new SyncDB();
    $this->testObject->setCore($this->core);

    $this->file = tempnam(sys_get_temp_dir(), "ts-unit-test");
  }

  protected function tearDown() {
    unlink($this->file);
  }

  public function testRunSchools() {
    Conf::$USER = DB::getRootAccount();

    // Prepare
    $conference = new Conference();
    $conference->id = "CONF";
    $conference->name = "Conference";

    $previouslySyncedSchool = new School();
    $previouslySyncedSchool->id = 1;
    $previouslySyncedSchool->name = "Present Name";
    $previouslySyncedSchool->conference = $conference;
    $previouslySyncedSchool->nick_name = "Duplicate Nick";
    $previouslySyncedSchool->created_by = Conf::$USER->id;
    $previouslySyncedSchoolUrl = 'duplicate-nick';

    $manuallyCreatedSchool = new School();
    $manuallyCreatedSchool->id = 2;
    $manuallyCreatedSchool->name = "Manual Name";
    $manuallyCreatedSchool->conference = $conference;
    $manuallyCreatedSchool->nick_name = "Duplicate Nick";
    $manuallyCreatedSchool->created_by = "NOT-" . Conf::$USER->id;
    $manuallyCreatedSchool->url = 'duplicate-nick'; // should change
    $manuallyCreatedSchoolUrl = 'duplicate-nick-conf';

    $newSchool = new School();
    $newSchool->id = 3;
    $newSchool->name = "New School Name";
    $newSchool->conference = $conference;
    $newSchool->nick_name = "Duplicate Nick";
    $newSchoolUrl = 'duplicate-nick-conf-1'; // expectation

    $badConference = new Conference();
    $badConference->id = "BadConf";
    $invalidConferenceSchool = new School();
    $invalidConferenceSchool->id = 4;
    $invalidConferenceSchool->name = "Invalid Conf";
    $invalidConferenceSchool->conference = $badConference;
    $invalidConferenceSchool->nick_name = "invalid-conf";

    $xml = new XDoc(
      'schoollist', [],
      array(
        $this->schoolToXml($previouslySyncedSchool),
        $this->schoolToXml($manuallyCreatedSchool),
        $this->schoolToXml($newSchool),
        $this->schoolToXml($invalidConferenceSchool),
      )
    );

    file_put_contents($this->file, $xml->toXml());

    $season = new Season();
    $sailors = array();
    $schools = array(
      $previouslySyncedSchool->id => $previouslySyncedSchool,
      $manuallyCreatedSchool->id  => $manuallyCreatedSchool,
    );
    $conferences = array($conference->id => $conference);
    $settings = array(
      STN::SCHOOL_API_URL => $this->file,
    );

    SyncDBTestDB::init(
      $season,
      $sailors,
      $schools,
      $conferences,
      $settings
    );

    // Run test
    $log = $this->testObject->run(true, false, $season);

    // Verify
    $warnings = $this->testObject->warnings();
    $errors = $this->testObject->errors();

    // Expect one new school, one ignored, one invalid
    $this->assertEquals(1, count($errors), print_r($errors, true));
    $this->assertEquals(array_values($errors), $log->error);
    $this->assertEquals(3, count($warnings), print_r($warnings, true));

    // Assert schools set
    $schoolsSet = SyncDBTestDB::getObjectsSet(new School());
    $this->assertEquals(3, count($schoolsSet), "Schools that should be saved to DB.");

    // Previously entered school
    $prevSchoolSet = array_shift($schoolsSet);
    $this->assertNull($previouslySyncedSchool->inactive);
    $this->assertEquals($previouslySyncedSchoolUrl, $previouslySyncedSchool->url);

    // Manually entered school's URL must change
    $manualSchoolSet = array_shift($schoolsSet);
    $this->assertEquals($manuallyCreatedSchool->name, $manualSchoolSet->name);
    $this->assertNull($manuallyCreatedSchool->inactive);
    $this->assertEquals($manuallyCreatedSchoolUrl, $manualSchoolSet->url);

    // New school
    $newSchoolSet = array_shift($schoolsSet);
    $this->assertEquals($newSchool->id, $newSchoolSet->id);
    $this->assertEquals($newSchool->name, $newSchoolSet->name);
    $this->assertEquals($newSchool->conference, $newSchoolSet->conference);
    $this->assertEquals($newSchool->nick_name, $newSchoolSet->nick_name);
    $this->assertEquals($log, $newSchoolSet->sync_log);
    $this->assertEquals($newSchoolUrl, $newSchoolSet->url);
  }

  private function schoolToXml(School $school) {
    return new XElem(
      'school', [],
      array(
        new XElem('id', [], [new XText('ID-' . $school->id)]),
        new XElem('school_code', [], [new XText(' ' . $school->id)]),
        new XElem('district', [], [new XText($school->conference->id)]),
        new XElem('school_name', [], [new XText($school->name)]),
        new XElem('school_display_name', [], [new XText($school->nick_name)]),
        new XElem('school_city', [], [new XText($school->city)]),
        new XElem('school_state', [], [new XText($school->state)]),
        new XElem('team_name', [], [new XText("")]),
      )
    );
  }
}

/**
 * DB mock.
 */
class SyncDBTestDB extends DB {

  private static $methodsCalled;
  private static $objectsSet;

  private static $season;
  private static $sailorsById;
  private static $schoolsById;
  private static $conferencesById;
  private static $settings;

  public static function init(
    Season $season,
    Array $sailorsById,
    Array $schoolsById,
    Array $conferencesById,
    Array $settings
  ) {
    self::$methodsCalled = array();
    self::$objectsSet = array();

    self::$season = $season;
    self::$sailorsById = $sailorsById;
    self::$schoolsById = $schoolsById;
    self::$conferencesById = $conferencesById;
    self::$settings = $settings;
  }

  private static function methodCalled($methodName) {
    if (!array_key_exists($methodName, self::$methodsCalled)) {
      self::$methodsCalled[$methodName] = 0;
    }
    self::$methodsCalled[$methodName] += 1;
  }

  public static function getObjectsSet(DBObject $obj) {
    $class = get_class($obj);
    return array_key_exists($class, self::$objectsSet)
      ? self::$objectsSet[$class]
      : array();
  }

  //
  // Override DB behavior
  //

  public static function createUrlSlug(
    Array $seeds,
    Closure $isSlugApprovedCallback,
    $apply_rule_c = true,
    Array $blacklist = array()
  ) {
    self::methodCalled(__METHOD__);
    return DB::createUrlSlug($seeds, $isSlugApprovedCallback, $apply_rule_c, $blacklist);
  }

  public static function g($name) {
    self::methodCalled(__METHOD__);
    return (array_key_exists($name, self::$settings))
      ? self::$settings[$name]
      : null;
  }

  public static function getConference($id) {
    self::methodCalled(__METHOD__);
    return (array_key_exists($id, self::$conferencesById))
      ? self::$conferencesById[$id]
      : null;
  }

  public static function getRegisteredSailor($id) {
    self::methodCalled(__METHOD__);
    return (array_key_exists($id, self::$sailorsById))
      ? self::$sailorsById[$id]
      : null;
  }

  public static function getSchool($id) {
    self::methodCalled(__METHOD__);
    return (array_key_exists($id, self::$schoolsById))
      ? self::$schoolsById[$id]
      : null;

  }

  public static function inactivateSchools(Season $season) {
    self::methodCalled(__METHOD__);
    if ($season != self::$season) {
      var_dump($season);
      var_dump(self::$season);
      throw new RuntimeException("Expected only the registered season to be passed.");
    }
  }

  public static function set(DBObject $obj, $update = "guess") {
    self::methodCalled(__METHOD__);
    $class = get_class($obj);
    if (!array_key_exists($class, self::$objectsSet)) {
      self::$objectsSet[$class] = array();
    }
    self::$objectsSet[$class][$obj->id] = $obj;
  }

  public static function slugify($seed, $apply_rule_c = true, Array $blacklist = array()) {
    self::methodCalled(__METHOD__);
    return DB::slugify($seed, $apply_rule_c, $blacklist);
  }

}