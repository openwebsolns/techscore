<?php
require_once('AbstractTester.php');

/**
 * Tests the home page.
 *
 * @author Dayan Paez
 * @version 2015-03-11
 */
class HomePageTest extends AbstractTester {

  protected function setUp() {
    $this->login();
  }

  public function test(): void {
    $response = $this->getUrl('/');
    $this->assertResponseStatus($response, 200);
  }
}