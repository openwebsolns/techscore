<?php
/*
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */


/**
 * Encapsulates a breakdown
 *
 * @author Dayan Paez
 * @created 2010-01-25
 */
class Breakdown extends FinishModifier {

  // Constants
  const RDG = "RDG";
  const BKD = "BKD";
  const BYE = "BYE";

  /**
   * @var int the minimum score than an averaged breakdown
   * deserves. This is tracked by the scoring algorithm so that an
   * entire race need not be re-scored just to determine a handicapped
   * team's finish average score; and to keep that average from never
   * being worse than that team's EARNED score, sans breakdown.
   */
  public $earned;

  public static function getList() {
    return array(Breakdown::BKD => "BKD: Breakdown",
		 Breakdown::RDG => "RDG: Yacht Given Redress",
		 Breakdown::BYE => "BYE: Team is awarded average");
  }
}
?>