<?php
use \model\FleetRotation;
use \rotation\FrannyFleetRotationCreator;

require_once(dirname(dirname(__FILE__)) . '/AbstractUnitTester.php');

/**
 * Test the rotation creator in question.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class FrannyFleetRotationCreatorTest extends AbstractUnitTester {

  private $regatta;
  private $manager;

  protected function setUp() {
    $this->regatta = new FrannyFleetRotationCreatorRegatta();
    $this->manager = new FrannyFleetRotationCreatorRotationManager($this->regatta);
    $this->regatta->setRotationManager($this->manager);
  }

  public function testCreateRotation() {
    $rotation = new FleetRotation();
    $rotation->regatta = $this->regatta;
    $rotation->rotation_type = FleetRotation::TYPE_NONE;
    $rotation->rotation_style = FleetRotation::STYLE_FRANNY;
    $rotation->division_order = array('A', 'B');
    $rotation->races_per_set = 2;
    $rotation->sails_list = new SailsList();
    $rotation->sails_list->sails = array(1, 2, 3, 4, 5, 6);

    $testObject = new FrannyFleetRotationCreator();
    $testObject->createRotation($rotation);

    $expectedPartialRotation = array(
      '1A-1' => 1,
      '1A-2' => 2,
      '1A-3' => 3,
      '1A-4' => 4,
      '1A-5' => 5,
      '1A-6' => 6,
      '2A-1' => 1,
      '2A-2' => 2,
      '2A-3' => 3,
      '2A-4' => 4,
      '2A-5' => 5,
      '2A-6' => 6,

      '1B-1' => 4,
      '1B-2' => 5,
      '1B-3' => 6,
      '1B-4' => 1,
      '1B-5' => 2,
      '1B-6' => 3,
      '2B-1' => 4,
      '2B-2' => 5,
      '2B-3' => 6,
      '2B-4' => 1,
      '2B-5' => 2,
      '2B-6' => 3,
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
class FrannyFleetRotationCreatorRotationManager extends RotationManager {

  private $queue;
  private $commitedQueue;

  public function initQueue() {
    $this->queue = array();
  }

  public function reset(Race $race = null) {
    // no op
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
}

/**
 * Mock regatta.
 */
class FrannyFleetRotationCreatorRegatta extends Regatta {

  private $rotationManager;

  public function setRotationManager(RotationManager $manager) {
    $this->rotationManager = $manager;
  }

  public function getRotationManager() {
    return $this->rotationManager;
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
}
