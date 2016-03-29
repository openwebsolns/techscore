<?php

require_once('AbstractTester.php');

/**
 * Tests all manners of entering finishes.
 *
 * @author Dayan Paez
 * @version 2015-11-03
 */
class EnterFinishPaneTest extends AbstractTester {

  private static $standardRegatta;

  private $regatta;

  protected function setUp() {
    $this->login();
    try {
      $this->regatta = $this->getStandardRegatta();
    }
    catch (SoterException $e) {
      $this->markTestSkipped($e->getMessage());
      return;
    }
  }

  public function testStandardStep1() {
    $url = sprintf('/score/%s/finishes', $this->regatta->id);
    $response = $this->getUrl($url);
    $this->assertResponseStatus($response, 200);

    $body = $response->getBody();
    $this->assertNotNull($body);

    $root = $body->asXml();
    $forms = $root->xpath(sprintf('//html:form[@action="%s"]', $url));
    $this->assertEquals(1, count($forms));

    $form = $forms[0];
    $this->findInputElement($form, 'select', 'race');

    // Now with rotation, so there's a choice
    $races = $this->regatta->getRaces();
    $race = $races[0];
    $this->createRotation($race);
    $response = $this->getUrl($url);
    $body = $response->getBody();
    $root = $body->asXml();
    $forms = $root->xpath(sprintf('//html:form[@action="%s"]', $url));
    $form = $forms[0];
    $this->findInputElement($form, 'select', 'finish_using');
  }

  public function testStandardStep2UsingRotations() {
    $races = $this->regatta->getRaces();
    $race = $races[1];
    $this->createRotation($race);

    $url = sprintf('/score/%s/finishes', $this->regatta->id);
    $response = $this->getUrl($url, array('race' => (string) $race));
    $this->assertResponseStatus($response, 200);

    $body = $response->getBody();
    $this->assertNotNull($body);

    $root = $body->asXml();
    $forms = $root->xpath(sprintf('//html:form[@action="%s"]', $url));
    $this->assertEquals(1, count($forms));

    $form = $forms[0];
    $numTeams = count($this->regatta->getTeams());
    for ($i = 0; $i < $numTeams; $i++) {
      $name1 = sprintf('finishes[%d][entry]', $i);
      $name2 = sprintf('finishes[%d][modifier]', $i);
      $this->findInputElement($form, 'select', $name1);
      $this->findInputElement($form, 'select', $name2);
    }
  }

  public function testStandardStep2UsingTeams() {
    $races = $this->regatta->getRaces();
    $race = $races[0];
    $rotation = $this->regatta->getRotationManager();
    $rotation->reset($race);

    $url = sprintf('/score/%s/finishes', $this->regatta->id);
    $response = $this->getUrl($url, array('race' => (string) $race));
    $this->assertResponseStatus($response, 200);

    $body = $response->getBody();
    $this->assertNotNull($body);

    $root = $body->asXml();
    $forms = $root->xpath(sprintf('//html:form[@action="%s"]', $url));
    $this->assertEquals(1, count($forms));

    $form = $forms[0];
    $numTeams = count($this->regatta->getTeams());
    for ($i = 0; $i < $numTeams; $i++) {
      $name1 = sprintf('finishes[%d][entry]', $i);
      $name2 = sprintf('finishes[%d][modifier]', $i);
      $this->findInputElement($form, 'select', $name1);
      $this->findInputElement($form, 'select', $name2);
    }
  }

  public function testStandardPost() {
    $args = array(
      'commit-finishes' => 'Test',
      'finishes' => array(),
    );
    $races = $this->regatta->getRaces();
    $race = $races[0];
    $teams = $this->regatta->getTeams();
    foreach ($teams as $team) {
      $args['finishes'][] = array(
        'entry' => sprintf('%s,%s', $race->id, $team->id),
        'modifier' => null
      );
    }
    $args['finishes'][count($teams) - 1]['modifier'] = Penalty::DNS;

    DB::commit();
    $url = sprintf('/score/%s/finishes', $this->regatta->id);
    $response = $this->postUrl($url, $args);
    $this->assertResponseStatus($response, 303);
    $head = $response->getHead();
    $this->assertContains(
      sprintf('/score/%s/finishes', $this->regatta->id),
      $head->getHeader('Location'),
      "Expected redirect to finish pane."
    );

    DB::commit();
    $finishes = $this->regatta->getFinishes($race);
    $this->assertEquals(count($teams), count($finishes));
  }

  private function getStandardRegatta() {
    if (self::$standardRegatta == null) {
      $numTeams = 6;
      $numDivisions = 2;
      $numRaces = 1;
      $regatta = self::$regatta_creator->createStandardRegatta($numTeams, $numDivisions, $numRaces);
      self::$standardRegatta = $regatta;
    }
    return self::$standardRegatta;
  }

  private function createRotation(Race $race) {
    $teams = $race->regatta->getTeams();
    $manager = $race->regatta->getRotationManager();
    $manager->initQueue();
    foreach ($teams as $i => $team) {
      $sail = new Sail();
      $sail->race = $race;
      $sail->team = $team;
      $sail->sail = ($i + 1);
      $manager->queue($sail);
    }
    $manager->commit();
    DB::commit();
  }
}