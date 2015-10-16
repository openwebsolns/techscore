<?php
require_once('AbstractTester.php');

/**
 * Tests the login page. This needs to be run first so that a session
 * can be established for future tests.
 *
 * @author Dayan Paez
 * @created 2015-02-22
 */
class LoginPageTest extends AbstractTester {

  public function testGet() {
    $this->login();
  }

}
