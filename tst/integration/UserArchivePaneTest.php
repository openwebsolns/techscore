<?php
require_once('AbstractTester.php');

/**
 * Tests the home page.
 *
 * @author Dayan Paez
 * @version 2015-03-11
 */
class UserArchivePaneTest extends AbstractTester {

  protected function setUp() {
    $this->login();
  }

  public function test() {
    $response = $this->getUrl('/archive');
    $this->assertResponseStatus($response, 200);
  }
}