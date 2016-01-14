<?php
namespace xml5;

use \AbstractUnitTester;
use \Account;
use \Conference;
use \Sailor;
use \School;
use \utils\SailorSearcher;

require_once('xml5/TS.php');

/**
 * Test the sailor search form creator.
 *
 * @author Dayan Paez
 * @version 2015-11-23
 */
class SailorPageWhizCreatorTest extends AbstractUnitTester {

  protected function setUp() {

  }

  public function testEmpty() {
    $query = "QueryString";
    $account = new SailorPageWhizCreatorTestAccount();
    $args = array(SailorSearcher::FIELD_QUERY => "Query");
    $numPerPage = 10;
    $action = "Action";
    $testObject = new SailorPageWhizCreator(
      $account,
      $args,
      $numPerPage,
      $action
    );

    $form = $testObject->getFilterForm();
    $this->assertNull($form);
  }

  public function testNonEmptyFilter() {
    $school = new School();
    $account = new SailorPageWhizCreatorTestAccount();
    $account->setSchools(array($school));

    $gender = Sailor::FEMALE;
    $args = array(SailorSearcher::FIELD_GENDER => $gender);
    $numPerPage = 10;
    $action = "Action";
    $testObject = new SailorPageWhizCreator(
      $account,
      $args,
      $numPerPage,
      $action
    );

    $form = $testObject->getFilterForm();
    $this->assertNotNull($form);
  }
}

/**
 * Mock account.
 */
class SailorPageWhizCreatorTestAccount extends Account {

  private $schools;

  public function setSchools(Array $schools) {
    $this->schools = $schools;
  }

  public function getSchools(Conference $conf = null, $effective = true, $active = true) {
    return $this->schools;
  }

}