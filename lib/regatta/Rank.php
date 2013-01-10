<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */
require_once('conf.php');

/**
 * Objects encapsulate a finish and a tiebreaker explanation. When
 * ranking teams, it is necessary to know not just the order, but
 * often the reason in case of tie-breakers.
 *
 * @author Dayan Paez
 * @version 2010-01-31
 */
class Rank {

  protected $team;
  public $score;
  public $explanation;
  /**
   * @var Division the optional division being referred to
   */
  protected $division;

  /**
   * Create a new rank with the given parameters
   *
   * @param Team $team the team
   * @param int $score the score
   * @param String $exp the (optional) explanation
   */
  public function __construct(Team $team, $score, $exp = "") {
    $this->team = $team;
    $this->score = $score;
    $this->explanation = (string)$exp;
  }

  public function __set($name, $value) {
    if ($name == 'team' && $value instanceof Team)
      $this->team = $value;
    elseif ($name == 'division' && $value instanceof Division)
      $this->division = $value;
    elseif ($name == 'explanation')
      $this->explanation = (string)$value;
    elseif ($name == 'score')
      $this->score = (int)$score;
    else
      throw new InvalidArgumentException("Non-existing or invalid object type for Rank.");
  }

  public function __get($name) {
    if (property_exists($this, $name))
      return $this->$name;
    throw new InvalidArgumentException("Non-existing property $name.");
  }

  /**
   * Unique representation of rank: team-division pairing
   *
   */
  public function hash() {
    $tid = ($this->team instanceof Team) ? $this->team->id : $this->team;
    return sprintf('%s-%s', $tid, $this->division);
  }

  /**
   * Compares the ranks by comparing the teams, then the divisions
   *
   * @param Rank $r1 the first rank
   * @param Rank $r2 the second rank
   * @return -1 when the first is less than the second, 1 when vice
   * versa, and 0 when equal
   */
  public static function compareTeam(Rank $r1, Rank $r2) {
    $ret = strcmp($r1->team, $r2->team);
    if ($ret != 0)
      return $ret;
    return strcmp($r1->division, $r2->division);
  }

  /**
   * Compares the ranks by comparing the score
   *
   * @param Rank $r1 the first rank
   * @param Rank $r2 the second rank
   * @eturn -1 when the first is less than the second, 1 when vice
   * versa, and 0 when equal
   */
  public static function compareScore(Rank $r1, Rank $r2) {
    return $r1->score - $r2->score;
  }
}


/**
 * Encapsulates a win-loss-tie record for a specific team.
 *
 * Provides functionality for ordering teams based on score, as
 * applicable to team racing.
 *
 * @author Dayan Paez
 * @version 2013-01-10
 */
class TeamRank extends Rank {
  public $wins;
  public $losses;
  public $ties;

  /**
   * The win-loss record for a specific team
   *
   * @param Team $team the team in question
   * @param int $wins the number of wins
   * @param int $losses the number of losses
   * @param int $ties number of ties (default = 0)
   */
  public function __construct(Team $team, $wins = 0, $losses = 0, $ties = 0) {
    parent::__construct($team, null);
    $this->team = $team;
    $this->wins = (int)$wins;
    $this->losses = (int)$losses;
    $this->ties = (int)$ties;
  }

  public function getWinPercentage() {
    $total = $this->wins + $this->losses + $this->ties;
    if ($total == 0)
      return 0;
    return $this->wins / $total;
  }

  public function getRecord() {
    $txt = sprintf('%d-%d', $this->wins, $this->losses);
    if ($this->ties > 0)
      $txt .= sprintf('-%d', $this->ties);
    return $txt;
  }
}

?>