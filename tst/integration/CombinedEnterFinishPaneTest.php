<?php

require_once('AbstractTester.php');

/**
 * Tests all manners of entering finishes for combined racing.
 *
 * @author Dayan Paez
 * @version 2015-11-03
 */
class CombinedEnterFinishPaneTest extends AbstractTester {

  private static $combinedRegatta;

  private $regatta;

  protected function setUp() {
    $this->login();
    try {
      $this->regatta = $this->getCombinedRegatta();
    }
    catch (SoterException $e) {
      $this->markTestSkipped($e->getMessage());
      return;
    }
  }

  public function testCombinedStep1() {
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
    $races = $this->regatta->getRaces(Division::A());
    $race = $races[0];
    $this->createRotation($race);
    $response = $this->getUrl($url);
    $body = $response->getBody();
    $root = $body->asXml();
    $forms = $root->xpath(sprintf('//html:form[@action="%s"]', $url));
    $form = $forms[0];
    $this->findInputElement($form, 'select', 'finish_using');
  }

  public function testCombinedStep2UsingRotation() {
    $races = $this->regatta->getRaces();
    $race = $races[0];
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
    $numDivisions = count($this->regatta->getDivisions());
    for ($i = 0; $i < $numDivisions * $numTeams; $i++) {
      $name1 = sprintf('finishes[%d][entry]', $i);
      $name2 = sprintf('finishes[%d][modifier]', $i);
      $this->findInputElement($form, 'select', $name1);
      $this->findInputElement($form, 'select', $name2);
    }
  }

  public function testCombinedStep2UsingTeams() {
    $rotation = $this->regatta->getRotationManager();
    foreach ($this->regatta->getDivisions() as $division) {
      $race = $this->regatta->getRace($division, 1);
      $rotation->reset($race);
    }

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
    $numDivisions = count($this->regatta->getDivisions());
    for ($i = 0; $i < $numDivisions * $numTeams; $i++) {
      $name1 = sprintf('finishes[%d][entry]', $i);
      $name2 = sprintf('finishes[%d][modifier]', $i);
      $this->findInputElement($form, 'select', $name1);
      $this->findInputElement($form, 'select', $name2);
    }
  }

  public function testCombinedPost() {
    $args = array(
      'commit-finishes' => 'Test',
      'finishes' => array(),
    );
    $races = $this->regatta->getRaces();
    $teams = $this->regatta->getTeams();
    foreach ($races as $race) {
      foreach ($teams as $team) {
        $args['finishes'][] = array(
          'entry' => sprintf('%s,%s', $race->id, $team->id),
          'modifier' => null
        );
      }
    }

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
    foreach ($races as $race) {
      $finishes = $this->regatta->getFinishes($race);
      $this->assertEquals(count($teams), count($finishes));
    }
  }

  private function getCombinedRegatta() {
    if (self::$combinedRegatta == null) {
      $numTeams = 6;
      $numDivisions = 2;
      $numRaces = 2;
      $regatta = self::$regatta_creator->createCombinedRegatta($numTeams, $numRaces, $numDivisions);
      self::$combinedRegatta = $regatta;
    }
    return self::$combinedRegatta;
  }

  private function createRotation(Race $race) {
    $teams = $race->regatta->getTeams();
    $manager = $race->regatta->getRotationManager();
    $manager->initQueue();
    $i = 0;
    foreach ($race->regatta->getDivisions() as $division) {
      $otherRace = $race->regatta->getRace($division, $race->number);
      $manager->reset($otherRace);
      foreach ($teams as $team) {
        $sail = new Sail();
        $sail->race = $otherRace;
        $sail->team = $team;
        $sail->sail = ($i + 1);
        $manager->queue($sail);
        $i++;
      }
    }
    $manager->commit();
    DB::commit();
  }
}