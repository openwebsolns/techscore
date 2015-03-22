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

  private $multiDivisionRegatta;
  private $singlehandedRegatta;

  /**
   * Tests the creation of rotation table for regatta with multiple
   * divisions.
   *
   */
  public function testMultipleDivisions() {
    $divisions = $this->multiDivisionRegatta->getDivisions();
    foreach ($divisions as $div) {
      $r1 = new RotationTable($this->multiDivisionRegatta, $div);
      $r2 = new RotationTable($this->multiDivisionRegatta, $div, true);

      $this->assertInstanceOf('XTable', $r1);
      $this->assertInstanceOf('XTable', $r2);
    }
  }

  /**
   * Tests rotation creation for singlehanded events.
   */
  public function testSinglehanded() {
    $r1 = new RotationTable($this->singlehandedRegatta, Division::A());
    $r2 = new RotationTable($this->singlehandedRegatta, Division::A(), true);

    $this->assertInstanceOf('XTable', $r1);
    $this->assertInstanceOf('XTable', $r2);
  }

  protected function setUp() {
    // Fetch a regatta with a rotation and multiple divisions
    $regs = DB::getAll(
      DB::T(DB::REGATTA),
      new DBBool(
        array(
          new DBCond('dt_num_divisions', 2, DBCond::GE),
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
      throw new InvalidArgumentException("Unable to test: no multi-division regattas with rotations.");
    }

    $this->multiDivisionRegatta = $regs[rand(0, count($regs) - 1)];

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
      throw new InvalidArgumentException("Unable to test: no singlehanded regattas with rotations.");
    }

    $this->singlehandedRegatta = $regs[rand(0, count($regs) - 1)];
  }
}