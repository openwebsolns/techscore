<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package regatta
 */

require_once('regatta/ICSAScorer.php');

/**
 * Scores a regatta according to ICSA rules for combined regattas.
 *
 * 2010-02-24: Score combined divisions. For combined division
 * scoring, the finishes for all the divisions in the same race NUMBER
 * are combined into one virtual race, and points are awarded
 * accordingly. The FLEET value is then equal to the total number of
 * boats sailing: number of divisions * number of teams.
 *
 * In combined division scoring, "orphan" finishes (those race numbers
 * which are not finished in ALL divisions) are gracefully ignored
 * from the scoring process.
 *
 * 2011-01-30: When scoring a regatta, optionally score only the given
 * races, instead of all the races, for speed sake. Note that when
 * scoring select races, ICSAScorer will still update those finishes
 * in other races whose score depends on averages.
 *
 * @author Dayan Paez
 * @version 2010-01-28
 */
class ICSACombinedScorer extends ICSAScorer {

  /**
   * Helper method to identify any additional average-scored finishes
   * in the given list of races
   *
   */
  protected function &getAverageFinishes(Regatta $reg, $races) {
    $avg_finishes = array();
    foreach ($reg->getDivisions() as $div) {
      foreach ($reg->getAverageFinishes($div) as $finish) {
        if (!isset($avg_finishes[$finish->hash()]))
          $avg_finishes[$finish->hash()] = $finish;
      }
    }
    return $avg_finishes;
  }

  protected function &getFinishes(Regatta $reg, Race $race) {
    $finishes = array();
    foreach ($reg->getDivisions() as $div) {
      $r = $reg->getRace($div, $race->number);
      foreach ($reg->getFinishes($r) as $fin)
        $finishes[] = $fin;
    }
    usort($finishes, "Finish::compareEntered");
    return $finishes;
  }

  /**
   * Fleet size equals (# teams) * (# divisions)
   *
   * @param Regatta $reg the regatta
   * @return Penalty
   */
  public function getPenaltyScore(Finish $fin, Penalty $pen) {
    if ($pen->amount <= 0) {
      if ($this->fleet === null) {
	$reg = $fin->team->regatta;
	$this->fleet = count($reg->getTeams()) * count($reg->getDivisions()) + 1;
      }
      return new Score($this->fleet, sprintf("(%d, Fleet + 1) %s", $this->fleet, $pen->comments));
    }
    return parent::getPenaltyScore($fin, $pen);
  }
}
?>
