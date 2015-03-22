<?php
use \data\RotationTable;

require_once('AbstractUnitTester.php');

/**
 * Tests the RotationTable creation.
 *
 * @author Dayan Paez
 * @version 2015-03-22
 */
class RotationTableTest extends AbstractUnitTester {

  /**
   * Tests the creation of rotation table for regatta with multiple
   * divisions.
   *
   */
  public function testMultipleDivisions() {
    $reg = $this->getMultiDivisionRegatta();
    if ($reg === null) {
      $this->markTestSkipped("No regattas with multiple divisions.");
      return;
    }

    $divisions = $reg->getDivisions();
    foreach ($divisions as $div) {
      $r1 = new RotationTable($reg, $div);
      $r2 = new RotationTable($reg, $div, true);

      $this->assertInstanceOf('XTable', $r1);
      $this->assertInstanceOf('XTable', $r2);
    }
  }

  /**
   * Tests rotation creation for singlehanded events.
   */
  public function testSinglehanded() {
    $reg = $this->getSinglehandedRegatta();
    if ($reg === null) {
      $this->markTestSkipped("No singlehanded regattas.");
      return;
    }

    $r1 = new RotationTable($reg, Division::A());
    $r2 = new RotationTable($reg, Division::A(), true);

    $this->assertInstanceOf('XTable', $r1);
    $this->assertInstanceOf('XTable', $r2);
  }

  private function getMultiDivisionRegatta() {
    // Fetch a regatta with a rotation and multiple divisions
    $regs = DB::getAll(
      DB::T(DB::REGATTA),
      new DBBool(
        array(
          new DBCond('dt_num_divisions', 2, DBCond::GE),
          new DBCond('scoring', Regatta::SCORING_STANDARD),
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

  private function getSinglehandedRegatta() {
    // Fetch a singlehanded regatta with a rotation
    $regs = DB::getAll(
      DB::T(DB::REGATTA),
      new DBBool(
        array(
          new DBCond('dt_singlehanded', null, DBCond::NE),
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