<?php
namespace scripts;

use \AbstractUnitTester;
use \Closure;
use \Conf;
use \Conference;
use \DateTime;
use \DB;
use \DBExpression;
use \DBMSetterInterceptor;
use \DBObject;
use \RuntimeException;
use \STN;
use \School;
use \Season;
use \UpdateSchoolRequest;
use \XDoc;
use \XElem;
use \XText;

require_once('xml5/XmlLib.php');
require_once(dirname(__DIR__) . '/DBMSetterInterceptor.php');

/**
 * Test the synchronicity of the database.
 *
 * @author Dayan Paez
 * @version 2016-03-04
 */
class SyncDBTest extends AbstractUnitTester {

  private $testObject;
  private $file;

  protected function setUp() {
    $this->testObject = new SyncDB();
    $this->testObject->setCore(new SyncDBTestDB());
    SyncDBTestDB::setDbm(new DBMSetterInterceptor());
    DBMSetterInterceptor::init();

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

    $formerlyInactiveSchool = new School();
    $formerlyInactiveSchool->id = 5;
    $formerlyInactiveSchool->name = "Was Invalid";
    $formerlyInactiveSchool->conference = $conference;
    $formerlyInactiveSchool->nick_name = "Was Invalid";
    $formerlyInactiveSchool->created_by = Conf::$USER->id;
    $formerlyInactiveSchool->inactive = new DateTime();
    $formerlyInactiveSchool->url = 'was-invalid';

    $xml = new XDoc(
      'schoollist', [],
      array(
        $this->schoolToXml($previouslySyncedSchool),
        $this->schoolToXml($manuallyCreatedSchool),
        $this->schoolToXml($newSchool),
        $this->schoolToXml($invalidConferenceSchool),
        $this->schoolToXml($formerlyInactiveSchool),
      )
    );

    file_put_contents($this->file, $xml->toXml());

    $season = new Season();
    $sailors = array();
    $schools = array(
      $previouslySyncedSchool->id => $previouslySyncedSchool,
      $manuallyCreatedSchool->id  => $manuallyCreatedSchool,
      $formerlyInactiveSchool->id => $formerlyInactiveSchool,
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
    $schoolsSet = DBMSetterInterceptor::getObjectsSet(new School());
    $this->assertEquals(4, count($schoolsSet), "Schools that should be saved to DB.");

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

    // Formerly inactive is now active
    $wasInactiveSchool = array_shift($schoolsSet);
    $this->assertSame($formerlyInactiveSchool, $wasInactiveSchool);
    $this->assertNull($formerlyInactiveSchool->inactive);

    // Verify that UpdateSchoolRequests were created for URL changes
    $updateRequests = DBMSetterInterceptor::getObjectsSet(new UpdateSchoolRequest());
    $this->assertEquals(2, count($updateRequests), "Expected number of UpdateSchoolRequests.");
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

    self::$season = $season;
    self::$sailorsById = $sailorsById;
    self::$schoolsById = $schoolsById;
    self::$conferencesById = $conferencesById;
    self::$settings = $settings;

    //self::setDbm(
  }

  private static function methodCalled($methodName) {
    if (!array_key_exists($methodName, self::$methodsCalled)) {
      self::$methodsCalled[$methodName] = 0;
    }
    self::$methodsCalled[$methodName] += 1;
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
      throw new RuntimeException("Expected only the registered season to be passed.");
    }
  }

  public static function slugify($seed, $apply_rule_c = true, Array $blacklist = array()) {
    self::methodCalled(__METHOD__);
    return DB::slugify($seed, $apply_rule_c, $blacklist);
  }

}