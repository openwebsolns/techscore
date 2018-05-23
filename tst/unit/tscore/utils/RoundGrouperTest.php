<?php
namespace tscore\utils;

use \AbstractUnitTester;
use \Division;
use \Regatta;
use \Race;
use \Round;

class RoundGrouperTest extends AbstractUnitTester {

  /**
   * Classic team racing: two rounds grouped, 3 divisions, 3 races per flight
   */
  public function testSimpleGrouping() {
    $round1 = new Round();
    $round1->id = 1;
    $round1->title = 6;
    $round1->relative_order = 1;
    $round1->num_boats = 18;

    $round2 = new Round();
    $round2->id = 2;
    $round2->title = 6;
    $round2->relative_order = 2;
    $round2->num_boats = 18;

    $round3 = new Round();
    $round3->id = 'Ignored';
    $round3->title = 6;
    $round3->relative_order = 3;
    $round3->num_boats = 18;

    $regatta = new RoundGrouperTestRegatta();
    $regatta->prepare(3, array($round1, $round2, $round3));

    $testObject = new RoundGrouper($regatta);
    $races = $testObject->group(array($round1, $round2));

    // first flight (=3 races) from first round (with total of 18 races)
    $this->assertRace(array_shift($races), 1, 1, Division::A(), $round1);
    $this->assertRace(array_shift($races), 2, 1, Division::B(), $round1);
    $this->assertRace(array_shift($races), 3, 1, Division::C(), $round1);

    $this->assertRace(array_shift($races), 4, 2, Division::A(), $round1);
    $this->assertRace(array_shift($races), 5, 2, Division::B(), $round1);
    $this->assertRace(array_shift($races), 6, 2, Division::C(), $round1);

    $this->assertRace(array_shift($races), 7, 3, Division::A(), $round1);
    $this->assertRace(array_shift($races), 8, 3, Division::B(), $round1);
    $this->assertRace(array_shift($races), 9, 3, Division::C(), $round1);

    // second flight (=3 races) from second round (with total of 18 races)
    $this->assertRace(array_shift($races), 19, 4, Division::A(), $round2);
    $this->assertRace(array_shift($races), 20, 4, Division::B(), $round2);
    $this->assertRace(array_shift($races), 21, 4, Division::C(), $round2);

    $this->assertRace(array_shift($races), 22, 5, Division::A(), $round2);
    $this->assertRace(array_shift($races), 23, 5, Division::B(), $round2);
    $this->assertRace(array_shift($races), 24, 5, Division::C(), $round2);

    $this->assertRace(array_shift($races), 25, 6, Division::A(), $round2);
    $this->assertRace(array_shift($races), 26, 6, Division::B(), $round2);
    $this->assertRace(array_shift($races), 27, 6, Division::C(), $round2);

    // third flight is from round 1 (i.e., NOT from round 3)
    $this->assertRace(array_shift($races), 10, 7, Division::A(), $round1);
  }

  /**
   * 2 on 2: Two divisions, two rounds, flight sizes of 2 and 3
   */
  public function testDifferentFlightSizes() {
    $round1 = new Round();
    $round1->id = 1;
    $round1->title = 2;
    $round1->relative_order = 1;
    $round1->num_boats = 8;

    $round2 = new Round();
    $round2->id = 2;
    $round2->title = 3;
    $round2->relative_order = 2;
    $round2->num_boats = 12;

    $regatta = new RoundGrouperTestRegatta();
    $regatta->prepare(2, array($round1, $round2));

    $testObject = new RoundGrouper($regatta);
    $races = $testObject->group(array($round1, $round2));

    // first flight (=2 races) from first round (with total of 4 races)
    $this->assertRace(array_shift($races), 1, 1, Division::A(), $round1);
    $this->assertRace(array_shift($races), 2, 1, Division::B(), $round1);

    $this->assertRace(array_shift($races), 3, 2, Division::A(), $round1);
    $this->assertRace(array_shift($races), 4, 2, Division::B(), $round1);

    // second flight (=3 races) from second round (with total of 6 races)
    $this->assertRace(array_shift($races), 5, 3, Division::A(), $round2);
    $this->assertRace(array_shift($races), 6, 3, Division::B(), $round2);

    $this->assertRace(array_shift($races), 7, 4, Division::A(), $round2);
    $this->assertRace(array_shift($races), 8, 4, Division::B(), $round2);

    $this->assertRace(array_shift($races), 9, 5, Division::A(), $round2);
    $this->assertRace(array_shift($races), 10, 5, Division::B(), $round2);

    $this->assertEmpty($races);
  }

  /**
   * "Match racing": one division, flight size of 1
   */
  public function testDifferentNumberOfFlights() {
    $round1 = new Round();
    $round1->id = 1;
    $round1->title = 2;
    $round1->relative_order = 1;
    $round1->num_boats = 2;

    $round2 = new Round();
    $round2->id = 2;
    $round2->title = 3;
    $round2->relative_order = 2;
    $round2->num_boats = 2;

    $round3 = new Round();
    $round3->id = 3;
    $round3->title = 1;
    $round3->relative_order = 2;
    $round3->num_boats = 22;

    $regatta = new RoundGrouperTestRegatta();
    $regatta->prepare(1, array($round1, $round2, $round3));

    $testObject = new RoundGrouper($regatta);
    $races = $testObject->group(array($round1, $round2, $round3));

    $this->assertRace(array_shift($races), 1, 1, Division::A(), $round1);
    $this->assertRace(array_shift($races), 3, 2, Division::A(), $round2);
    $this->assertRace(array_shift($races), 6, 3, Division::A(), $round3);

    $this->assertRace(array_shift($races), 2, 4, Division::A(), $round1);
    $this->assertRace(array_shift($races), 4, 5, Division::A(), $round2);
    // round 2 and 3 exhausted

    $this->assertRace(array_shift($races), 5, 6, Division::A(), $round2);
  }

  private function assertRace(Race $race, $id, $number, Division $div, Round $round) {
    $this->assertEquals($id, $race->id, 'Invalid ID');
    $this->assertEquals($number, $race->number, 'Invalid number');
    $this->assertEquals($div, $race->division, 'Invalid division');
    $this->assertEquals($round, $race->round, 'Invalid round');
  }
}

class RoundGrouperTestRegatta extends Regatta {

  private $divisions;
  private $races;

  public function prepare($numDivisions, Array $rounds) {
    $this->divisions = Division::listOfSize($numDivisions);

    $this->races = array();
    $num = 0;
    $id = 0;
    // round name indicates number of races for this test
    foreach ($rounds as $round) {
      for ($i = 0; $i < $round->title; $i++) {
        $num++;
        foreach ($this->divisions as $division) {
          $id++;
          $race = new Race();
          $race->id = $id;
          $race->number = $num;
          $race->division = $division;
          $race->round = $round;
          $race->regatta = $this;
          $this->races[] = $race;
        }
      }
    }
  }

  public function getDivisions() {
    return $this->divisions;
  }

  public function getFleetSize() {
    return 2 * count($this->divisions);
  }

  public function getRacesInRound(Round $round, Division $division = null) {
    $races = array();
    foreach ($this->races as $race) {
      if ($race->round == $round && ($division === null || $race->division == $division)) {
        $races[] = $race;
      }
    }
    return $races;
  }

  public function getRace(Division $div, $num) {
    foreach ($this->races as $race) {
      if ($race->division == $div && $race->number == $num) {
        return $race;
      }
    }
    return null;
  }
}
