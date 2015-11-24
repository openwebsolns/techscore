<?php
namespace data\finalize;

use \AbstractUnitTester;
use \Division;
use \Race;
use \Regatta;
use \Round;

/**
 * Test the minimum round completion criterion.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class MinimumRoundCompletionCriterionTest extends AbstractUnitTester {

  public function testCanApply() {
    $testObject = new MinimumRoundCompletionCriterion();

    $regatta = new Regatta();
    $regatta->scoring = Regatta::SCORING_STANDARD;
    $this->assertFalse($testObject->canApplyTo($regatta));

    $regatta->scoring = Regatta::SCORING_TEAM;
    $this->assertTrue($testObject->canApplyTo($regatta));
  }

  public function testMinimumCompletion() {
    // Mocks
    $round1 = new Round(); $round1->id = 1; $round1->title = "Round1";
    $round2 = new Round(); $round2->id = 2; $round2->title = "Round2";
    $round3 = new Round(); $round3->id = 3; $round3->title = "Round3";

    $race1 = new Race();
    $race2 = new Race();
    $race3 = new Race();
    $race4 = new Race();
    $race5 = new Race();

    $race6 = new Race();
    $race7 = new Race();

    $regatta = new MinimumRoundCompletionCriterionRegatta();
    $regatta->rounds = array($round1, $round2, $round3);
    $regatta->racesByRound = array(
      $round1->id => array($race1, $race2, $race3, $race4, $race5),
      $round2->id => array($race6, $race7),
      $round3->id => array(),
    );
    $regatta->scoredRacesByRound = array(
      $round1->id => array($race1, $race2, $race3, $race4),
      $round2->id => array($race6),
      $round3->id => array(),
    );

    // Test
    $testObject = new MinimumRoundCompletionCriterion();
    $statuses = $testObject->getFinalizeStatuses($regatta);
    $this->assertCount(2, $statuses);

    $status = $statuses[0];
    $this->assertEquals(FinalizeStatus::ERROR, $status->getType());
    $this->assertNotNull($status->getMessage());
    $this->assertTrue(strpos($status->getMessage(), $round2->title) !== false);

    $status = $statuses[1];
    $this->assertEquals(FinalizeStatus::ERROR, $status->getType());
    $this->assertNotNull($status->getMessage());
    $this->assertTrue(strpos($status->getMessage(), $round3->title) !== false);
  }
}

/**
 * Mock regatta
 */
class MinimumRoundCompletionCriterionRegatta extends Regatta {

  public $rounds = array();
  public $racesByRound = array();
  public $scoredRacesByRound = array();

  public function getRounds() {
    return $this->rounds;
  }

  public function getRacesInRound(Round $round, Division $division = null) {
    return $this->racesByRound[$round->id];
  }

  public function getScoredRacesInRound(Round $round) {
    return $this->scoredRacesByRound[$round->id];
  }
}