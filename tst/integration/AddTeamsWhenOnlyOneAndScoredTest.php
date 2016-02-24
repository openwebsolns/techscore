<?php

require_once('AbstractTester.php');

/**
 * A bug was discovered on 2016-02-21, under the following conditions: 
 *
 * A user attempted to add a team when the regatta was under way, but
 * no "new score" was provided for the new team, because there was
 * only one team left, and Techscore assumes that there can be no
 * finishes with only one team.
 *
 * The system arrived at this illegal state with the user adding a
 * valid number of teams, scoring a race, and then deleting all but
 * one of the teams, before attempting to add a new one.
 *
 * This integration tests makes sure this very specific issue is
 * properly covered.
 *
 * @author Dayan Paez
 * @version 2016-02-23
 */
class AddTeamsWhenOnlyOneAndScoredTest extends AbstractTester {

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

    $this->prepareRegatta();
  }

  public function testGetSingleScoredTeam() {
    $this->removeAllButOneTeam();

    $url = sprintf('/score/%s/teams', $this->regatta->id);
    $response = $this->getUrl($url);
    $this->assertResponseStatus($response);

    $body = $response->getBody();
    $this->assertNotNull($body);

    $root = $body->asXml();
    $forms = $root->xpath(sprintf('//html:form[@action="%s"]', $url));
    $this->assertEquals(1, count($forms));

    $form = $forms[0];
    $this->findInputElement($form, 'select', 'new-score');
  }

  public function testPostSingleScoredTeam() {
    $this->removeAllButOneTeam();

    $teams = $this->regatta->getTeams();
    $args = array(
      'invite' => "Add team",
      'school' => $teams[0]->school->id,
    );

    $url = sprintf('/score/%s/teams', $this->regatta->id);
    $response = $this->postUrl($url, $args);
    $this->assertResponseStatus($response, 303);
  }

  /**
   * Score one race.
   */
  private function prepareRegatta() {
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

    $url = sprintf('/score/%s/finishes', $this->regatta->id);
    $response = $this->postUrl($url, $args);
    DB::commit();
  }

  private function removeAllButOneTeam() {
    require_once('tscore/DeleteTeamsPane.php');
    $args = array(
      DeleteTeamsPane::SUBMIT_REMOVE => "Remove",
      'teams' => array(),
    );

    $teams = $this->regatta->getTeams();
    for ($i = 1; $i < count($teams); $i++) {
      $args['teams'][] = $teams[$i]->id;
    }

    $url = sprintf('/score/%s/remove-teams', $this->regatta->id);
    $response = $this->postUrl($url, $args);
    DB::commit();
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

}