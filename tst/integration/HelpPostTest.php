<?php

require_once('AbstractTester.php');

/**
 * Tests that questions can be asked!
 *
 * @author Dayan Paez
 * @version 2015-11-09
 */
class HelpPostTest extends AbstractTester {

  protected function setUp() {
    $this->login();
  }

  public function testPostAjax() {
    $args = array(
      'message' => "Test message, at least 10 characters long.",
      'subject' => "Test subject",
      'html' => '<html><head><title>Test</title></head><body>This is a test</body>',
    );
    $headers = array(
      'Referer: /',
      'Accept: application/json',
    );

    $url = '/help';
    $response = $this->postUrl($url, $args, $headers);
    $this->assertResponseStatus($response);

    $body = $response->getBody();
    $json = json_decode($body->getRaw(), true);
    $this->assertTrue(is_array($json));
  }
}