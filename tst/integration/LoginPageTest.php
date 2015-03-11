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
}
?>