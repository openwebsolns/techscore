<?php
/*
 * This file is part of Techscore
 */




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

  /**
   * The win-loss record for a specific team
   *
   * @param Team $team the team in question
   * @param int $wins the number of wins
   * @param int $losses the number of losses
   * @param int $ties number of ties (default = 0)
   */
  public function __construct(Team $team, $wins = 0, $losses = 0, $ties = 0, $exp = "") {
    parent::__construct($team, null);
    $this->team = $team;
    $this->wins = (float) $wins;
    $this->losses = (float) $losses;
    $this->ties = (int) $ties;

    // Preserve explanation, if one exists
    $this->explanation = $exp;
  }

  public function getWinPercentage() {
    $total = $this->wins + $this->losses + $this->ties;
    if ($total == 0)
      return 0;
    return $this->wins / $total;
  }

  public function getRecord() {
    $txt = sprintf('%s/%s', $this->wins, $this->losses);
    if ($this->ties > 0)
      $txt .= sprintf('/%d', $this->ties);
    return $txt;
  }
}
