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
   * @var int 
   */
  public $rank;
  /**
   * @var int useful for team racing
   */
  public $wins;
  public $losses;
  public $ties;

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
