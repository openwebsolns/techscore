<?php
namespace users\membership;

use \AbstractUnitTester;
use \Account;
use \DataCreator;
use \DB;
use \DBM;
use \DBObject;
use \Permission;
use \School;
use \Session;
use \SimpleXMLElement;
use \UserPaneHelper;
use \XSubmitDelete;
use \users\utils\burgees\AssociateBurgeesToSchoolHelper;
use \xml5\XExternalA;

require_once('xml5/TS.php');
require_once('xml5/Session.php');

/**
 * Test what is displayed based on permissions.
 *
 * @author Dayan Paez
 * @version 2015-11-18
 */
class SchoolsPaneTest extends AbstractUnitTester {

  private $paneHelper;
  private $dataCreator;

  protected function setUp() {
    session_id("fake-session");
    Session::init();
    DB::setDbm(new SchoolsPaneTestDBM());
    SchoolsPaneTestDBM::resetForTest();

    $this->paneHelper = new UserPaneHelper();
    $this->dataCreator = new DataCreator();
  }

  public function testNoAccessLanding() {
    $user = new SchoolsPaneTestAccount();
    $testObject = new SchoolsPane($user);
    $root = $this->paneHelper->getPaneHtml($testObject, array());
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "All Schools" only with no school
    $ports = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(1, count($ports));
    $port = $ports[0];
    $this->paneHelper->autoregisterXpathNamespace($port);
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_LIST, $title);

    $tables = $port->xpath('//html:table');
    $this->assertEmpty($tables);
  }

  public function testMultiSchoolAccessNoEdit() {
    $school1 = $this->dataCreator->createSchool();
    $school1->url = 'url';
    $school2 = $this->dataCreator->createSchool();
    $schools = array($school1, $school2);

    $user = new SchoolsPaneTestAccount();
    $user->setSchools($schools);

    $args = array('q' => "SomeGenericSearchTerm");
    $testObject = new SchoolsPane($user);
    $root = $this->paneHelper->getPaneHtml($testObject, $args);
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "All Schools" only with no school
    $ports = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(1, count($ports));
    $port = $ports[0];
    $this->paneHelper->autoregisterXpathNamespace($port);
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_LIST, $title);

    $tables = $port->xpath('html:table');
    $this->assertEquals(1, count($tables));
    $table = $tables[0];
    $this->paneHelper->autoregisterXpathNamespace($table);
    $rows = $table->xpath('html:tbody/html:tr');
    $this->assertEquals(count($schools), count($rows));

    // Proof that only the school's URL link is included
    $links = $table->xpath(sprintf('html:tbody//html:a[@class="%s"]', XExternalA::CLASSNAME));
    $this->assertEquals(1, count($links));
  }

  public function testSearchNoResults() {
    $user = new SchoolsPaneTestAccount();

    $searchTerm = "SomeGenericSearchTerm";
    $args = array('q' => $searchTerm);
    $testObject = new SchoolsPane($user);
    $root = $this->paneHelper->getPaneHtml($testObject, $args);
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "All Schools"
    $ports = $root->xpath('//html:div[@class="port"]');
    $port = $ports[0];
    $this->paneHelper->autoregisterXpathNamespace($port);
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_LIST, $title);

    $inputs = $port->xpath('.//html:input[@name="q"]');
    $this->assertEquals(1, count($inputs));
    $input = $inputs[0];
    $this->assertEquals($searchTerm, (string) $input['value']);
  }

  public function testListSchoolsThatUserCanEdit() {
    $school = $this->dataCreator->createSchool();

    $user = new SchoolsPaneTestAccount();
    $user->setSchools(array($school));
    $user->setPermissions(array(Permission::EDIT_SCHOOL_LOGO));

    $testObject = new SchoolsPane($user);
    $root = $this->paneHelper->getPaneHtml($testObject, array());
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "All Schools" only with no school
    $ports = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(1, count($ports));
    $port = $ports[0];
    $this->paneHelper->autoregisterXpathNamespace($port);
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_LIST, $title);

    $links = $port->xpath('//html:tbody//html:a');
    $this->assertEquals(
      2, // one to edit, and one to view "roster"
      count($links),
      print_r($links, true)
    );
    $link = $links[0];
    $this->assertContains($school->id, (string) $link['href']);
  }

  //
  // getSchoolById
  //

  public function testEditInvalidSchoolId() {
    $user = new SchoolsPaneTestAccount();
    $args = array(SchoolsPane::EDIT_KEY => "BadId");
    $testObject = new SchoolsPane($user);
    $root = $this->paneHelper->getPaneHtml($testObject, $args);
    $this->paneHelper->autoregisterXpathNamespace($root);

    $ports = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(1, count($ports));
    $port = $ports[0];
    $this->paneHelper->autoregisterXpathNamespace($port);
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_LIST, $title);
  }

  public function testEditSchoolWithNoAccessId() {
    $school = $this->dataCreator->createSchool();
    SchoolsPaneTestDBM::setSchoolsById(
      array($school->id => $school)
    );

    $user = new SchoolsPaneTestAccount();
    $args = array(SchoolsPane::EDIT_KEY => $school->id);
    $testObject = new SchoolsPane($user);
    $root = $this->paneHelper->getPaneHtml($testObject, $args);
    $this->paneHelper->autoregisterXpathNamespace($root);

    $ports = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(1, count($ports));
    $port = $ports[0];
    $this->paneHelper->autoregisterXpathNamespace($port);
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_LIST, $title);
  }

  public function testCanEditSyncedSchool() {
    $root = DB::getRootAccount();
    $school = $this->dataCreator->createSchool();
    $school->created_by = $root->id;
    $school->url = 'url';
    $school->city = "TestCity";
    $school->state = 'MA';

    SchoolsPaneTestDBM::setSchoolsById(
      array($school->id => $school)
    );

    $user = new SchoolsPaneTestAccount();
    $user->setSchools(array($school));
    $user->setPermissions(array(Permission::EDIT_SCHOOL));

    $args = array(SchoolsPane::EDIT_KEY => $school->id);
    $testObject = new SchoolsPane($user);
    $root = $this->paneHelper->getPaneHtml($testObject, $args);
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "Edit school" only (no other ports)
    $ports = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(1, count($ports));
    $port = $ports[0];
    $this->paneHelper->autoregisterXpathNamespace($port);
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_EDIT, $title);

    // TODO: test edit fields?
  }

  public function testCanEditAndDeleteNonSyncedSchool() {
    $school = $this->dataCreator->createSchool();
    $school->created_by = "Non-Root";
    $school->url = 'url';
    $school->city = "TestCity";
    $school->state = 'MA';

    SchoolsPaneTestDBM::setSchoolsById(
      array($school->id => $school)
    );

    $user = new SchoolsPaneTestAccount();
    $user->setSchools(array($school));
    $user->setPermissions(array(Permission::ADD_SCHOOL));

    $args = array(SchoolsPane::EDIT_KEY => $school->id);
    $testObject = new SchoolsPane($user);
    $root = $this->paneHelper->getPaneHtml($testObject, $args);
    
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "Edit school"
    $ports = $root->xpath('//html:div[@class="port"]');
    $this->assertNotEmpty($ports);
    $port = $ports[0];
    $this->paneHelper->autoregisterXpathNamespace($port);
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_EDIT, $title);

    $inputs = $port->xpath(
      sprintf('//html:input[@class="%s"]', XSubmitDelete::CLASSNAME)
    );
    $this->assertEquals(1, count($inputs));
  }

  public function testCanEditTeamNames() {
    $school = $this->dataCreator->createSchool();

    SchoolsPaneTestDBM::setSchoolsById(
      array($school->id => $school)
    );

    $user = new SchoolsPaneTestAccount();
    $user->setSchools(array($school));
    $user->setPermissions(array(Permission::EDIT_TEAM_NAMES));

    $args = array(SchoolsPane::EDIT_KEY => $school->id);
    $testObject = new SchoolsPane($user);
    $root = $this->paneHelper->getPaneHtml($testObject, $args);
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "Team names" only
    $ports = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(2, count($ports));
    $port = $ports[0];
    $this->paneHelper->autoregisterXpathNamespace($port);
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_EDIT, $title);
    $inputs = $port->xpath('/html:input[@type="submit"]');
    $this->assertEmpty($inputs, print_r($inputs, true));

    $port = $ports[1];
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_SQUAD_NAMES, $title);

    // TODO: test edit fields?
  }

  public function testCanEditLogo() {
    $school = $this->dataCreator->createSchool();

    SchoolsPaneTestDBM::setSchoolsById(
      array($school->id => $school)
    );

    $user = new SchoolsPaneTestAccount();
    $user->setSchools(array($school));
    $user->setPermissions(array(Permission::EDIT_SCHOOL_LOGO));

    $args = array(SchoolsPane::EDIT_KEY => $school->id);
    $testObject = new SchoolsPane($user);
    $root = $this->paneHelper->getPaneHtml($testObject, $args);
    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "Team names" only
    $ports = $root->xpath('//html:div[@class="port"]');
    $this->assertEquals(2, count($ports));
    $port = $ports[0];
    $this->paneHelper->autoregisterXpathNamespace($port);
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_EDIT, $title);
    $inputs = $port->xpath('/html:input[@type="submit"]');
    $this->assertEmpty($inputs, print_r($inputs, true));

    $port = $ports[1];
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_LOGO, $title);

    // TODO: test edit fields?
  }

  public function testNoSchoolCanAdd() {
    $user = new SchoolsPaneTestAccount();
    $user->setSchools(array());
    $user->setPermissions(array(Permission::ADD_SCHOOL));

    $testObject = new SchoolsPane($user);
    $root = $this->paneHelper->getPaneHtml($testObject, array());

    $this->paneHelper->autoregisterXpathNamespace($root);

    // Expect "All Schools" only with no school
    $ports = $root->xpath('//html:div[@class="port collapsible"]');
    $this->assertEquals(1, count($ports));
    $port = $ports[0];
    $this->paneHelper->autoregisterXpathNamespace($port);
    $title = $this->paneHelper->getPortTitle($port);
    $this->assertEquals(SchoolsPane::PORT_ADD, $title);
  }

  //
  // POST
  //

  public function testProcessBurgeeNoUpload() {
    $school = $this->dataCreator->createSchool();
    SchoolsPaneTestDBM::setSchoolsById(
      array($school->id => $school)
    );

    $user = new SchoolsPaneTestAccount();
    $user->setSchools(array($school));
    $user->setPermissions(array(Permission::EDIT_SCHOOL_LOGO));

    $testObject = new SchoolsPane($user);
    $testObject->process(
      array(
        SchoolsPane::SUBMIT_SET_LOGO => "Set Logo",
        SchoolsPane::FIELD_BURGEE => array(),
        SchoolsPane::FIELD_SCHOOL_ID => $school->id,
      )
    );

    // TODO: verify
  }

  public function testProcessBurgeeDelegatesToHelper() {
    $helper = new EditSchoolProcessorTestAssociateBurgeesHelper();
    $school = $this->dataCreator->createSchool();
    SchoolsPaneTestDBM::setSchoolsById(
      array($school->id => $school)
    );

    $user = new SchoolsPaneTestAccount();
    $user->setSchools(array($school));
    $user->setPermissions(array(Permission::EDIT_SCHOOL_LOGO));

    $testObject = new SchoolsPane($user);
    $testObject->setAssociateBurgeesHelper($helper);

    $filename = "TestFilename";
    $post = array(
      'tmp_name' => $filename,
      'name' => "Ignored Value",
      'size' => 1,
      'error' => 0
    );
    $testObject->process(
      array(
        SchoolsPane::SUBMIT_SET_LOGO => "Set Logo",
        SchoolsPane::FIELD_BURGEE => $post,
        SchoolsPane::FIELD_SCHOOL_ID => $school->id,
      )
    );

    // Verify
    $calledArgs = $helper->getCalledArgs();
    $this->assertEquals(1, count($calledArgs));
    $this->assertSame($school, $calledArgs[0]['school']);
    $this->assertEquals($filename, $calledArgs[0]['filename']);
  }

}

/**
 * Mock DBM.
 */
class SchoolsPaneTestDBM extends DBM {

  private static $schoolsById = array();

  public static function resetForTest() {
    self::setSchoolsById(array());
  }

  public static function setSchoolsById(Array $schools) {
    self::$schoolsById = $schools;
  }

  public static function get(DBObject $obj, $id) {
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
class SchoolsPaneTestAccount extends Account {

  private $methodsCalled = array();

  private $permissions = array();
  private $schools = array();

  public function setPermissions(Array $permissions) {
    $this->permissions = $permissions;
  }

  public function canAny(Array $perms) {
    $this->methodsCalled[] = __METHOD__;
    foreach ($perms as $permission) {
      if (in_array($permission, $this->permissions)) {
        return true;
      }
    }
    return false;
  }

  public function can($perm) {
    $this->methodsCalled[] = __METHOD__;
    if (parent::can($perm)) {
      return true;
    }
    return $this->canAny(array($perm));
  }

  public function setSchools(Array $schools) {
    $this->schools = $schools;
  }

  public function getSchools(Conference $conf = null, $effective = true, $active = true) {
    $this->methodsCalled[] = __METHOD__;
    return $this->schools;
  }

  public function searchSchools($qry, Conference $conf = null, $effective = true, $active = true) {
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

/**
 * Mock AssociateBurgeesHelper.
 */
class EditSchoolProcessorTestAssociateBurgeesHelper extends AssociateBurgeesToSchoolHelper {

  private $calledArgs = array();

  public function setBurgee(Account $account, School $school, $filename) {
    $this->calledArgs[] = array(
      'account' => $account,
      'school' => $school,
      'filename' => $filename
    );
  }

  public function getCalledArgs() {
    return $this->calledArgs;
  }
}