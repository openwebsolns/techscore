<?php
use \scripts\tools\AutoFinalizeEmailPreparer;

require_once(dirname(dirname(__DIR__)) . '/AbstractUnitTester.php');

/**
 * Tests the helpful class referenced in the name.
 *
 * @author Dayan Paez
 * @version 2015-10-29
 */
class AutoFinalizeEmailPreparerTest extends AbstractUnitTester {

  public function testAddPenalizedTeam() {
    // Setup
    $account1 = new Account();
    $account1->id = "Account1";
    $account2 = new Account();
    $account2->id = "Account2";
    $account3 = new Account();
    $account3->id = "Account3";

    $school1 = new AutoFinalizeEmailPreparerTestSchool("School1", array($account1, $account2));
    $school2 = new AutoFinalizeEmailPreparerTestSchool("School", array($account1, $account3));

    $regatta = new Regatta();
    $regatta->id = "Regatta1";

    $team1 = new Team();
    $team1->id = "Team1";
    $team1->school = $school1;
    $team1->regatta = $regatta;

    $team2 = new Team();
    $team2->id = "Team2";
    $team2->school = $school2;
    $team2->regatta = $regatta;

    $team3 = new Team();
    $team3->id = "Team3";
    $team3->school = $school2;
    $team3->regatta = $regatta;

    // Test
    $testObject = new AutoFinalizeEmailPreparer();
    $testObject->addPenalizedTeam($team1);
    $testObject->addPenalizedTeam($team2);
    $testObject->addPenalizedTeam($team3);

    $expectedAccounts = array($account1, $account2, $account3);
    $accounts = $testObject->getAccounts();
    foreach ($accounts as $account) {
      $this->assertTrue(in_array($account, $expectedAccounts));
    }
 
    /*
    $justKeys = array();
    foreach ($testObject->getAccountsRegattasTeamsMap() as $accountId => $regattaTeamsMap) {
      $justKeys[$accountId] = array();
      foreach ($regattaTeamsMap as $regattaId => $teamsMap) {
        $justKeys[$accountId][$regattaId] = array();
        foreach ($teamsMap as $teamId => $team) {
          $justKeys[$accountId][$regattaId][$teamId] = $team->id;
        }
      }
    }
    print_r($justKeys);
    */
  }

}

/**
 * Mock school
 */
class AutoFinalizeEmailPreparerTestSchool extends School {

  private $users = array();

  public function __construct($id, Array $users) {
    $this->id = $id;
    $this->users = $users;
    
  }

  public function getUsers($status = null, $effective = true) {
    return $this->users;
  }
}