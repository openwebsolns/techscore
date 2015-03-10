<?php
require_once('AbstractTester.php');

/**
 * Tests every page to make sure it returns a non-500 error status.
 *
 * @author Dayan Paez
 * @created 2015-02-22
 */
class LoginPageTest extends AbstractTester {

  public function testGet() {
    $response = $this->getUrl('/');
    $head = $response->getHead();
    $this->assertEquals(403, $head->getStatus());
    $this->assertNotEmpty($head->getHeader('Set-Cookie'), "Expected session cookie to be set.");
  }
}
?>