<?php
use \data\FleetScoresTableCreator;

require_once(dirname(dirname(__FILE__)) . '/AbstractUnitTester.php');

/**
 * Test the "full report" table in a fleet regatta.
 *
 * @author Dayan Paez
 * @version 2015-10-29
 */
class FleetScoresTableCreatorTest extends AbstractUnitTester {

  public function testDivisionPenaltyAdded() {
    $regatta = new FleetScoresTableCreatorTestRegatta();
    $testObject = new FleetScoresTableCreator($regatta);
    $table = $testObject->getScoreTable();
    $this->assertInstanceOf('XTable', $table);

    $xml = new SimpleXMLElement($table->toXML());
    $rows = $xml->xpath('//tbody/tr');
    $this->assertEquals(2, count($rows));

    $row = $rows[0];
    $cells = $row->xpath('td');
    $this->assertEquals("Team 1", (string) $cells[4]);
    $this->assertEquals("1", (string) $cells[5]);
    $this->assertEquals("MRP", (string) $cells[6]);
    $this->assertEquals("2", (string) $cells[9]);
  }

}

/**
 * Mock regatta with the following scores:
 *
 * | Team   | A | Pen. | B | B Pen. | Total |
 * |--------+---+------+---+--------+-------|
 * | Team 1 | 1 | MRP  | 1 |        | 22    |
 * | Team 2 | 2 |      | 2 |        | 4     |
 *
 */
class FleetScoresTableCreatorTestRegatta extends Regatta {

  public function getDivisions() {
    return array(Division::A(), Division::B());
  }

  public function getRankedTeams(School $school = null) {
    $school = new School();
    $school->name = "School";
    $school->url = "school";

    $team1DivA = new Dt_Team_Division();
    $team1DivA->score = 1;
    $team1DivA->penalty = DivisionPenalty::MRP;
    $team1DivA->comments = "Comments";
    $team1DivB = new Dt_Team_Division();
    $team1DivB->score = 1;
    $team1 = new FleetScoresTableCreatorTestTeam(
      array(
        'school' => $school,
        'name' => "Team 1",
        'ranks' => array(
          'A' => $team1DivA,
          'B' => $team1DivB,
        ),
      )
    );

    $team2DivA = new Dt_Team_Division();
    $team2DivA->score = 2;
    $team2DivB = new Dt_Team_Division();
    $team2DivB->score = 2;
    $team2 = new FleetScoresTableCreatorTestTeam(
      array(
        'school' => $school,
        'name' => "Team 2",
        'dt_explanation' => "Second to register",
        'ranks' => array(
          'A' => $team2DivA,
          'B' => $team2DivB,
        ),
      )
    );

    return array($team1, $team2);
  }

  public function getSeason() {
    $season = new Season();
    $season->season = Season::FALL;
    $season->start_date = new DateTime('2015-07-01');
    $season->url = 'f15';
    return $season;
  }
}

/**
 * Mock team (for ranks).
 */
class FleetScoresTableCreatorTestTeam extends Team {

  private $ranks = array();

  public function __construct(Array $props) {
    foreach ($props as $name => $value) {
      if ($name == 'ranks') {
        $this->ranks = $value;
      }
      else {
        $this->__set($name, $value);
      }
    }
  }

  public function getRank(Division $division) {
    $div = (string) $division;
    if (array_key_exists($div, $this->ranks)) {
      return $this->ranks[$div];
    }
    return null;
  }

}