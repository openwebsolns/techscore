<?php
namespace scripts\tools;

use \Account;
use \Conf;
use \School;
use \Team;

/**
 * Utility to aggregate and compose body of e-mail messages for the
 * AutoFinalize script.
 *
 * @author Dayan Paez
 * @version 2015-10-29
 */
class AutoFinalizeEmailPreparer {

  private $teams;
  private $accounts;
  private $regattas;
  private $accountsRegattasTeamsMap;
  private $schoolsAccountsCache;

  public function __construct() {
    $this->teams = array();
    $this->accounts = array();
    $this->regattas = array();
    $this->accountsRegattasTeamsMap = array();
    $this->schoolsAccountsCache = array();
  }

  public function addPenalizedTeam(Team $team) {
    if (array_key_exists($team->id, $this->teams)) {
      return;
    }

    $this->teams[$team->id] = $team;

    $regatta = $team->regatta;
    if (!array_key_exists($regatta->id, $this->regattas)) {
      $this->regattas[$regatta->id] = $regatta;
    }

    $accounts = $this->getAccountsForSchool($team->school);
    foreach ($accounts as $account) {
      $this->accounts[$account->id] = $account;
      $this->createArrayForKey(
        $this->accountsRegattasTeamsMap,
        $account->id
      );
      $this->createArrayForKey(
        $this->accountsRegattasTeamsMap[$account->id],
        $regatta->id
      );

      $this->accountsRegattasTeamsMap[$account->id][$regatta->id][$team->id] = $team;
    }
  }

  public function getAccounts() {
    return array_values($this->accounts);
  }

  public function getEmailBody(Account $account) {
    if (!array_key_exists($account->id, $this->accountsRegattasTeamsMap)) {
      throw new InvalidArgumentException("Invalid account provided.");
    }

    $message = "";
    foreach ($this->accountsRegattasTeamsMap[$account->id] as $regattaId => $regattasTeamsMap) {
      $regatta = $this->regattas[$regattaId];
      $message .= sprintf(
        "*%s*\nhttp://%s%s\n",
        $regatta->name,
        Conf::$PUB_HOME,
        $regatta->getURL()
      );
      foreach ($regattasTeamsMap as $teamId => $team) {
        $message .= sprintf(
          "\n  - %s (new rank: %d)",
          $team,
          $team->dt_rank
        );
      }
      $message .= "\n\n";
    }
    return $message;
  }

  public function getAccountsRegattasTeamsMap() {
    return $this->accountsRegattasTeamsMap;
  }

  private function createArrayForKey(Array &$array, $key) {
    if (!array_key_exists($key, $array)) {
      $array[$key] = array();
    }
  }

  private function getAccountsForSchool(School $school) {
    if (!array_key_exists($school->id, $this->schoolsAccountsCache)) {
      $list = array();
      foreach ($school->getUsers() as $account) {
        $list[] = $account;
      }
      $this->schoolsAccountsCache[$school->id] = $list;
    }
    return $this->schoolsAccountsCache[$school->id];
  }
}