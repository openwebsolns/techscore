<?php
use \data\TeamRotationTable;

require_once('AbstractUnitTester.php');

/**
 * Tests the TeamRotationTable creation.
 *
 * @author Dayan Paez
 * @version 2015-03-22
 */
class TeamRotationTableTest extends AbstractUnitTester {

  /**
   * Tests the creation of rotation table for regular team.
   *
   */
  public function testRegularRegatta() {
    $reg = $this->getRegularRegatta();
    if ($reg === null) {
      $this->markTestSkipped("No \"regular\" team racing regattas.");
      return;
    }

    foreach ($reg->getRounds() as $round) {
      $r1 = new TeamRotationTable($reg, $round);
      $r2 = new TeamRotationTable($reg, $round, true);

      $this->assertInstanceOf('XTable', $r1);
      $this->assertInstanceOf('XTable', $r2);
    }
  }

  /**
   * Tests the creation of rotation table for round in groups team.
   *
   */
  public function testRoundGroupRegatta() {
    $reg = $this->getRoundGroupRegatta();
    if ($reg === null) {
      $this->markTestSkipped("No regattas with rounds in groups.");
      return;
    }

    foreach ($reg->getRounds() as $round) {
      $r1 = new TeamRotationTable($reg, $round);
      $r2 = new TeamRotationTable($reg, $round, true);

      $this->assertInstanceOf('XTable', $r1);
      $this->assertInstanceOf('XTable', $r2);
    }
  }

  /**
   * Tests the creation of rotation table for regatta with unknown teams.
   *
   */
  public function testMissingTeamRegatta() {
    $reg = $this->getMissingTeamRegatta();
    if ($reg === null) {
      $this->markTestSkipped("No regattas with rounds with unknown teams.");
      return;
    }

    foreach ($reg->getRounds() as $round) {
      $r1 = new TeamRotationTable($reg, $round);
      $r2 = new TeamRotationTable($reg, $round, true);

      $this->assertInstanceOf('XTable', $r1);
      $this->assertInstanceOf('XTable', $r2);
    }
  }

  private function getRegularRegatta() {
    // Fetch a regatta with a rotation and multiple divisions
    $regs = DB::getAll(
      DB::T(DB::REGATTA),
      new DBBool(
        array(
          new DBCond('scoring', Regatta::SCORING_TEAM),
          new DBCondIn(
            'id',
            DB::prepGetAll(
              DB::T(DB::ROUND),
              new DBCondIn(
                'id',
                DB::prepGetAll(
                  DB::T(DB::RACE),
                  new DBCondIn(
                    'id',
                    DB::prepGetAll(
                      DB::T(DB::SAIL),
                      null,
                      array('race')
                    )
                  ),
                  array('round')
                )
              ),
              array('regatta')
            )
          )
        )
      )
    );

    if (count($regs) == 0) {
      return null;
    }
    return $regs[rand(0, count($regs) - 1)];
  }

  private function getRoundGroupRegatta() {
    // Fetch a regatta with a round in a round group
    $regs = DB::getAll(
      DB::T(DB::REGATTA),
      new DBBool(
        array(
          new DBCond('scoring', Regatta::SCORING_TEAM),
          new DBCondIn(
            'id',
            DB::prepGetAll(
              DB::T(DB::ROUND),
              new DBCond('round_group', null, DBCond::NE),
              array('regatta')
            )
          )
        )
      )
    );

    if (count($regs) == 0) {
      return null;
    }
    return $regs[rand(0, count($regs) - 1)];
  }

  private function getMissingTeamRegatta() {
    // Fetch a regatta with a rotation and multiple divisions
    $regs = DB::getAll(
      DB::T(DB::REGATTA),
      new DBBool(
        array(
          new DBCond('scoring', Regatta::SCORING_TEAM),
          new DBCondIn(
            'id',
            DB::prepGetAll(
              DB::T(DB::ROUND),
              new DBCondIn(
                'id',
                DB::prepGetAll(
                  DB::T(DB::RACE),
                  new DBBool(
                    array(
                      new DBBool(
                        array(
                          new DBCond('tr_team1', null),
                          new DBCond('tr_team2', null)
                        ),
                        DBBool::mOR
                      ),
                      new DBCondIn(
                        'id',
                        DB::prepGetAll(
                          DB::T(DB::SAIL),
                          null,
                          array('race')
                        )
                      ),
                    )
                  ),
                  array('round')
                )
              ),
              array('regatta')
            )
          )
        )
      )
    );

    if (count($regs) == 0) {
      return null;
    }
    return $regs[rand(0, count($regs) - 1)];
  }
}