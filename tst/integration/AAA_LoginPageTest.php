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
    $response = $this->getUrl('/');
    $this->assertResponseStatus($response, 403);

    $head = $response->getHead();
    $cookie = $head->getHeader('Set-Cookie');
    $this->assertNotEmpty($cookie, "Expected session cookie to be set.");
    $cookie_parts = explode(';', $cookie);

    // Cache session ID
    self::setSession($cookie_parts[0]);

    $body = $response->getBody();
    $this->assertNotNull($body);
    $root = $body->asXml();
    $this->assertEquals('html', $root->getName());

    $namespaces = $root->getNamespaces();
    $namespace = $namespaces[''];
    $this->assertNotEmpty($namespace, "Looking for default namespace.");

    // Look for form?
    $root->registerXPathNamespace('html', $namespace);
    $forms = $root->xpath('//html:form');
    $this->assertEquals(1, count($forms), "Looking for exactly one login form");
    $form = $forms[0];

    // CSRF token?
    $form->registerXPathNamespace('html', $namespace);
    $inputs = $form->xpath('html:input[@name="csrf_token"]');
    $this->assertNotEmpty($inputs, "No CSRF tokens found");
  }

  public function testPost() {
    // 
  }
}
