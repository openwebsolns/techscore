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
    $this->getUrl('/');
  }
}
?>