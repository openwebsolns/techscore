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
  public function getPenaltyScore(Finish $fin, Penalty $pen) {
    $amt = ($pen->amount <= 0) ? 6 : $pen->amount;
    $tot = $fin->earned + $amt;
    return new Score($tot, sprintf("(%d, +%d) %s", $tot, $amt, $pen->comments));
  }
  protected function displaceScore(Finish $fin, FinishModifier $pen) {
    return true;
  }
}
?>