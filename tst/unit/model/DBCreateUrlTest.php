<?php
namespace model;

use \AbstractUnitTester;
use \DB;

/**
 * Test URL-related methods.
 *
 * @author Dayan Paez
 * @version 2015-11-24
 */
class DBCreateUrlTest extends AbstractUnitTester {

  public function testCreateUrlSlug() {
    $seed = "Test URL";
    $expectedUrl = DB::slugify($seed) . '-2';
    $counter = 0;
    $result = DB::createUrlSlug(
      array($seed),
      function($slugBeingConsidered) use (&$counter) {
        $counter++;
        return $counter > 2;
      }
    );

    $this->assertEquals($expectedUrl, $result);
  }

  public function testCreateUrlSlugSecondSeed() {
    $badSeed = 'badseed';
    $passSeed = 'pass';
    $ignoredSeed = 'ignored';
    $seeds = array($badSeed, $passSeed, $ignoredSeed);
    $result = DB::createUrlSlug(
      $seeds,
      function($slugBeingConsidered) use ($badSeed) {
        return $slugBeingConsidered != $badSeed;
      }
    );
    $this->assertEquals($passSeed, $result);
  }

  public function testSlugifyDefaultArguments() {
    $expectations = array(
      'Simple' => 'simple',
      'Short Words s removed' => 'short-words-removed',
      " Leading \t other  spaces " => 'leading-other-spaces',
      '000' => '000',
      'A B C' => 'a-b-c',
    );
    foreach ($expectations as $input => $expected) {
      $this->assertEquals(
        $expected,
        DB::slugify($input),
        sprintf("Expected \"%s\" for input \"%s\".", $expected, $input)
      );
    }
  }

}