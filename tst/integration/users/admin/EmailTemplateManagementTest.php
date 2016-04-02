<?php
namespace users\admin;

use \AbstractTester;
use \DB;
use \STN;

require_once(dirname(dirname(__DIR__)) . '/AbstractTester.php');
require_once('users/admin/TextManagement.php');

/**
 * Test the email management page.
 *
 * @author Dayan Paez
 * @version 2016-04-02
 */
class EmailTemplateManagementTest extends AbstractTester {

  private static $url = '/email-templates';
  private static $knownTemplates = array(
    STN::MAIL_REGISTER_USER,
    STN::MAIL_REGISTER_STUDENT,
    STN::MAIL_VERIFY_EMAIL,
    STN::MAIL_REGISTER_ADMIN,
    STN::MAIL_APPROVED_USER,
    STN::MAIL_UNFINALIZED_REMINDER,
    STN::MAIL_MISSING_RP_REMINDER,
    STN::MAIL_UPCOMING_REMINDER,
    STN::MAIL_RP_REMINDER,
    STN::MAIL_AUTO_FINALIZE_PENALIZED,
  );

  protected function setUp() {
    $this->login();
  }

  public function testLandingPage() {
    $response = $this->getUrl(self::$url);
    $this->assertResponseStatus($response);

    $body = $response->getBody();
    $xpath = sprintf('//html:table[@id="%s"]', EmailTemplateManagement::TABLE_ID);
    $tables = $body->xpath($xpath);
    $this->assertEquals(1, count($tables));

    $table = $tables[0];
    $this->autoregisterXpathNamespace($table);
    foreach (self::$knownTemplates as $id) {
      $xpath = sprintf(
        '//html:a[@href="%s?%s=%s"]',
        self::$url,
        EmailTemplateManagement::INPUT_TEMPLATE,
        $id
      );
      $links = $table->xpath($xpath);
      $this->assertEquals(1, count($links), sprintf("Looking for '%s' link", $id));
    }
  }

  public function testEachMailTemplate() {
    foreach (self::$knownTemplates as $id) {
      $url = sprintf(
        '%s?%s=%s',
        self::$url,
        EmailTemplateManagement::INPUT_TEMPLATE,
        $id
      );
      $response = $this->getUrl($url);
      $this->assertResponseStatus($response);
    }
  }

  /**
   * Grab registration one; delete it; add it back; re-edit.
   */
  public function testPost() {
    $templateId = STN::MAIL_REGISTER_USER;
    $oldValue = DB::g($templateId);
    if ($oldValue == null) {
      $this->markTestSkipped("Null value for $templateId; this test needs fixing.");
      return;
    }
    DB::s($templateId, null);
    DB::commit();
    DB::resetSettings();
    DB::resetCache();

    $params = array(
      EmailTemplateManagement::INPUT_TEMPLATE => $templateId,
      EmailTemplateManagement::INPUT_CONTENT => $oldValue,
      EmailTemplateManagement::SUBMIT_EDIT => "Edit",
    );
    $response = $this->postUrl(self::$url, $params);
    $this->assertResponseStatus($response, 303);
    $newValue = DB::g($templateId);
    $this->assertEquals($oldValue, $newValue);

    $response = $this->postUrl(self::$url, $params);
    $this->assertResponseStatus($response, 303);
  }
}