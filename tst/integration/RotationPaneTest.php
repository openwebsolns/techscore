<?php
use \model\FleetRotation;

require_once('AbstractTester.php');

/**
 * Tests rotation creation.
 *
 * @author Dayan Paez
 * @version 2015-03-11
 */
class RotationPaneTest extends AbstractTester {

  private static $standardRegatta;

  private function getStandardRegatta() {
    if (self::$standardRegatta == null) {
      $numTeams = 6;
      $numDivisions = 2;
      $numRaces = 5;
      $regatta = self::$regatta_creator->createStandardRegatta($numTeams, $numDivisions, $numRaces);
      self::$standardRegatta = $regatta;
    }
    return self::$standardRegatta;
  }

  protected function setUp() {
    $this->login();
  }

  public function testStandardPost() {
    try {
      $regatta = $this->getStandardRegatta();
    }
    catch (SoterException $e) {
      $this->markTestSkipped($e->getMessage());
      return;
    }

    // Test creation of sails leads to redirect to finish
    $url = sprintf('/score/%s/rotations', $regatta->id);
    $args = array(
      'races_per_set' => 2,
      'sails' => array(1, 2, 3, 4, 5, 6),
      'colors' => array('#00f', '#0f0', '#f00', '#008', '#080', '#800'),
      'division_order' => array('A', 'B'),
    );
    $manager = $regatta->getRotationManager();
    $prevId = null;
    foreach (FleetRotation::types() as $type) {
      foreach (FleetRotation::styles() as $style) {
        $args['rotation_type'] = $type;
        $args['rotation_style'] = $style;
        $messagePrefix = sprintf("[Type=%s, Style=%s] ", $type, $style);

        $response = $this->postUrl($url, $args);
        $head = $response->getHead();
        $this->assertEquals(303, $head->getStatus());
        $this->assertContains(
          sprintf('/score/%s/finishes', $regatta->id),
          $head->getHeader('Location'),
          $messagePrefix . "Expected redirect to finish pane."
        );

        // Assert that rotation was saved
        $savedRotation = $manager->getFleetRotation();
        $this->assertEquals($type, $savedRotation->rotation_type);
        $this->assertNotEquals($prevId, $savedRotation->id);
        $prevId = $savedRotation->id;
        DB::commit();
      }
    }
  }

  public function testStandardGetStep1() {
    try {
      $regatta = $this->getStandardRegatta();
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
    $this->findInputElement($form, 'select', 'rotation_style');
    $this->findInputElement($form, 'input', 'races_per_set');
    $this->findInputElement($form, 'input', 'division_order[]', null, 2);
  }

  public function testStandardGetStep2() {
    try {
      $regatta = $this->getStandardRegatta();
    }
    catch (SoterException $e) {
      $this->markTestSkipped($e->getMessage());
      return;
    }

    // Test creation of sails
    $url = sprintf('/score/%s/rotations', $regatta->id);
    $args = array(
      'rotation_type' => FleetRotation::TYPE_STANDARD,
      'rotation_style' => FleetRotation::STYLE_NAVY,
      'races_per_set' => 3,
      'division_order' => array('B', 'A'),
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
    $count = 6; // number of teams
    $this->findInputElement($form, 'input', 'sails[]', null, $count);
    $this->findInputElement($form, 'select', 'colors[]', null, $count);
  }
}