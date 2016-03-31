<?php
namespace users\admin;

use \AbstractTester;
use \DB;
use \Text_Entry;

require_once(dirname(dirname(__DIR__)) . '/AbstractTester.php');
require_once('users/admin/TextManagement.php');

/**
 * Test the TextManagement pane.
 *
 * @author Dayan Paez
 * @version 2016-03-31
 */
class TextManagementTest extends AbstractTester {

  private static $url = '/text';

  protected function setUp() {
    $this->login();
  }

  public function testLandingPage() {
    $response = $this->getUrl(self::$url);
    $this->assertResponseStatus($response);

    $body = $response->getBody();
    $xpath = '//html:table[@class="text-entries"]';
    $tables = $body->xpath($xpath);
    $this->assertEquals(1, count($tables));

    $table = $tables[0];
    $this->autoregisterXpathNamespace($table);
    foreach (Text_Entry::getSections() as $id => $title) {
      $xpath = sprintf(
        '//html:a[@href="%s?%s=%s"]',
        self::$url,
        TextManagement::INPUT_SECTION,
        $id
      );
      $links = $table->xpath($xpath);
      $this->assertEquals(1, count($links), sprintf("Looking for '%s' link", $id));
    }
  }

  public function testEachTextEntry() {
    foreach (Text_Entry::getSections() as $id => $title) {
      $url = sprintf(
        '%s?%s=%s',
        self::$url,
        TextManagement::INPUT_SECTION,
        $id
      );
      $response = $this->getUrl($url);
      $this->assertResponseStatus($response);
    }
  }

  /**
   * Grab first one; delete it; add it back; re-edit.
   */
  public function testPost() {
    $sections = Text_Entry::getSections();
    $sectionIds = array_keys($sections);
    $oldValue = null;
    foreach ($sectionIds as $sectionId) {
      $oldValue = DB::get(DB::T(DB::TEXT_ENTRY), $sectionId);
      if ($oldValue !== null) {
        break;
      }
    }
    if ($oldValue == null) {
      $this->markTestSkipped("No non-null values; this test needs fixing.");
      return;
    }
    DB::remove($oldValue);
    DB::commit();

    $params = array(
      TextManagement::INPUT_SECTION => $sectionId,
      TextManagement::INPUT_CONTENT => $oldValue->plain,
    );
    $response = $this->postUrl(self::$url, $params);
    $this->assertResponseStatus($response, 303);
    $newValue = DB::get(DB::T(DB::TEXT_ENTRY), $sectionId);
    $this->assertNotNull($newValue);
    $this->assertEquals($oldValue->plain, $newValue->plain);

    $response = $this->postUrl(self::$url, $params);
    $this->assertResponseStatus($response, 303);
  }

}