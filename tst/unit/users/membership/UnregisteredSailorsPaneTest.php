<?php
namespace users\membership;

use \SimpleXMLElement;

use \AbstractUnitTester;
use \Account;
use \DataCreator;
use \DB;
use \DBM;
use \DBObject;
use \Sailor;
use \School;
use \Session;
use \UserPaneHelper;

use \XValid;

require_once('xml5/TS.php');
require_once('xml5/Session.php');

/**
 * Test what is displayed and the processing.
 *
 * @author Dayan Paez
 * @version 2015-12-02
 */
class UnregisteredSailorsPaneTest extends AbstractUnitTester {

  private $paneHelper;
  private $dataCreator;

  protected function setUp() {
    session_id("fake-session");
    Session::init();
    DB::setDbm(new UnregisteredSailorsPaneTestDBM());
    UnregisteredSailorsPaneTestDBM::resetForTest();

    $this->paneHelper = new UserPaneHelper();
    $this->dataCreator = new DataCreator();
  }

  public function testNoSchoolsWithPendingSailors() {
    $account = new UnregisteredSailorsPaneTestAccount();
    $account->setSchools(array());
    $testObject = new UnregisteredSailorsPane($account);
    $root = $this->paneHelper->getPaneHtml($testObject, array());
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "Congratulations" pane only
    $elems = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(0, count($elems));
    $elems = $root->xpath(sprintf('//html:p[@class="%s"]', XValid::CLASSNAME));
    $this->assertEquals(1, count($elems));
  }

  public function testOneSchoolNoChoosePort() {
    $school = $this->dataCreator->createSchool();
    $account = new UnregisteredSailorsPaneTestAccount();
    $account->setSchools(array($school));
    $testObject = new UnregisteredSailorsPane($account);
    $root = $this->paneHelper->getPaneHtml($testObject, array());
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "Congratulations" pane only
    $elems = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(1, count($elems));
  }

  public function testInvalidSchoolRequested() {
    $validSchool = $this->dataCreator->createSchool();
    $errorSchool = $this->dataCreator->createSchool();
    UnregisteredSailorsPaneTestDBM::setSchoolsById(
      array(
        $validSchool->id => $validSchool,
        $errorSchool->id => $errorSchool,
      )
    );
    $account = new UnregisteredSailorsPaneTestAccount();
    $account->setSchools(array($validSchool));
    $testObject = new UnregisteredSailorsPane($account);

    $args = array(UnregisteredSailorsPane::KEY_SCHOOL => $errorSchool->id);
    $root = $this->paneHelper->getPaneHtml($testObject, $args);
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "Congratulations" pane only
    $ports = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(1, count($ports));
    $port = $ports[0];
    $title = $this->paneHelper->getPortTitle($port);
    $expectedTitle = sprintf(UnregisteredSailorsPane::PORT_MERGE, $validSchool);
    $this->assertEquals($expectedTitle, $title);
  }

  public function testMultiSchoolSecondChosen() {
    $school1 = $this->dataCreator->createSchool();
    $school2 = $this->dataCreator->createSchool();
    UnregisteredSailorsPaneTestDBM::setSchoolsById(
      array(
        $school1->id => $school1,
        $school2->id => $school2,
      )
    );
    $account = new UnregisteredSailorsPaneTestAccount();
    $account->setSchools(array($school1, $school2));
    $testObject = new UnregisteredSailorsPane($account);

    $args = array(UnregisteredSailorsPane::KEY_SCHOOL => $school2->id);
    $root = $this->paneHelper->getPaneHtml($testObject, $args);
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "Congratulations" pane only
    $ports = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(2, count($ports));
    $port = $ports[0];
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(UnregisteredSailorsPane::PORT_CHOOSE, $title);

    $port = $ports[1];
    $title = $this->paneHelper->getPortTitle($port);
    $expectedTitle = sprintf(UnregisteredSailorsPane::PORT_MERGE, $school2);
    $this->assertEquals($expectedTitle, $title);
  }

}

/**
 * Mock DBM.
 */
class UnregisteredSailorsPaneTestDBM extends DBM {

  private static $schoolsById = array();
  private static $sailorsById = array();

  public static function resetForTest() {
    self::setSailorsById(array());
    self::setSchoolsById(array());
  }

  public static function setSailorsById(Array $sailors) {
    self::$sailorsById = $sailors;
  }

  public static function setSchoolsById(Array $schools) {
    self::$schoolsById = $schools;
  }

  public static function get(DBObject $obj, $id) {
    if ($obj instanceof Sailor) {
      return array_key_exists($id, self::$sailorsById)
        ? self::$sailorsById[$id]
        : null;
    }
    if ($obj instanceof School) {
      return array_key_exists($id, self::$schoolsById)
        ? self::$schoolsById[$id]
        : null;
    }
    return parent::get($obj, $id);
  }
}

/**
 * Mock user.
 */
class UnregisteredSailorsPaneTestAccount extends Account {

  private $methodsCalled = array();

  private $schools = array();

  public function setSchools(Array $schools) {
    $this->schools = $schools;
  }

  public function getSchoolsWithUnregisteredSailors(Conference $conf = null, $effective = true, $active = true) {
    $this->methodsCalled[] = __METHOD__;
    return $this->schools;
  }

  public function hasSchool(School $school) {
    $this->methodsCalled[] = __METHOD__;
    return in_array($school, $this->schools);
  }

  public function getMethodsCalled() {
    return $this->methodsCalled;
  }
}
