<?php
use \model\FleetRotation;
use \rotation\NavyStyleRacesRotator;
use \rotation\SimilarStyleRacesRotator;
use \rotation\StandardFleetRotationCreator;

require_once(dirname(dirname(__FILE__)) . '/AbstractUnitTester.php');

/**
 * Test the rotation creator in question.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class StandardFleetRotationCreatorTest extends AbstractUnitTester {

  private $regatta;
  private $manager;

  protected function setUp() {
    $this->regatta = new StandardFleetRotationCreatorRegatta();
    $this->manager = new StandardFleetRotationCreatorRotationManager($this->regatta);
    $this->regatta->setRotationManager($this->manager);
  }

  public function testCreateRotation() {
    $rotation = new FleetRotation();
    $rotation->regatta = $this->regatta;
    $rotation->rotation_type = FleetRotation::TYPE_NONE;
    $rotation->rotation_style = FleetRotation::STYLE_SIMILAR;
    $rotation->division_order = array('B', 'A');
    $rotation->races_per_set = 2;
    $rotation->sails_list = new SailsList();
    $rotation->sails_list->sails = array(1, 2, 3, 4);

    $testObject = new StandardFleetRotationCreator();
    $testObject->createRotation($rotation);

    $queue = $this->manager->getQueue();
    $totalEntries =
      2    // number of divisions
      * 5  // number of races per division
      * 4; // number of teams
    $this->assertEquals($totalEntries, count($queue));
    $expectedSailNumbers = array();
    foreach (array(1, 2, 3, 4, 5) as $raceNumber) {
      foreach (array(Division::A(), Division::B()) as $div) {
        foreach (array(1, 2, 3, 4) as $team) {
          $hash = sprintf('%s%s-%s', $raceNumber, $div, $team);
          $expectedSailNumbers[$hash] = $team;
        }
      }
    }
    foreach ($queue as $sail) {
      $hash = sprintf('%s-%s', $sail->race, $sail->team->name);
      $this->assertEquals($expectedSailNumbers[$hash], $sail->sail);
      unset($expectedSailNumbers[$hash]);
    }
  }

  public function testGetRacesRotator() {
    $testObject = new StandardFleetRotationCreator();

    $rotation = new FleetRotation();
    $rotation->regatta = $this->regatta;
    $rotation->division_order = array('A');

    $rotation->rotation_style = FleetRotation::STYLE_SIMILAR;
    $rotator = $testObject->getRacesRotator($rotation);
    $this->assertTrue($rotator instanceof SimilarStyleRacesRotator);

    $rotation->rotation_style = FleetRotation::STYLE_NAVY;
    $rotator = $testObject->getRacesRotator($rotation);
    $this->assertTrue($rotator instanceof NavyStyleRacesRotator);
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testGetRacesRotatorInvalid() {
    $testObject = new StandardFleetRotationCreator();

    $rotation = new FleetRotation();
    $rotation->regatta = $this->regatta;
    $rotation->division_order = array('A');

    $rotation->rotation_style = FleetRotation::STYLE_FRANNY;
    $testObject->getRacesRotator($rotation);
  }

}

/**
 * Mock rotation manager.
 */
class StandardFleetRotationCreatorRotationManager extends RotationManager {

  private $queue;
  private $commitedQueue;

  public function initQueue() {
    $this->queue = array();
  }

  public function queue(Sail $sail) {
    $hash = sprintf('%s-%s', $sail->race, $sail->team->name);
    $this->queue[$hash] = $sail;
  }

  public function commit() {
    $this->commitedQueue = $this->queue;
  }

  public function getQueue() {
    return $this->commitedQueue;
  }

  public function reset(Race $race = null) {
    // no op
  }
}

/**
 * Mock regatta.
 */
class StandardFleetRotationCreatorRegatta extends Regatta {

  private $rotationManager;

  public function setRotationManager(RotationManager $manager) {
    $this->rotationManager = $manager;
  }

  public function getRotationManager() {
    return $this->rotationManager;
  }

  public function getTeams(School $school = null) {
    $list = array();
    for ($i = 0; $i < 4; $i++) {
      $team = new Team();
      $team->school = new School();
      $team->name = $i + 1;
      $list[] = $team;
    }
    return $list;
  }

  public function getRaces(Division $div = null) {
    $list = array();
    for ($i = 0; $i < 5; $i++) {
      $race = new Race();
      $race->number = ($i + 1);
      $race->division = $div;
      $list[] = $race;
    }
    return $list;
  }
}