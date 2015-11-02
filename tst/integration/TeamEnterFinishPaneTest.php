<?php

require_once('AbstractTester.php');

/**
 * Tests all manners of entering finishes for team race.
 *
 * @author Dayan Paez
 * @version 2015-11-03
 */
class TeamEnterFinishPaneTest extends AbstractTester {

  private static $teamRegatta;
  private static $numTeams = 6;
  private static $numRounds = 1;

  private $regatta;

  protected function setUp() {
    $this->login();
    try {
      $this->regatta = $this->getTeamRegatta();
    }
    catch (SoterException $e) {
      $this->markTestSkipped($e->getMessage());
      return;
    }
  }

  public function testTeamStep1() {
    $url = sprintf('/score/%s/finishes', $this->regatta->id);
    $response = $this->getUrl($url);
    $body = $response->getBody();
    $root = $body->asXml();
    $forms = $root->xpath(sprintf('//html:form[@action="%s"]', $url));
    $form = $forms[0];
    $this->findInputElement($form, 'select', 'finish_using', null, 0);
  }

  public function testTeamStep2UsingRotations() {
    $url = sprintf('/score/%s/finishes', $this->regatta->id);
    $response = $this->getUrl($url, array('race' => 1));
    $this->assertResponseStatus($response, 200);

    $body = $response->getBody();
    $this->assertNotNull($body);

    $root = $body->asXml();
    $forms = $root->xpath(sprintf('//html:form[@action="%s"]', $url));
    $this->assertEquals(1, count($forms));

    $form = $forms[0];
    $numTeams = 2 * count($this->regatta->getDivisions());
    for ($i = 0; $i < $numTeams; $i++) {
      $name1 = sprintf('finishes[%d][entry]', $i);
      $name2 = sprintf('finishes[%d][modifier]', $i);
      $this->findInputElement($form, 'select', $name1);
      $this->findInputElement($form, 'select', $name2);
    }
  }

  public function testTeamPost() {
    $args = array(
      'commit-finishes' => 'Test',
      'finishes' => array(),
    );
    $teams = $this->regatta->getTeams();
    $divisions = $this->regatta->getDivisions();
    foreach ($divisions as $division) {
      $race = $this->regatta->getRace($division, 1);
      foreach (array($race->tr_team1, $race->tr_team2) as $team) {
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
    $finishes = $this->regatta->getCombinedFinishes($race);
    $this->assertEquals(2 * count($divisions), count($finishes));
  }

  private function getTeamRegatta() {
    if (self::$teamRegatta == null) {
      $regatta = self::$regatta_creator->createTeamRegatta(
        self::$numTeams,
        self::$numRounds
      );
      self::$teamRegatta = $regatta;
    }
    return self::$teamRegatta;
  }
}