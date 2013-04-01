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
  public function getPenaltiesScore(Finish $fin, Array $mods) {
    $amt = 0;
    $comments = array();
    foreach ($mods as $pen) {
      $amt += ($pen->amount <= 0) ? 6 : $pen->amount;
      if (!empty($pen->comments))
        $comments[] = $pen->comments;
    }
    $tot = $fin->earned + $amt;
    return new Score($tot, sprintf("(%d, +%d) %s", $tot, $amt, implode(". ", $comments)));
  }
  protected function reorderScore(Finish $fin, FinishModifier $pen) {
    $bkdlist = Breakdown::getList();
    if (isset($bkdlist[$pen->type]))
      return true;
    return false;
  }
}
?>