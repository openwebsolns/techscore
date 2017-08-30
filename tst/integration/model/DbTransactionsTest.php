<?php
require_once(dirname(__DIR__) . '/AbstractTester.php');

/**
 * Test creating and rolling back transactions.
 *
 * @author Dayan Paez
 * @version 2017-08-28
 */
class DbTransactionsTest extends AbstractTester {

  private $created = array();

  protected function tearDown() {
    foreach ($this->created as $item) {
      DB::remove($item);
    }
  }

  public function testRollingBackDoesNotPersist() {
    $name = sprintf('DbTest-%d', rand(10, 10000));
    DB::beginTransaction();
    $boat = new Boat();
    $boat->name = $name;
    $boat->min_crews = 1;
    $boat->max_crews = 2;
    DB::set($boat);
    $this->created[] = $boat;

    $savedBoat = DB::get(DB::T(DB::BOAT), $boat->id);
    $this->assertNotNull($savedBoat);
    $this->assertEquals($name, $savedBoat->name);

    DB::rollback();
    DB::resetCache();
    $savedBoat = DB::get(DB::T(DB::BOAT), $boat->id);
    $this->assertNull($savedBoat);
  }
}
