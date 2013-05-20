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
      if ($pen->type == Penalty::DNS || $pen->type == Penalty::DNF) {
        $amt = ($pen->amount <= 0) ? 6 : $pen->amount;
        return new Score($amt, sprintf("(%d) %s", $amt, $pen->comments));
      }
      if ($pen->type == Penalty::OCS)
        $amt += ($pen->amount <= 0) ? 10 : $pen->amount;
      else
        $amt += ($pen->amount <= 0) ? 6 : $pen->amount;
      if (!empty($pen->comments))
        $comments[] = $pen->comments;
    }
    $tot = $fin->earned + $amt;
    return new Score($tot, sprintf("(%d, +%d) %s", $tot, $amt, implode(". ", $comments)));
  }

  protected function reorderScore(Finish $fin, FinishModifier $pen) {
    if ($pen->amount > 0)
      return ($pen->displace > 0) ? (int)$pen->amount : null;

    $bkdlist = Breakdown::getList();
    if (isset($bkdlist[$pen->type]))
      return null;

    if ($pen->type == Penalty::DNS || $pen->type == Penalty::DNF)
      return 6;
    if ($pen->type == Penalty::OCS)
      return 10;
    return null;
  }
}
?>