<?php

require_once(dirname(dirname(__FILE__)) . '/AbstractUnitTester.php');

/**
 * Attempt to cover Season class
 *
 * @author Dayan Paez
 * @created 2015-06-09
 */
class SeasonTest extends AbstractUnitTester {

  /**
   * At least test that it runs.
   */
  public function testGetSailorParticipation() {
    $seasons = DB::getAll(DB::T(DB::SEASON));
    if (count($seasons) == 0) {
      $this->markTestSkipped("No seasons available for testing.");
      return;
    }

    $season = $seasons[rand(0, count($seasons) - 1)];

    // random sailor
    $sailor = new Sailor();
    $sailor->id = -3;
    $season->getSailorParticipation($sailor, false);
    $season->getSailorParticipation($sailor, true);
  }
}