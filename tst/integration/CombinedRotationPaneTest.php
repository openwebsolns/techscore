<?php
use \model\FleetRotation;

require_once('AbstractTester.php');

/**
 * Tests rotation creation for combined racing regattas.
 *
 * @author Dayan Paez
 * @version 2015-03-11
 */
class CombinedRotationPaneTest extends AbstractTester {

  private static $regatta;

  private function getRegatta() {
    if (self::$regatta == null) {
      $numTeams = 5;
      $numRaces = 5;
      $numDivisions = 3;
      $regatta = self::$regatta_creator->createCombinedRegatta($numTeams, $numRaces, $numDivisions);
      self::$regatta = $regatta;
    }
    return self::$regatta;
  }

  protected function setUp() {
    $this->login();
  }

  public function testCombinedPost() {
    try {
      $regatta = $this->getRegatta();
    }
    catch (SoterException $e) {
      $this->markTestSkipped($e->getMessage());
      return;
    }

    // Test creation of sails leads to redirect to finish
    $url = sprintf('/score/%s/rotations', $regatta->id);
    $args = array(
      'races_per_set' => 2,
      'sails' => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16),
      'colors' => array('#00f', '#0f0', '#f00', '#008', '#080', '#800', '#00f', '#0f0', '#f00', '#008', '#080', '#800', '#f00', '#008', '#080', '#800')
    );
    foreach (FleetRotation::types() as $type) {
      $args['rotation_type'] = $type;
      $messagePrefix = sprintf("[Type=%s] ", $type);

      $response = $this->postUrl($url, $args);
      $head = $response->getHead();
      $this->assertEquals(303, $head->getStatus());
      $this->assertContains(
        sprintf('/score/%s/finishes', $regatta->id),
        $head->getHeader('Location'),
        $messagePrefix . "Expected redirect to finish pane."
      );
    }
  }

  public function testCombinedGetStep1() {
    try {
      $regatta = $this->getRegatta();
    }
    catch (SoterException $e) {
      $this->markTestSkipped($e->getMessage());
      return;
    }

    // Test creation of sails
    $url = sprintf('/score/%s/rotations', $regatta->id);
    $response = $this->getUrl($url);
    $this->assertResponseStatus($response, 200);

    $body = $response->getBody();
    $this->assertNotNull($body);

    $root = $body->asXml();
    $forms = $root->xpath(sprintf('//html:form[@action="%s"]', $url));
    $this->assertEquals(1, count($forms));

    $form = $forms[0];

    // Expect an input for 'rottype', 'style' and 'repeat'
    $this->findInputElement($form, 'select', 'rotation_type');
    $this->findInputElement($form, 'input', 'races_per_set');
  }

  public function testCombinedGetStep2() {
    try {
      $regatta = $this->getRegatta();
    }
    catch (SoterException $e) {
      $this->markTestSkipped($e->getMessage());
      return;
    }

    // Test creation of sails
    $url = sprintf('/score/%s/rotations', $regatta->id);
    $args = array(
      'rotation_type' => FleetRotation::TYPE_SWAP,
      'races_per_set' => 3,
    );
    $response = $this->getUrl($url, $args);
    $this->assertResponseStatus($response, 200);

    $body = $response->getBody();
    $this->assertNotNull($body);

    $root = $body->asXml();
    $forms = $root->xpath(sprintf('//html:form[@action="%s"]', $url));
    $this->assertEquals(1, count($forms));

    $form = $forms[0];

    // Expect an input for 'rottype', 'style' and 'repeat'
    $count = 16; // num divisions * num teams + 1;
    $this->findInputElement($form, 'input', 'sails[]', null, $count);
    $this->findInputElement($form, 'select', 'colors[]', null, $count);
  }
}