<?php
require_once(dirname(__DIR__) . '/AbstractTester.php');

/**
 * Test the access to the SchoolsPane
 *
 * @author Dayan Paez
 * @version 2015-11-13
 */
class SchoolsPaneTest extends AbstractTester {

  protected function setUp() {
    $this->login();
  }

  public function testPageExists() {
    $response = $this->getUrl('/schools-edit');
    $this->assertResponseStatus($response, 200);
  }
}