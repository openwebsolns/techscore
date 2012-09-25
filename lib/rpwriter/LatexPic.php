<?php
/*
 * This file is part of TechScore
 *
 * @package rpwriter
 */

/**
 * Helper class to encapsulate an RP picture environment
 *
 */
class LatexPic {

  private $x;
  private $y;
  private $body;

  /**
   * Creates a new LaTeX picture environment offset by the optional
   * amount $x, $y
   *
   * @param int $x the horizontal offset in inches of the origin
   * @param int $y the vertical offset in inches of the origin
   */
  public function __construct($x = 0, $y = 0) {
    $this->x = $x;
    $this->y = $y;
    $this->body = "";
  }

  /**
   * Build the body of the picture using LaTeX strings
   *
   * @param string $content
   */
  public function add($content) {
    $this->body .= (string)$content;
  }

  public function __toString() {
    return sprintf('\begin{picture}(0, 0)(%0.2f, %0.2f) %s \end{picture}',
                   $this->x, $this->y, $this->body);
  }
}

/**
 * Temporary RP manager allows temporary keeping of RP information for
 * overflow handling
 *
 */
class TempRpManager {

  private $teams;
  private $rps;

  public function __construct() {
    $this->teams = array();
    $this->rps   = array();
  }
  
  /**
   * Queues the given RP object
   *
   * @param RP $rp the RP object
   */
  public function add(RP $rp) {
    if (($pos = array_search($rp->team, $this->teams)) === false) {
      $this->teams[] = $rp->team;
      $pos = count($this->teams) - 1;
    }
    $this->rps[$pos][(string)$rp->division][$rp->boat_role][] = $rp;
  }

  /**
   * Returns list of teams in the order in which they were added to
   * this manager
   *
   * @return Array of Team objects
   */
  public function getTeams() {
    return $this->teams;
  }

  /**
   * Returns a list of RP object for the given team in the given
   * division with the given role
   *
   * @param Team $team the team
   * @param string $div the division
   * @param string $role the role (RP::SKIPPER|RP::CREW)
   * @return Array of RP objects matching criteria in the order in
   * which they were added to this TempRpManager
   */
  public function getRP(Team $team, Division $div, $role) {
    $d = (string)$div;
    $pos = array_search($team, $this->teams);
    if ($pos === false)                        return array();
    if (!isset($this->rps[$pos][$d]))        return array();
    if (!isset($this->rps[$pos][$d][$role])) return array();
    return $this->rps[$pos][$d][$role];
  }
}
?>