<?php
namespace scripts;

use \AbstractUnitTester;
use \DateTime;

/**
 * Tests lib/scripts/RemovePrivate.
 *
 * @author Dayan Paez
 * @version 2015-05-28
 */
class RemovePrivateTest extends AbstractUnitTester {

  /**
   * The RemovePrivate script.
   */
  private $P;

  public function setUp() {
    $this->P = new RemovePrivate();
  }

  /**
   * Test regatta selector
   */
  public function testGetRegattasToRemove() {
    $list = $this->P->getRegattasToRemove();
    if (count($list) == 0) {
      // skip
      $this->markTestSkipped("No private regattas were returned!");
      return;
    }

    // make sure they qualify
    foreach ($list as $reg) {
      if ($reg->inactive !== null) {
        $this->assertNotNull($reg->private);
        $this->assertLessThanOrEqual(new DateTime('4 months ago'), $reg->end_date);
      }
      else {
        $this->assertTrue(true, "Regatta is inactive.");
      }
    }
  }

  /**
   * Tests the get orphaned sailors.
   *
   */
  public function testGetOrphanedSailors() {
    $list = $this->P->getOrphanedSailors();
    if (count($list) == 0) {
      // skip
      $this->markTestSkipped("No orphaned sailors were returned!");
      return;
    }

    // make sure they qualify
    foreach ($list as $sailor) {
      $this->assertFalse($sailor->isRegistered());
      $this->assertNull($sailor->regatta_added);
      $this->assertCount(0, $sailor->getRegattas());
    }
  }
}