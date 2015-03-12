<?php
require_once('AbstractTester.php');

/**
 * Tests the home page.
 *
 * @author Dayan Paez
 * @version 2015-03-11
 */
class HomePageTest extends AbstractTester {

  public function test() {
    $response = $this->getUrl('/');
    $head = $response->getHead();
    print_r($head);
  }
}