<?php
namespace model;

use \AbstractUnitTester;
use \DateTime;
use \DB;

class DBCacheTest extends AbstractUnitTester {

  public function testNonCachingDateTime() {
    $now = DB::T(DB::NOW);
    $later = DB::T(DB::NOW);
    $this->assertNotSame($now, $later);
  }

}