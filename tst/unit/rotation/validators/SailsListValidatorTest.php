<?php
namespace rotation\validators;

use \AbstractUnitTester;
use \Division;
use \Regatta;
use \SailsList;
use \School;
use \Team;
use \model\FleetRotation;

class SailsListValidatorTest extends AbstractUnitTester {

  private $testObject;
  private $rotation;

  protected function setUp() {
    $this->testObject = new SailsListValidator();
    $this->rotation = new FleetRotation();
    $this->rotation->regatta = new SailsListValidatorTestRegatta();
  }

  /**
   * @expectedException SoterException
   */
  public function testMissing() {
    $this->rotation->sails_list = null;
    $this->testObject->validateFleetRotation($this->rotation);
  }

  /**
   * @expectedException SoterException
   */
  public function testCombinedInvalid() {
    $this->rotation->regatta->scoring = Regatta::SCORING_COMBINED;
    $this->rotation->sails_list = new SailsList();
    $this->rotation->sails_list->sails = array(1, 2, 3, 4);
    $this->testObject->validateFleetRotation($this->rotation);
  }

  public function testCombinedValid() {
    $this->rotation->regatta->scoring = Regatta::SCORING_COMBINED;

    $this->rotation->sails_list = new SailsList();
    $this->rotation->sails_list->sails = array(1, 2, 3, 4, 5, 6, 7, 8, 9);
    $this->testObject->validateFleetRotation($this->rotation);

    $this->rotation->sails_list = new SailsList();
    $this->rotation->sails_list->sails = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
    $this->testObject->validateFleetRotation($this->rotation);
  }

  /**
   * @expectedException SoterException
   */
  public function testStandardInvalid() {
    $this->rotation->regatta->scoring = Regatta::SCORING_STANDARD;
    $this->rotation->sails_list = new SailsList();
    $this->rotation->sails_list->sails = array(1, 2);
    $this->testObject->validateFleetRotation($this->rotation);
  }

  public function testStandardValid() {
    $this->rotation->regatta->scoring = Regatta::SCORING_STANDARD;

    $this->rotation->sails_list = new SailsList();
    $this->rotation->sails_list->sails = array(1, 2, 3);
    $this->testObject->validateFleetRotation($this->rotation);

    $this->rotation->sails_list = new SailsList();
    $this->rotation->sails_list->sails = array(1, 2, 3, 4);
    $this->testObject->validateFleetRotation($this->rotation);
  }
}

class SailsListValidatorTestRegatta extends Regatta {

  public function getTeams(School $school = null) {
    return array(
      new Team(),
      new Team(),
      new Team(),
    );
  }

  public function getDivisions() {
    return array(
      Division::A(),
      Division::B(),
      Division::C(),
    );
  }
}