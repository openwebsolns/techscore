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

  public $team;
  public $score;
  public $explanation;

  /**
   * Create a new rank with the given parameters
   *
   * @param Team $team the team
   * @param String $exp the (optional) explanation
   */
  public function __construct(Team $team, $score, $exp = "") {
    $this->team = $team;
    $this->score = $score;
    $this->explanation = (string)$exp;
  }

  public function __set($name, $value) {
    if ($name == "team" &&
	$value instanceof Team) {
      $this->team = $value;
    }
    elseif ($name == "explanation")
      $this->explanation = (string)$value;
    elseif ($name == "score")
      $this->score = (int)$score;
    else
      throw new InvalidArgumentException("Non-existing or invalid object type for Rank.");
  }

  /**
   * Compares the ranks by comparing the teams
   *
   * @param Rank $r1 the first rank
   * @param Rank $r2 the second rank
   * @return -1 when the first is less than the second, 1 when vice
   * versa, and 0 when equal
   */
  public static function compareTeam(Rank $r1, Rank $r2) {
    return strcmp($r1->team, $r2->team);
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
?>