<?php
namespace model;

use \AbstractUnitTester;
use \DB;

/**
 * Test the regular expression capabilities.
 *
 * @author Dayan Paez
 * @version 2015-11-17
 */
class DBAddRegexDelimitersTest extends AbstractUnitTester {

  public function testAddRegexDelimiters() {
    $expectations = array(
      'foo' => '/foo/',
      '/bar' => '/\/bar/',
      'foo/' => '/foo\//',
      '\/foo' => '/\\\/foo/',
    );
    foreach ($expectations as $input => $expected) {
      $result = DB::addRegexDelimiters($input);
      $this->assertEquals($expected, $result);
    }
  }

  public function testDelimitersWorkAsExpected() {
    $input = '/foobar?';
    $raw = '^/foo';
    $converted = DB::addRegexDelimiters($raw);
    $this->assertEquals(1, preg_match($converted, $input));
  }

}