<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package regatta
 */

require_once('regatta/ICSACombinedScorer.php');

/**
 * Scores a team racing regatta.
 *
 * @author Dayan Paez
 * @version 2012-12-30
 */
class ICSATeamScorer extends ICSACombinedScorer {
  protected function getPenaltyScore(Finish $fin, Penalty $pen) {
    $amt = $pen->earned + 10;
    return new Score($amt, sprintf("(%d, +10) %s", $amt, $pen->comments));
  }
  protected function getPenaltyDisplace(Finish $fin, Penalty $pen) {
    return true;
  }
}
?>