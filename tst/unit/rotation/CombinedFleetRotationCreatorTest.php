<?php
use \model\FleetRotation;
use \rotation\CombinedFleetRotationCreator;

require_once(dirname(dirname(__FILE__)) . '/AbstractUnitTester.php');

/**
 * Test the rotation creator in question.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class CombinedFleetRotationCreatorTest extends AbstractUnitTester {

  private $regatta;
  private $manager;

  protected function setUp() {
    $this->regatta = new CombinedFleetRotationCreatorRegatta();
    $this->manager = new CombinedFleetRotationCreatorRotationManager($this->regatta);
    $this->regatta->setRotationManager($this->manager);
  }

  public function testCreateRotation() {
    $rotation = new FleetRotation();
    $rotation->regatta = $this->regatta;
    $rotation->rotation_type = FleetRotation::TYPE_STANDARD;
    $rotation->rotation_style = FleetRotation::STYLE_SIMILAR;
    $rotation->races_per_set = 1;
    $rotation->sails_list = new SailsList();
    $rotation->sails_list->sails =
      array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

    $testObject = new CombinedFleetRotationCreator();
    $testObject->createRotation($rotation);

    $expectedPartialRotation = array(
      '1A-1' => 1,
      '1A-2' => 2,
      '1A-3' => 3,
      '1A-4' => 4,
      '1A-5' => 5,
      '1A-6' => 6,
      '1B-1' => 7,
      '1B-2' => 8,
      '1B-3' => 9,
      '1B-4' => 10,
      '1B-5' => 11,
      '1B-6' => 12,

      '2A-1' => 2,
      '2A-2' => 3,
      '2A-3' => 4,
      '2A-4' => 5,
      '2A-5' => 6,
      '2A-6' => 7,
      '2B-1' => 8,
      '2B-2' => 9,
      '2B-3' => 10,
      '2B-4' => 11,
      '2B-5' => 12,
      '2B-6' => 1,
    );

    $queue = $this->manager->getQueue();
    $totalEntries =
      2    // number of divisions
      * 5  // number of races per division
      * 6; // number of teams
    $this->assertEquals($totalEntries, count($queue));
    foreach ($expectedPartialRotation as $hash => $sailNumber) {
      $this->assertEquals($sailNumber, $queue[$hash]->sail);
    }
  }
}

/**
 * Mock rotation manager.
 */
class CombinedFleetRotationCreatorRotationManager extends RotationManager {

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
class CombinedFleetRotationCreatorRegatta extends Regatta {

  private $rotationManager;

  public function setRotationManager(RotationManager $manager) {
    $this->rotationManager = $manager;
  }

  public function getRotationManager() {
    return $this->rotationManager;
  }

  public function getDivisions() {
    return array(Division::A(), Division::B());
  }

  public function getTeams(School $school = null) {
    $list = array();
    for ($i = 0; $i < 6; $i++) {
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

  public function getRace(Division $division, $number) {
    $race = new Race();
    $race->division = $division;
    $race->number = $number;
    return $race;
  }
}
