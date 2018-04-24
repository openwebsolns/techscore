<?php
namespace model;

use \AbstractUnitTester;
use \DateTime;
use \InvalidArgumentException;
use \Regatta;
use \School;
use \Season;

class PublicDataTest extends AbstractUnitTester {

  private $testObject;

  protected function setUp() {
    parent::setUp();
    $this->testObject = new PublicData(PublicData::V1);
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testVersionDisallowed() {
    $this->testObject->with('version', 'foo');
  }

  public function testTranslateNull() {
    $key = 'value';
    $this->testObject->with($key, null);
    $parsed = json_decode($this->testObject->toJson(), true);
    $this->assertNull($parsed[$key]);
  }

  public function testTranslateDateTime() {
    $key = 'key';
    $value = new DateTime('April 24, 2018 06:00:00');
    $this->testObject->with($key, $value);
    $parsed = json_decode($this->testObject->toJson(), true);
    $this->assertEquals($value->format('c'), $parsed[$key]);
  }

  public function testTranslateSeasonAsPublishable() {
    $key = 'key';
    $value = new Season();
    $value->season = Season::FALL;
    $value->start_date = new DateTime();
    $value->end_date = new DateTime('tomorrow');
    $value->url = 'season-url';

    $this->testObject->with($key, $value);
    $parsed = json_decode($this->testObject->toJson(), true);
    $this->assertStringStartsWith('url', $parsed[$key]);
    $this->assertContains($value->url, $parsed[$key]);
  }

  public function testTranslatePublishableAsUrl() {
    $key = 'key';
    $value = new School();
    $value->url = 'test-url';

    $this->testObject->with($key, $value);
    $parsed = json_decode($this->testObject->toJson(), true);
    $this->assertEquals(sprintf('url:%s', $value->getURL()), $parsed[$key]);
  }

  public function testRecursiveTranslation() {
    $key = 'key';
    $value = array(
      'A' => 'string',
      'B' => new DateTime(),
      'C' => array(
        'D' => null,
        'E' => 3,
      ),
    );

    $parsed = json_decode($this->testObject->with($key, $value)->toJson(), true);
    $this->assertTrue(is_array($parsed[$key]));

    $sub1 = $parsed[$key];
    $this->assertEquals('string', $sub1['A']);
    $this->assertNotEmpty($sub1['B']);
    $this->assertTrue(is_array($sub1['C']));

    $sub2 = $sub1['C'];
    $this->assertTrue(array_key_exists('D', $sub2));
    $this->assertNull($sub2['D']);
    $this->assertEquals(3, $sub2['E']);
  }
}
